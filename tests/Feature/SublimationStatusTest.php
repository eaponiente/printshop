<?php

use App\Models\Sublimation;
use App\Enums\Sublimations\SublimationStatus;
use App\Models\User;

it('blocks status transition to any state when completed', function () {
    $user = User::factory()->create(['role' => 'staff']);
    
    $sublimation = Sublimation::factory()->create([
        'status' => SublimationStatus::COMPLETED->value
    ]);
    
    $this->actingAs($user)
        ->patch(route('sublimations.update-status', $sublimation), [
            'status' => SublimationStatus::FOR_APPROVAL->value
        ])
        ->assertSessionHasErrors(['status']);
        
    expect($sublimation->fresh()->status->value)->toEqual(SublimationStatus::COMPLETED->value);
});

it('allows superadmin to transition from completed state', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    
    $sublimation = Sublimation::factory()->create([
        'status' => SublimationStatus::COMPLETED->value
    ]);
    
    $this->actingAs($superadmin)
        ->patch(route('sublimations.update-status', $sublimation), [
            'status' => SublimationStatus::FOR_APPROVAL->value
        ])
        ->assertRedirect();
        
    expect($sublimation->fresh()->status->value)->toEqual(SublimationStatus::FOR_APPROVAL->value);
});
