<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A form only the company's admin may send. Requests that anyone else may send
 * (a tech, a customer) extend FormRequest and say who in their own authorize().
 */
abstract class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }
}
