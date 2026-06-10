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

    /** @return HasMany<ServiceSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ServiceSubscription::class);
    }
}
