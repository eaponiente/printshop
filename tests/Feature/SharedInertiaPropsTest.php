<?php

use App\Models\User;

it('shares serverToday on every Inertia page as the server calendar date', function () {
    $frozenNow = now()->setDate(2026, 9, 7)->setTime(3, 30, 0);
    $this->travelTo($frozenNow);

    $user = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(function ($page) use ($frozenNow) {
        $page->where('serverToday', $frozenNow->toDateString());
    });

    expect($response->inertiaProps('serverToday'))->toBe(now()->toDateString());
});
