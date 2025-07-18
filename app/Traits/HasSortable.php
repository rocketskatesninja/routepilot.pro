<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSortable
{
    /**
     * Apply sorting to a query builder.
     */
    protected function applySorting(Builder $query, array $sortOptions, string $defaultSort = 'created_at'): Builder
    {
        $sortBy = request()->get('sort_by', $defaultSort);
        
        if (isset($sortOptions[$sortBy])) {
            $sortConfig = $sortOptions[$sortBy];
            $column = $sortConfig['column'] ?? $sortBy;
            $direction = $sortConfig['direction'] ?? 'desc';
            
            return $query->orderBy($column, $direction);
        }
        
        return $query->orderBy($defaultSort, 'desc');
    }

    /**
     * Get default sort options for common patterns.
     */
    protected function getDefaultSortOptions(): array
    {
        return [
            'date_desc' => ['column' => 'created_at', 'direction' => 'desc'],
            'date_asc' => ['column' => 'created_at', 'direction' => 'asc'],
            'name' => ['column' => 'name', 'direction' => 'asc'],
            'status' => ['column' => 'status', 'direction' => 'asc'],
            'amount' => ['column' => 'total_amount', 'direction' => 'desc'],
        ];
    }
} 