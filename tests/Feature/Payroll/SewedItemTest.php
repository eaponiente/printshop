<?php

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payroll\SewedItem;
use App\Models\Sublimation;
use App\Models\User;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->adminA = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branchA->id,
    ]);

    $this->staffA = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    $this->adminB = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branchB->id,
    ]);

    $this->staffB = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchB->id,
    ]);

    $this->customer = Customer::factory()->create();

    $this->sublimationA = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'status' => SublimationStatus::SEWING,
        'description' => 'Team Jersey A',
        'due_at' => now()->addDays(7),
        'quantity' => 10,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);
});

it('creates a sewed item and updates sublimation status', function () {
    $this->actingAs($this->adminA);

    $response = $this->post('/payroll/sewed-items', [
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 10,
        'unit_price' => 150,
    ]);

    $response->assertRedirect();

    $sewedItem = SewedItem::where('sublimation_id', $this->sublimationA->id)->first();
    expect($sewedItem)->not->toBeNull();
    expect($sewedItem->quantity)->toBe(10);
    expect($sewedItem->unit_price)->toBe('150.00');
    expect($sewedItem->amount)->toBe('1500.00');
    expect($sewedItem->branch_id)->toBe($this->branchA->id);
    expect($sewedItem->user_id)->toBe($this->adminA->id);
    expect($sewedItem->notes)->toBeNull();

    $this->sublimationA->refresh();
    expect($this->sublimationA->status)->toBe(SublimationStatus::SEWED);
});

it('prevents duplicate sewed items for same sublimation', function () {
    $this->actingAs($this->adminA);

    SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->post('/payroll/sewed-items', [
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 10,
        'unit_price' => 150,
    ]);

    $response->assertSessionHasErrors();
    expect(SewedItem::where('sublimation_id', $this->sublimationA->id)->count())->toBe(1);
});

it('allows admin to edit sewed items within their branch', function () {
    $this->actingAs($this->adminA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'quantity' => 20,
        'unit_price' => 200,
        'notes' => 'Updated',
    ]);

    $response->assertRedirect();
    $sewedItem->refresh();
    expect($sewedItem->quantity)->toBe(20);
    expect($sewedItem->unit_price)->toBe('200.00');
    expect($sewedItem->amount)->toBe('4000.00');
    expect($sewedItem->notes)->toBe('Updated');
});

it('allows staff to edit their own sewed items', function () {
    $this->actingAs($this->staffA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'quantity' => 15,
        'unit_price' => 50,
        'notes' => 'Staff updated',
    ]);

    $response->assertRedirect();
    $sewedItem->refresh();
    expect($sewedItem->quantity)->toBe(15);
});

it('prevents admin from editing sewed items in another branch', function () {
    $this->actingAs($this->adminB);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'quantity' => 20,
        'unit_price' => 200,
        'notes' => 'Attempted edit cross-branch',
    ]);

    $response->assertForbidden();
});

it('prevents staff from editing sewed items created by others', function () {
    $otherStaff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($this->staffA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $otherStaff->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'quantity' => 20,
        'unit_price' => 200,
        'notes' => 'Attempted edit by other staff',
    ]);

    $response->assertForbidden();
});

it('branch-scopes sewed items in index for admin', function () {
    $this->actingAs($this->adminA);

    $sublimationB = Sublimation::create([
        'branch_id' => $this->branchB->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'status' => SublimationStatus::SEWED,
        'description' => 'Team Jersey B',
        'due_at' => now()->addDays(7),
        'quantity' => 20,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    SewedItem::create([
        'sublimation_id' => $sublimationB->id,
        'quantity' => 10,
        'unit_price' => 200,
        'amount' => 2000,
        'branch_id' => $this->branchB->id,
        'user_id' => $this->adminB->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->get('/payroll/sewed-items');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect(count($props['sewedItems']['data']))->toBe(1);
    expect($props['sewedItems']['data'][0]['branch_id'])->toBe($this->branchA->id);
});

it('shows staff only their own sewed items', function () {
    $otherStaff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($this->staffA);

    SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $sublimation2 = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'status' => SublimationStatus::SEWED,
        'description' => 'Team Jersey 2',
        'due_at' => now()->addDays(7),
        'quantity' => 30,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    SewedItem::create([
        'sublimation_id' => $sublimation2->id,
        'quantity' => 10,
        'unit_price' => 200,
        'amount' => 2000,
        'branch_id' => $this->branchA->id,
        'user_id' => $otherStaff->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->get('/payroll/sewed-items');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect(count($props['sewedItems']['data']))->toBe(1);
    expect($props['sewedItems']['data'][0]['user_id'])->toBe($this->staffA->id);
});

it('allows superadmin to delete sewed items', function () {
    $this->actingAs($this->superadmin);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->delete("/payroll/sewed-items/{$sewedItem->id}");
    $response->assertRedirect();

    expect(SewedItem::find($sewedItem->id))->toBeNull();
});

it('prevents staff from deleting sewed items', function () {
    $this->actingAs($this->staffA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->delete("/payroll/sewed-items/{$sewedItem->id}");
    $response->assertForbidden();
});
