<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Branch A']);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);

    $this->customer = Customer::factory()->create();

    $this->tag = Tag::create(['name' => 'Shirt', 'color' => '#FF5733']);
    $this->tag2 = Tag::create(['name' => 'Polo', 'color' => '#33FF57']);

    $this->sublimation = Sublimation::create([
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'status' => 'for_approval',
        'description' => 'Team Jersey',
        'due_at' => now()->addDays(7),
        'quantity' => 10,
    ]);
});

it('stores per-tag quantity on the pivot when creating a sublimation', function () {
    $this->actingAs($this->admin);

    $this->post(route('sublimations.store'), [
        'description' => 'New Order',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 500,
        'tag_ids' => [
            ['id' => $this->tag->id, 'quantity' => 3],
            ['id' => $this->tag2->id, 'quantity' => 7],
        ],
    ])->assertRedirect();

    $sublimation = Sublimation::where('description', 'New Order')->firstOrFail();

    $this->assertDatabaseHas('sublimations', [
        'id' => $sublimation->id,
        'quantity' => 10,
    ]);

    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $sublimation->id,
        'tag_id' => $this->tag->id,
        'quantity' => 3,
    ]);

    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $sublimation->id,
        'tag_id' => $this->tag2->id,
        'quantity' => 7,
    ]);
});

it('updates per-tag quantity on the pivot when editing a sublimation', function () {
    $this->sublimation->tags()->attach($this->tag->id, ['quantity' => 1]);

    $this->actingAs($this->admin);

    $this->put(route('sublimations.update', $this->sublimation), [
        'description' => 'Updated Order',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'transaction_type' => 'retail',
        'production_authorized' => false,
        'tag_ids' => [
            ['id' => $this->tag->id, 'quantity' => 5],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $this->sublimation->id,
        'tag_id' => $this->tag->id,
        'quantity' => 5,
    ]);
});

it('defaults quantity to 1 when adding a tag inline from the list', function () {
    $this->actingAs($this->admin);

    $this->post(route('sublimations.tags.add', $this->sublimation), [
        'tag_id' => $this->tag->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $this->sublimation->id,
        'tag_id' => $this->tag->id,
        'quantity' => 1,
    ]);
});

it('updates pivot quantity via the inline update-quantity endpoint', function () {
    $this->sublimation->tags()->attach($this->tag->id, ['quantity' => 1]);

    $this->actingAs($this->admin);

    $this->patch(
        route('sublimations.tags.update-quantity', [
            'sublimation' => $this->sublimation->id,
            'tag' => $this->tag->id,
        ]),
        ['quantity' => 9]
    )->assertRedirect();

    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $this->sublimation->id,
        'tag_id' => $this->tag->id,
        'quantity' => 9,
    ]);
});

it('rejects quantity less than 1 on update-quantity endpoint', function () {
    $this->sublimation->tags()->attach($this->tag->id, ['quantity' => 1]);

    $this->actingAs($this->admin);

    $this->patch(
        route('sublimations.tags.update-quantity', [
            'sublimation' => $this->sublimation->id,
            'tag' => $this->tag->id,
        ]),
        ['quantity' => 0]
    )->assertSessionHasErrors(['quantity']);
});

it('rejects missing quantity on create', function () {
    $this->actingAs($this->admin);

    $this->post(route('sublimations.store'), [
        'description' => 'New Order',
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 500,
        'tag_ids' => [
            ['id' => $this->tag->id],
        ],
    ])->assertSessionHasErrors(['tag_ids.0.quantity']);
});
