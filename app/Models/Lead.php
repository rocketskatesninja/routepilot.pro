<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A public-site lead for a tenant. `status` is set via controlled updates.
 *
 * @property string $name
 * @property string|null $email
 * @property string $source
 * @property string $status
 */
class Lead extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'phone', 'message', 'source'];
}
