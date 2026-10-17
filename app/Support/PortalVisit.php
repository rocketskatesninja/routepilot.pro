<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ServiceVisit;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * A completed visit the way the customer portal shows it — one shape for the
 * Home page's latest report and every entry in Service History.
 */
final class PortalVisit
{
    /** Relations to eager-load before calling toArray(). */
    public const WITH = [
        'pool:id,name,custom_target_ranges,sanitizer_type',
        'agent:id,first_name,last_name',
        'chemicalReading', 'treatments', 'tasks', 'photos',
        'serviceSubscription.serviceType:id,name',
    ];

    /** @return array<string, mixed> */
    public static function toArray(ServiceVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'pool' => $visit->pool?->getAttribute('name'),
            'pool_id' => $visit->getAttribute('pool_id'),
            'service' => $visit->serviceSubscription?->serviceType?->name,
            'on' => Tenant::localDate($visit->completed_at),
            'agent' => $visit->agent?->firstName(),
            'notes' => $visit->notes,
            'reading' => self::reading($visit),
            // The pool's own chemistry targets, so "good / low / high" matches what the tech works to.
            'targets' => $visit->pool?->custom_target_ranges,
            'treatments' => $visit->treatments->map(fn ($t): array => [
                'name' => $t->getAttribute('chemical_name'),
                'amount' => (float) $t->amount,
                'unit' => $t->getAttribute('unit'),
            ])->all(),
            'tasks' => $visit->tasks->map(fn ($t): array => [
                'name' => $t->getAttribute('task_name'),
                'done' => (bool) $t->getAttribute('is_completed'),
            ])->all(),
            'photos' => $visit->photos
                ->map(fn ($p): ?string => is_string($path = $p->getAttribute('photo_path')) && $path !== '' ? Storage::disk('public')->url($path) : null)
                ->filter()->values()->all(),
        ];
    }

    /** @return array<string, float|null>|null */
    public static function reading(ServiceVisit $visit): ?array
    {
        $r = $visit->chemicalReading;

        return $r === null ? null : [
            'free_chlorine' => $r->free_chlorine,
            'ph' => $r->ph,
            'alkalinity' => $r->alkalinity,
            'calcium_hardness' => $r->calcium_hardness,
            'cyanuric_acid' => $r->cyanuric_acid,
            'salt' => $r->salt,
        ];
    }
}
