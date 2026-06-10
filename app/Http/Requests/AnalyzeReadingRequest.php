<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'free_chlorine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_chlorine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'alkalinity' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'calcium_hardness' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'cyanuric_acid' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'salt' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'tds' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'phosphates' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'water_temperature' => ['nullable', 'numeric', 'min:0', 'max:120'],
        ];
    }
}
