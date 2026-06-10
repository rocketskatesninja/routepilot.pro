<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A processed Stripe webhook event (platform-level — not tenant-scoped).
 */
class StripeEvent extends Model
{
    /** @var list<string> */
    protected $fillable = ['event_id', 'type'];
}
