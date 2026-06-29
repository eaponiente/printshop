<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Branch A']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);

    $this->staffA = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);

    $this->staffB = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);

    $this->customer = Customer::factory()->create();

    $this->tag = Tag::create([
        'name' => 'Shirt',
        'color' => '#FF5733',
    ]);
});

it('creates a sublimation with user_id', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('sublimations.store'), [
        'description' => 'Team Jersey',
        'notes' => 'Test notes',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'tag_ids' => [['id' => $this->tag->id, 'quantity' => 1]],
        'user_id' => $this->staffA->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('sublimations', [
        'description' => 'Team Jersey',
        'user_id' => $this->staffA->id,
    ]);
});

it('creates a sublimation without user_id', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('sublimations.store'), [
        'description' => 'Team Jersey',
        'notes' => 'Test notes',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'tag_ids' => [['id' => $this->tag->id, 'quantity' => 1]],
        'user_id' => '',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('sublimations', [
        'description' => 'Team Jersey',
        'user_id' => null,
    ]);
});

it('updates a sublimation user_id', function () {
    $sublimation = Sublimation::create([
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'status' => 'for_approval',
        'description' => 'Team Jersey',
        'due_at' => now()->addDays(7),
        'quantity' => 10,
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('sublimations.update', $sublimation), [
        'description' => 'Team Jersey Updated',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 2000,
        'transaction_type' => 'retail',
        'production_authorized' => false,
        'tag_ids' => [['id' => $this->tag->id, 'quantity' => 1]],
        'user_id' => $this->staffB->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('sublimations', [
        'id' => $sublimation->id,
        'user_id' => $this->staffB->id,
    ]);
});

it('rejects invalid user_id on sublimation create', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('sublimations.store'), [
        'description' => 'Team Jersey',
        'notes' => 'Test notes',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'tag_ids' => [['id' => $this->tag->id, 'quantity' => 1]],
        'user_id' => 99999,
    ]);

    $response->assertSessionHasErrors(['user_id']);
});
