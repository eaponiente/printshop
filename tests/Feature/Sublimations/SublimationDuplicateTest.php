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

    $this->shirtTag = Tag::create(['name' => 'Shirt', 'color' => '#FF0000']);
    $this->shortsTag = Tag::create(['name' => 'Shorts', 'color' => '#00FF00']);
    $this->socksTag = Tag::create(['name' => 'Socks', 'color' => '#0000FF']);

    $this->source = Sublimation::create([
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'amount_total' => 1000,
        'status' => 'for_approval',
        'description' => 'Team Jersey',
        'due_at' => now()->addDays(7),
        'quantity' => 10,
    ]);
    $this->source->tags()->sync([
        $this->shirtTag->id => ['quantity' => 5],
        $this->shortsTag->id => ['quantity' => 5],
    ]);
});

it('persists the client-supplied description on the additional copy', function () {
    $this->actingAs($this->admin);

    $this->post(route('sublimations.duplicate', $this->source), [
        'description' => 'Team Jersey (Addtl. 1)',
        'tag_ids' => [
            ['id' => $this->shirtTag->id, 'quantity' => 3],
        ],
        'amount_total' => 500,
    ])->assertRedirect();

    $this->assertDatabaseHas('sublimations', [
        'description' => 'Team Jersey (Addtl. 1)',
        'customer_id' => $this->customer->id,
        'branch_id' => $this->branch->id,
        'quantity' => 3,
    ]);
});

it('rejects when description is missing', function () {
    $this->actingAs($this->admin);

    $this->post(route('sublimations.duplicate', $this->source), [
        'tag_ids' => [
            ['id' => $this->shirtTag->id, 'quantity' => 1],
        ],
        'amount_total' => 100,
    ])->assertSessionHasErrors(['description']);
});

it('allows adding a new category not on the source and removing one that was on the source', function () {
    $this->actingAs($this->admin);

    // Source had Shirt + Shorts. We're sending only Shirt + Socks (Socks is new, Shorts dropped).
    $this->post(route('sublimations.duplicate', $this->source), [
        'description' => 'Team Jersey (Addtl. 1)',
        'tag_ids' => [
            ['id' => $this->shirtTag->id, 'quantity' => 2],
            ['id' => $this->socksTag->id, 'quantity' => 4],
        ],
        'amount_total' => 400,
    ])->assertRedirect();

    $copy = Sublimation::where('description', 'Team Jersey (Addtl. 1)')->firstOrFail();

    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $copy->id,
        'tag_id' => $this->shirtTag->id,
        'quantity' => 2,
    ]);
    $this->assertDatabaseHas('sublimation_tag', [
        'sublimation_id' => $copy->id,
        'tag_id' => $this->socksTag->id,
        'quantity' => 4,
    ]);
    $this->assertDatabaseMissing('sublimation_tag', [
        'sublimation_id' => $copy->id,
        'tag_id' => $this->shortsTag->id,
    ]);

    expect((int) $copy->quantity)->toBe(6);
});

it('copies branch, customer, user, due_at and resets status', function () {
    $this->source->update(['user_id' => $this->admin->id]);
    $this->actingAs($this->admin);

    $this->post(route('sublimations.duplicate', $this->source), [
        'description' => 'Team Jersey (Addtl. 1)',
        'tag_ids' => [
            ['id' => $this->shirtTag->id, 'quantity' => 1],
        ],
        'amount_total' => 100,
    ])->assertRedirect();

    $copy = Sublimation::where('description', 'Team Jersey (Addtl. 1)')->firstOrFail();

    expect($copy->branch_id)->toBe($this->source->branch_id);
    expect($copy->customer_id)->toBe($this->source->customer_id);
    expect($copy->user_id)->toBe($this->source->user_id);
    expect($copy->status->value)->toBe('for_approval');
});
