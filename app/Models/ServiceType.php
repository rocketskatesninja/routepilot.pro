<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ServiceType — a reusable visit template: pricing, duration, the task
 * checklist, and which at-pool field-flow modules the visit shows.
 *
 * @property string $name
 * @property string|null $category
 * @property string $frequency
 * @property int $estimated_duration_minutes
 * @property string $price
 * @property bool $chemicals_included
 * @property string|null $description
 * @property array<int, string>|null $tasks
 * @property array<string, bool>|null $field_modules
 * @property bool $is_active
 */
class ServiceType extends Model
{
    /** @use HasFactory<ServiceTypeFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name', 'category', 'frequency', 'estimated_duration_minutes', 'price',
        'chemicals_included', 'description', 'tasks', 'field_modules', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tasks' => 'array',
            'field_modules' => 'array',
            'price' => 'decimal:2',
            'chemicals_included' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** The at-pool sections a visit of this type can show, in field-flow order. */
    public const MODULES = ['tasks' => 'Tasks', 'chemistry' => 'Chemistry', 'treatments' => 'Treatments', 'photos' => 'Photos'];

    /**
     * On/off flag per visit section. Anything never set counts as on, so older
     * service types keep showing everything.
     *
     * @return array<string, bool>
     */
    public function modules(): array
    {
        $flags = $this->field_modules ?? [];
        $out = [];
        foreach (array_keys(self::MODULES) as $key) {
            $out[$key] = ! array_key_exists($key, $flags) || (bool) $flags[$key];
        }

        return $out;
    }

    /** @return HasMany<ServiceSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ServiceSubscription::class);
    }
}
