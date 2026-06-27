<?php

use App\Models\Branch;
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

    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);
});

it('allows any authenticated user to create a tag with price_per_piece', function () {
    $this->actingAs($this->staff);

    $response = $this->post(route('tags.store'), [
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => '25.50',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('tags', [
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => 25.50,
    ]);
});

it('allows creating a tag without price_per_piece', function () {
    $this->actingAs($this->superadmin);

    $response = $this->post(route('tags.store'), [
        'name' => 'Pants',
        'color' => '#33FF57',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('tags', [
        'name' => 'Pants',
        'price_per_piece' => null,
    ]);
});

it('rejects invalid price_per_piece values', function () {
    $this->actingAs($this->superadmin);

    $response = $this->post(route('tags.store'), [
        'name' => 'Sando',
        'color' => '#3357FF',
        'price_per_piece' => 'abc',
    ]);

    $response->assertSessionHasErrors(['price_per_piece']);
});

it('allows admin to update tag price_per_piece', function () {
    $tag = Tag::create([
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => 25.50,
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('tags.update', $tag), [
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => '30.00',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'price_per_piece' => 30.00,
    ]);
});

it('allows clearing price_per_piece by passing null', function () {
    $tag = Tag::create([
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => 25.50,
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('tags.update', $tag), [
        'name' => 'Shirt',
        'color' => '#FF5733',
        'price_per_piece' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'price_per_piece' => null,
    ]);
});

it('rejects negative price_per_piece', function () {
    $this->actingAs($this->superadmin);

    $response = $this->post(route('tags.store'), [
        'name' => 'Sando',
        'color' => '#3357FF',
        'price_per_piece' => '-10',
    ]);

    $response->assertSessionHasErrors(['price_per_piece']);
});

it('prevents staff from deleting tags', function () {
    $tag = Tag::create([
        'name' => 'Shirt',
        'color' => '#FF5733',
    ]);

    $this->actingAs($this->staff);

    $response = $this->delete(route('tags.destroy', $tag));

    $response->assertForbidden();
});

it('allows superadmin to delete tags', function () {
    $tag = Tag::create([
        'name' => 'Shirt',
        'color' => '#FF5733',
    ]);

    $this->actingAs($this->superadmin);

    $response = $this->delete(route('tags.destroy', $tag));

    $response->assertRedirect();

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});
