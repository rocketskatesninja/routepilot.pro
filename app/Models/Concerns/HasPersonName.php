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

    /**
     * First name only — for customer-facing contexts (reminder emails, the
     * customer portal) where a technician's surname must never be exposed.
     * Falls back to an em dash rather than ever revealing the last name.
     */
    public function firstName(): string
    {
        $first = trim((string) $this->getAttribute('first_name'));

        return $first !== '' ? $first : '—';
    }
}
