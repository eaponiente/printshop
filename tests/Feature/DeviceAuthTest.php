<?php

use App\Models\RegisteredDevice;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('app.url', 'http://localhost');
    Config::set('features.device_auth', true);
});

// ─── Feature flag ───────────────────────────────────────────

it('allows all users through when device auth is disabled', function () {
    Config::set('features.device_auth', false);

    $user = User::factory()->create(['role' => 'staff']);
    RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

it('enforces device auth when feature flag is enabled', function () {
    Config::set('features.device_auth', true);

    $user = User::factory()->create(['role' => 'staff']);
    RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->component('auth/device-auth'));
});

// ─── Superadmin bypass ───────────────────────────────────────

it('bypasses device auth for superadmin', function () {
    $user = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

// ─── Cookie-based auth ───────────────────────────────────────

it('passes through when device_token cookie matches approved device', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $device = RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => true,
        'device_token' => 'valid-token',
    ]);

    $this->actingAs($user)
        ->withCookie('device_token', 'valid-token')
        ->get(route('profile.edit'))
        ->assertOk();

    expect($device->fresh()->last_used_at)->not->toBeNull();
});

it('blocks when device_token cookie does not match any approved device', function () {
    $user = User::factory()->create(['role' => 'staff']);
    RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => true,
        'device_token' => 'other-token',
    ]);

    $this->actingAs($user)
        ->withCookie('device_token', 'wrong-token')
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->component('auth/device-auth'));
});

// ─── No devices ──────────────────────────────────────────────

it('shows registration page when user has no devices', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/device-auth')
            ->where('hasApproved', false)
            ->where('hasPending', false)
        );
});

// ─── Pending device ──────────────────────────────────────────

it('shows pending state when user has pending device', function () {
    $user = User::factory()->create(['role' => 'staff']);
    RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => false,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/device-auth')
            ->where('hasPending', true)
        );
});

// ─── Session-based pass-through ─────────────────────────────

it('passes through when device_verified session matches user', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->withSession(['device_verified' => $user->id])
        ->get(route('profile.edit'))
        ->assertOk();
});

it('re-checks when device_verified session is for different user', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->withSession(['device_verified' => 999])
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->component('auth/device-auth'));
});

// ─── Excluded paths ──────────────────────────────────────────

it('does not intercept logout requests', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();
});

it('does not intercept device-auth API requests', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->postJson(route('device-auth.register'), ['device_name' => 'Test'])
        ->assertOk();
});

// ─── Device registration ─────────────────────────────────────

it('registers device and returns pending for non-superadmin', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($user)
        ->postJson(route('device-auth.register'), ['device_name' => 'My Device'])
        ->assertOk()
        ->assertJson(['pending' => true]);

    $response->assertCookie('device_token');

    expect(RegisteredDevice::where('user_id', $user->id)->first()->is_approved)->toBeFalse();
});

it('auto-approves and redirects for superadmin', function () {
    $user = User::factory()->create(['role' => 'superadmin']);

    $response = $this->actingAs($user)
        ->postJson(route('device-auth.register'), ['device_name' => 'Admin Device'])
        ->assertOk()
        ->assertJson(['verified' => true]);

    expect(RegisteredDevice::where('user_id', $user->id)->first()->is_approved)->toBeTrue();
});

// ─── Device management (superadmin only) ─────────────────────

it('allows superadmin to view device management page', function () {
    $user = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($user)
        ->get(route('devices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('devices/index'));
});

it('denies admin from viewing device management page', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('devices.index'))
        ->assertForbidden();
});

it('denies staff from viewing device management page', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->get(route('devices.index'))
        ->assertForbidden();
});

// ─── Approve / reject / deactivate ───────────────────────────

it('allows superadmin to approve a pending device', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $user = User::factory()->create(['role' => 'staff']);
    $device = RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => false,
    ]);

    $this->actingAs($superadmin)
        ->post(route('devices.approve', $device))
        ->assertRedirect();

    expect($device->fresh()->is_approved)->toBeTrue();
    expect($device->fresh()->approved_by)->toBe($superadmin->id);
});

it('denies non-superadmin from approving a device', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'staff']);
    $device = RegisteredDevice::factory()->create([
        'user_id' => $user->id,
        'is_approved' => false,
    ]);

    $this->actingAs($admin)
        ->post(route('devices.approve', $device))
        ->assertForbidden();
});

it('allows superadmin to reject a pending device', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $device = RegisteredDevice::factory()->create(['is_approved' => false]);

    $this->actingAs($superadmin)
        ->delete(route('devices.reject', $device))
        ->assertRedirect();

    expect(RegisteredDevice::find($device->id))->toBeNull();
});

it('allows superadmin to deactivate an approved device', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $device = RegisteredDevice::factory()->create(['is_approved' => true]);

    $this->actingAs($superadmin)
        ->post(route('devices.deactivate', $device))
        ->assertRedirect();

    expect($device->fresh()->is_active)->toBeFalse();
});
