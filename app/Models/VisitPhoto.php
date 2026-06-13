<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VisitPhoto — a photo captured during a visit (before/after, issues).
 */
class VisitPhoto extends Model
{
    /** @var list<string> */
    protected $fillable = ['service_visit_id', 'photo_path', 'caption', 'is_showcase'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_showcase' => 'boolean'];
    }

    /** @return BelongsTo<ServiceVisit, $this> */
    public function serviceVisit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class);
    }
}
