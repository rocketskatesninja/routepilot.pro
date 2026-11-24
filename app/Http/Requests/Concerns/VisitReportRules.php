<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * The limits for a visit report's contents, shared by the two ways one is
 * written: the field app finishing a stop (CompleteVisitRequest) and the office
 * editing it afterwards (UpdateReportRequest). They must agree, or a report the
 * field app accepted can't be saved again from the Reports page.
 *
 * The field app shows the reading limits before sending
 * (resources/js/components/field/FieldVisit.vue `readingFields`).
 */
trait VisitReportRules
{
    /**
     * Water test readings. $prefix is 'reading.' when they arrive nested.
     *
     * @return array<string, list<string>>
     */
    protected function readingRules(string $prefix = ''): array
    {
        $max = [
            'free_chlorine' => 100,
            'total_chlorine' => 100,
            'ph' => 14,
            'alkalinity' => 1000,
            'calcium_hardness' => 5000,
            'cyanuric_acid' => 1000,
            'salt' => 20000,
            'tds' => 50000,
            'phosphates' => 10000,
            'water_temperature' => 150,
        ];

        $rules = [];
        foreach ($max as $field => $limit) {
            $rules[$prefix.$field] = ['nullable', 'numeric', 'min:0', 'max:'.$limit];
        }

        return $rules;
    }

    /**
     * Tasks done, chemicals added and parts used.
     *
     * @return array<string, list<string>>
     */
    protected function lineRules(): array
    {
        return [
            'tasks' => ['array'],
            'tasks.*.name' => ['nullable', 'string', 'max:255'],
            'tasks.*.done' => ['boolean'],
            'treatments' => ['array'],
            // A chemical picked from the company's list (another company's id is ignored);
            // name/unit are only sent for chemicals typed before the list existed.
            'treatments.*.chemical_id' => ['nullable', 'integer'],
            'treatments.*.name' => ['nullable', 'string', 'max:255'],
            'treatments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'treatments.*.unit' => ['nullable', 'string', 'max:50'],
            // Repair parts used, from the company's list (another company's id is ignored).
            'parts' => ['array'],
            'parts.*.part_id' => ['nullable', 'integer'],
            'parts.*.amount' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * The note the homeowner sees and the one only the company sees.
     *
     * @return array<string, list<string>>
     */
    protected function noteRules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'office_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
