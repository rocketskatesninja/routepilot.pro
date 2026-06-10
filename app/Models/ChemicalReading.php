<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChemicalReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChemicalReading — the water-test values captured during a visit.
 * One per visit; the chemistry engine analyzes these against the
 * pool's target ranges and writes back the computed LSI score.
 */
class ChemicalReading extends Model
{
    /** @use HasFactory<ChemicalReadingFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'service_visit_id', 'free_chlorine', 'total_chlorine', 'ph',
        'alkalinity', 'calcium_hardness', 'cyanuric_acid', 'salt', 'tds',
        'phosphates', 'water_temperature', 'lsi_score',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'free_chlorine' => 'float',
            'total_chlorine' => 'float',
            'ph' => 'float',
            'alkalinity' => 'float',
            'calcium_hardness' => 'float',
            'cyanuric_acid' => 'float',
            'salt' => 'float',
            'tds' => 'float',
            'phosphates' => 'float',
            'water_temperature' => 'float',
            'lsi_score' => 'float',
        ];
    }

    /** @return BelongsTo<ServiceVisit, $this> */
    public function serviceVisit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class);
    }
}
