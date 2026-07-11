<?php

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payroll\Employee;
use App\Models\Payroll\SewedItem;
use App\Models\Payroll\SewedItemPayslip;
use App\Models\Sublimation;
use App\Models\Tag;
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

    $this->tagA = Tag::create([
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => 25.50,
    ]);

    $this->tagB = Tag::create([
        'name' => 'Pants',
        'color' => '#33FF57',
        'price_per_piece' => 30.00,
    ]);

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

    $this->sublimationA->tags()->attach($this->tagA->id);
});

it('creates a sewed item and updates sublimation status', function () {
    $this->actingAs($this->adminA);

    $response = $this->post('/payroll/sewed-items', [
        'sublimation_id' => $this->sublimationA->id,
        'tags' => [
            ['tag_id' => $this->tagA->id, 'quantity' => 10, 'price_per_piece' => 25.50],
        ],
    ]);

    $response->assertRedirect();

    $sewedItem = SewedItem::where('sublimation_id', $this->sublimationA->id)->first();
    expect($sewedItem)->not->toBeNull();
    expect($sewedItem->quantity)->toBe(10);
    expect($sewedItem->amount)->toBe('255.00');
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
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->post('/payroll/sewed-items', [
        'sublimation_id' => $this->sublimationA->id,
        'tags' => [
            ['tag_id' => $this->tagA->id, 'quantity' => 10, 'price_per_piece' => 25.50],
        ],
    ]);

    $response->assertSessionHasErrors();
    expect(SewedItem::where('sublimation_id', $this->sublimationA->id)->count())->toBe(1);
});

it('allows admin to edit sewed items within their branch', function () {
    $this->actingAs($this->adminA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'tags' => [
            ['tag_id' => $this->tagA->id, 'quantity' => 20, 'price_per_piece' => 25.50],
        ],
        'notes' => 'Updated',
    ]);

    $response->assertRedirect();
    $sewedItem->refresh();
    expect($sewedItem->quantity)->toBe(20);
    expect($sewedItem->amount)->toBe('510.00');
    expect($sewedItem->unit_price)->toBe('25.50');
    expect($sewedItem->notes)->toBe('Updated');
});

it('allows staff to edit their own sewed items', function () {
    $this->actingAs($this->staffA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'tags' => [
            ['tag_id' => $this->tagA->id, 'quantity' => 15, 'price_per_piece' => 25.50],
        ],
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
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'tags' => [
            ['tag_id' => $this->tagA->id, 'quantity' => 20, 'price_per_piece' => 25.50],
        ],
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
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $otherStaff->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->put("/payroll/sewed-items/{$sewedItem->id}", [
        'tags' => [
            ['tag_id' => $this->tagA->id, 'quantity' => 20, 'price_per_piece' => 25.50],
        ],
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
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    SewedItem::create([
        'sublimation_id' => $sublimationB->id,
        'quantity' => 10,
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
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->delete("/payroll/sewed-items/{$sewedItem->id}");
    $response->assertForbidden();
});

it('generates a sewed item payslip and persists the record', function () {
    $this->actingAs($this->adminA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->post('/payroll/sewed-items/payslip', [
        'sewed_item_ids' => [$sewedItem->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('sewed_item_payslips', [
        'generated_by' => $this->adminA->id,
        'total_amount' => 500,
        'branch_id' => $this->branchA->id,
    ]);

    $payslip = SewedItemPayslip::first();
    expect($payslip->sewed_item_ids)->toBe([$sewedItem->id]);
});

it('allows staff to generate payslip for their own items', function () {
    $this->actingAs($this->staffA);

    $sewedItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 3,
        'amount' => 300,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->post('/payroll/sewed-items/payslip', [
        'sewed_item_ids' => [$sewedItem->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('sewed_item_payslips', [
        'generated_by' => $this->staffA->id,
        'total_amount' => 300,
    ]);
});

it('includes multiple sewed items in one payslip', function () {
    $this->actingAs($this->adminA);

    $item1 = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
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

    $item2 = SewedItem::create([
        'sublimation_id' => $sublimation2->id,
        'quantity' => 10,
        'amount' => 300,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->post('/payroll/sewed-items/payslip', [
        'sewed_item_ids' => [$item1->id, $item2->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('sewed_item_payslips', [
        'generated_by' => $this->adminA->id,
        'total_amount' => 800,
    ]);

    $payslip = SewedItemPayslip::first();
    expect($payslip->sewed_item_ids)->toBe([$item1->id, $item2->id]);
});

it('validates sewed_item_ids on payslip generation', function () {
    $this->actingAs($this->adminA);

    $response = $this->post('/payroll/sewed-items/payslip', [
        'sewed_item_ids' => [],
    ]);

    $response->assertSessionHasErrors(['sewed_item_ids']);
});

it('approves a payslip and marks items as completed', function () {
    $this->actingAs($this->adminA);

    $item = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $payslip = SewedItemPayslip::create([
        'generated_by' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'total_amount' => 500,
        'sewed_item_ids' => [$item->id],
        'status' => 'pending',
    ]);

    $response = $this->post("/payroll/sewed-items/payslip/{$payslip->id}/approve");

    $response->assertRedirect();

    $payslip->refresh();
    expect($payslip->status)->toBe('approved');
    expect($payslip->approved_by)->toBe($this->adminA->id);
    expect($payslip->approved_at)->not->toBeNull();

    $item->refresh();
    expect($item->completed_at)->not->toBeNull();
});

it('cancels a pending payslip', function () {
    $this->actingAs($this->adminA);

    $payslip = SewedItemPayslip::create([
        'generated_by' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'total_amount' => 500,
        'sewed_item_ids' => [1],
        'status' => 'pending',
    ]);

    $response = $this->post("/payroll/sewed-items/payslip/{$payslip->id}/cancel");

    $response->assertRedirect();

    $payslip->refresh();
    expect($payslip->status)->toBe('cancelled');
});

it('prevents approving an already approved payslip', function () {
    $this->actingAs($this->adminA);

    $payslip = SewedItemPayslip::create([
        'generated_by' => $this->adminA->id,
        'branch_id' => $this->branchA->id,
        'total_amount' => 500,
        'sewed_item_ids' => [1],
        'status' => 'approved',
    ]);

    $response = $this->post("/payroll/sewed-items/payslip/{$payslip->id}/approve");

    $response->assertSessionHasErrors();
    expect($payslip->fresh()->status)->toBe('approved');
});

it('hides completed items from index by default', function () {
    $this->actingAs($this->adminA);

    $sublimationB = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'status' => SublimationStatus::SEWING,
        'description' => 'Team Jersey B',
        'due_at' => now()->addDays(7),
        'quantity' => 20,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    $completed = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
        'completed_at' => now(),
    ]);

    $active = SewedItem::create([
        'sublimation_id' => $sublimationB->id,
        'quantity' => 3,
        'amount' => 300,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
        'completed_at' => null,
    ]);

    $response = $this->get('/payroll/sewed-items');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $ids = array_column($props['sewedItems']['data'], 'id');
    expect($ids)->toContain($active->id);
    expect($ids)->not->toContain($completed->id);
});

it('shows completed items when filter is enabled', function () {
    $this->actingAs($this->adminA);

    $completed = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
        'completed_at' => now(),
    ]);

    $response = $this->get('/payroll/sewed-items?include_completed=1');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $ids = array_column($props['sewedItems']['data'], 'id');
    expect($ids)->toContain($completed->id);
});

it('staff with can_edit_sewed_items flag can edit any sewed item in their branch', function () {
    $employee = Employee::create([
        'branch_id' => $this->branchA->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
        'first_name' => 'StaffA',
        'last_name' => 'Employee',
        'can_edit_sewed_items' => true,
    ]);

    // staffA created via User factory, update to link to employee
    $this->staffA->update(['employee_id' => $employee->id]);

    $otherUser = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    $sublimationA2 = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'status' => SublimationStatus::SEWING,
        'description' => 'Other Jersey',
        'due_at' => now()->addDays(7),
        'quantity' => 5,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    $item = SewedItem::create([
        'sublimation_id' => $sublimationA2->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $otherUser->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $this->actingAs($this->staffA);

    $response = $this->put("/payroll/sewed-items/{$item->id}", [
        'notes' => 'Updated by staff with flag',
        'tags' => [
            [
                'tag_id' => $this->tagA->id,
                'quantity' => 5,
                'price_per_piece' => 25.50,
            ],
        ],
    ]);

    $response->assertRedirect();
    expect($item->fresh()->notes)->toBe('Updated by staff with flag');
});

it('staff without flag cannot edit another staff sewed item', function () {
    $otherUser = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    $sublimationA2 = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'status' => SublimationStatus::SEWING,
        'description' => 'Other Jersey 2',
        'due_at' => now()->addDays(7),
        'quantity' => 5,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    $item = SewedItem::create([
        'sublimation_id' => $sublimationA2->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $otherUser->id,
        'sewed_date' => now()->toDateString(),
    ]);

    // staffA (no employee, no flag) trying to edit other user's item
    $this->actingAs($this->staffA);

    $response = $this->put("/payroll/sewed-items/{$item->id}", [
        'notes' => 'Should not work',
        'tags' => [
            [
                'tag_id' => $this->tagA->id,
                'quantity' => 5,
                'price_per_piece' => 25.50,
            ],
        ],
    ]);

    $response->assertForbidden();
});

it('staff with can_edit_sewed_items sees all branch items in index', function () {
    $employee = Employee::create([
        'branch_id' => $this->branchA->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
        'first_name' => 'StaffA',
        'last_name' => 'Employee',
        'can_edit_sewed_items' => true,
    ]);

    $this->staffA->update(['employee_id' => $employee->id]);

    $otherUser = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    $sublimationA2 = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'status' => SublimationStatus::SEWING,
        'description' => 'Other Jersey 3',
        'due_at' => now()->addDays(7),
        'quantity' => 5,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    $myItem = SewedItem::create([
        'sublimation_id' => $this->sublimationA->id,
        'quantity' => 3,
        'amount' => 300,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->staffA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $theirItem = SewedItem::create([
        'sublimation_id' => $sublimationA2->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $otherUser->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $this->actingAs($this->staffA);

    $response = $this->get('/payroll/sewed-items');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $ids = array_column($props['sewedItems']['data'], 'id');

    expect($ids)->toContain($myItem->id);
    expect($ids)->toContain($theirItem->id);
});

it('filters index by sublimation description search', function () {
    $this->actingAs($this->adminA);

    $sublimationJersey = $this->sublimationA; // description "Team Jersey A"

    $sublimationBanner = Sublimation::create([
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1500,
        'status' => SublimationStatus::SEWED,
        'description' => 'Vinyl Banner',
        'due_at' => now()->addDays(7),
        'quantity' => 5,
        'notes' => 'Test',
        'transaction_type' => 'retail',
    ]);

    $jerseyItem = SewedItem::create([
        'sublimation_id' => $sublimationJersey->id,
        'quantity' => 5,
        'amount' => 500,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $bannerItem = SewedItem::create([
        'sublimation_id' => $sublimationBanner->id,
        'quantity' => 5,
        'amount' => 750,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->adminA->id,
        'sewed_date' => now()->toDateString(),
    ]);

    $response = $this->get('/payroll/sewed-items?search=jersey');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $ids = array_column($props['sewedItems']['data'], 'id');

    expect($ids)->toContain($jerseyItem->id);
    expect($ids)->not->toContain($bannerItem->id);
});

it('uses default page size without a date filter', function () {
    $this->actingAs($this->adminA);

    $response = $this->get('/payroll/sewed-items');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['sewedItems']['per_page'])->toBe(20);
});

it('widens page size to 200 when a date filter is applied', function () {
    $this->actingAs($this->adminA);

    $response = $this->get('/payroll/sewed-items?date_from='.now()->toDateString());
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['sewedItems']['per_page'])->toBe(200);
});
