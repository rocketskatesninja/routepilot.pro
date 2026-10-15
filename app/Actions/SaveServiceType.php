<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceType;

/**
 * Add a service to the catalog, or save changes to one. Blank checklist lines
 * are dropped and the visit sections are stored as the four known switches.
 */
class SaveServiceType
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?ServiceType $type = null): ServiceType
    {
        $tasks = array_values(array_filter((array) ($data['tasks'] ?? []), fn ($t): bool => is_string($t) && trim($t) !== ''));
        $modules = [];
        foreach (array_keys(ServiceType::MODULES) as $key) {
            $modules[$key] = (bool) ($data['field_modules'][$key] ?? true);
        }

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'],
            'price' => $data['price'],
            'estimated_duration_minutes' => $data['estimated_duration_minutes'],
            'tasks' => array_map('trim', $tasks),
            'field_modules' => $modules,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'requestable' => (bool) ($data['requestable'] ?? true),
        ];

        if ($type === null) {
            return ServiceType::create($attributes);
        }
        $type->update($attributes);

        return $type;
    }
}
