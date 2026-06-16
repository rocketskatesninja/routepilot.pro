<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A processed offline-field mutation, keyed by its client idempotency key, so a
 * replayed sync returns the original visit instead of duplicating it.
 *
 * @property string $idempotency_key
 * @property int $user_id
 * @property int|null $service_visit_id
 */
class FieldSyncKey extends Model
{
    /** @var list<string> */
    protected $fillable = ['idempotency_key', 'user_id', 'service_visit_id'];
}
