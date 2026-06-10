<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * A person-like model (Customer / User) with first_name + last_name.
 * Centralizes full-name display so call sites don't re-glue the parts.
 */
trait HasPersonName
{
    /** Full display name, or an em dash when both parts are blank. */
    public function displayName(): string
    {
        $name = trim((string) $this->getAttribute('first_name').' '.(string) $this->getAttribute('last_name'));

        return $name !== '' ? $name : '—';
    }
}
