<?php

declare(strict_types=1);

namespace App\Actions;

/**
 * Maps validated service-type input to model attributes (shared by the
 * create + update actions). Drops blank task-checklist lines.
 */
class ServiceTypeAttributes
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function from(array $data): array
    {
        $tasks = is_array($data['tasks'] ?? null)
            ? array_values(array_filter($data['tasks'], fn ($t): bool => is_string($t) && trim($t) !== ''))
            : [];

        return [
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'frequency' => $data['frequency'] ?? 'weekly',
            'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? 30,
            'price' => $data['price'] ?? 0,
            'chemicals_included' => $data['chemicals_included'] ?? true,
            'description' => $data['description'] ?? null,
            'tasks' => $tasks,
            'field_modules' => is_array($data['field_modules'] ?? null) ? $data['field_modules'] : null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
