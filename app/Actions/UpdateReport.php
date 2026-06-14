<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceVisit;
use App\Services\ChemistryService;
use Illuminate\Support\Facades\DB;

/**
 * Edit a completed visit report: its date + notes, the chemistry reading (LSI
 * recomputed from the edited values), and the treatment + task lists (replaced
 * wholesale, mirroring CompleteVisit). One transaction.
 */
class UpdateReport
{
    public function __construct(private readonly ChemistryService $chem) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ServiceVisit $visit, array $data): void
    {
        DB::transaction(function () use ($visit, $data): void {
            $visit->update([
                'completed_at' => $data['completed_on'] ?? $visit->getAttribute('completed_at'),
                'notes' => $data['notes'] ?? null,
            ]);

            /** @var array<string, mixed> $reading */
            $reading = is_array($data['reading'] ?? null) ? $data['reading'] : [];
            $visit->chemicalReading()->delete();
            if (array_filter($reading, fn ($v): bool => $v !== null && $v !== '') !== []) {
                $lsi = $this->chem->calculateLSI([
                    'temperature' => $reading['water_temperature'] ?? null,
                    'ph' => $reading['ph'] ?? null,
                    'alkalinity' => $reading['alkalinity'] ?? null,
                    'calcium_hardness' => $reading['calcium_hardness'] ?? null,
                    'salt' => $reading['salt'] ?? null,
                ]);
                $visit->chemicalReading()->create([
                    'free_chlorine' => $reading['free_chlorine'] ?? null,
                    'total_chlorine' => $reading['total_chlorine'] ?? null,
                    'ph' => $reading['ph'] ?? null,
                    'alkalinity' => $reading['alkalinity'] ?? null,
                    'calcium_hardness' => $reading['calcium_hardness'] ?? null,
                    'cyanuric_acid' => $reading['cyanuric_acid'] ?? null,
                    'salt' => $reading['salt'] ?? null,
                    'water_temperature' => $reading['water_temperature'] ?? null,
                    'lsi_score' => $lsi,
                ]);
            }

            $visit->treatments()->delete();
            foreach (is_array($data['treatments'] ?? null) ? $data['treatments'] : [] as $t) {
                if (! is_array($t) || ($t['name'] ?? '') === '') {
                    continue;
                }
                $visit->treatments()->create([
                    'chemical_name' => $t['name'],
                    'amount' => (float) ($t['amount'] ?? 0),
                    'unit' => $t['unit'] ?? '',
                ]);
            }

            $visit->tasks()->delete();
            foreach (is_array($data['tasks'] ?? null) ? $data['tasks'] : [] as $task) {
                if (! is_array($task) || ($task['name'] ?? '') === '') {
                    continue;
                }
                $visit->tasks()->create(['task_name' => $task['name'], 'is_completed' => (bool) ($task['done'] ?? false)]);
            }
        });
    }
}
