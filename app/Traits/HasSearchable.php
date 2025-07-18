<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSearchable
{
    /**
     * Apply search functionality to a query builder.
     */
    protected function applySearch(Builder $query, array $searchFields, string $searchTerm): Builder
    {
        if (empty($searchTerm)) {
            return $query;
        }

        return $query->where(function ($q) use ($searchFields, $searchTerm) {
            foreach ($searchFields as $field) {
                if (str_contains($field, '.')) {
                    // Handle relationship searches
                    [$relation, $column] = explode('.', $field);
                    $q->orWhereHas($relation, function ($subQuery) use ($column, $searchTerm) {
                        $subQuery->where($column, 'like', "%{$searchTerm}%");
                    });
                } else {
                    // Handle direct column searches
                    $q->orWhere($field, 'like', "%{$searchTerm}%");
                }
            }
        });
    }

    /**
     * Get search term from request.
     */
    protected function getSearchTerm($request): string
    {
        return $request->filled('search') ? $request->search : '';
    }
} 