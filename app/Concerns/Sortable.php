<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Sortable
{
    /**
     * Scope a query to sort by request parameters.
     */
    public function scopeSort(Builder $query, Request $request, array $allowedSorts = []): Builder
    {
        $field = $request->query('sort_field');
        $direction = $request->query('sort_direction', 'asc');

        // Only sort if the field is in our allowed list
        if (in_array($field, $allowedSorts)) {
            return $query->orderBy($field, $direction === 'desc' ? 'desc' : 'asc');
        }

        // Optional: Default sort if no valid field is provided
        return $query->orderBy('created_at', 'desc');
    }
}
