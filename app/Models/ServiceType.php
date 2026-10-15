<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ServiceType — one entry in a company's service catalog: what it is, how it's
 * sold (recurring on a schedule, or a one-time job), what it costs, what the
 * tech does on site, and whether customers can request it in the portal and on
 * the website. A pool's plan is built from a service.
 *
 * @property string $name
 * @property string|null $description
 * @property string $frequency how it's sold: weekly / biweekly / monthly / one_time
 * @property string $price per visit (recurring) or for the job (one-time)
 * @property int $estimated_duration_minutes
 * @property array<int, string>|null $tasks
 * @property array<string, bool>|null $field_modules
 * @property bool $is_active
 * @property bool $requestable
 */
class ServiceType extends Model
{
    /** @use HasFactory<ServiceTypeFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name', 'description', 'frequency', 'price', 'estimated_duration_minutes',
        'tasks', 'field_modules', 'is_active', 'requestable',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tasks' => 'array',
            'field_modules' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'requestable' => 'boolean',
        ];
    }

    /** The at-pool sections a visit of this type can show, in field-flow order. */
    public const MODULES = ['tasks' => 'Checklist', 'chemistry' => 'Water test', 'treatments' => 'Treatments', 'photos' => 'Photos'];

    /** "$50.00 per visit · weekly" / "$150.00 one-time job" — how it reads to a customer. */
    public function offerLabel(): string
    {
        $price = '$'.number_format((float) $this->price, 2);

        return $this->frequency === 'one_time'
            ? $price.' one-time job'
            : $price.' per visit · '.strtolower(ServiceSubscription::FREQUENCIES[$this->frequency] ?? $this->frequency);
    }

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
