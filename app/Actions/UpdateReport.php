<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Services\ChemistryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Edit a completed visit report: its date + notes, the chemistry reading (LSI
 * recomputed from the edited values), and the chemicals + task lists (replaced
 * wholesale, mirroring CompleteVisit; RecordTreatments keeps stock in step).
 * One transaction.
 */
class UpdateReport
{
    public function __construct(
        private readonly ChemistryService $chem,
        private readonly RecordTreatments $treatments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ServiceVisit $visit, array $data, int $byUserId): void
    {
        DB::transaction(function () use ($visit, $data, $byUserId): void {
            $visit->update([
                'completed_at' => $this->completedAt($visit, $data['completed_on'] ?? null),
                'notes' => $data['notes'] ?? null,
                'office_notes' => $data['office_notes'] ?? null,
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

            $rows = is_array($data['treatments'] ?? null) ? array_values(array_filter($data['treatments'], 'is_array')) : [];
            $this->treatments->handle($visit, $rows, $byUserId);

            $visit->tasks()->delete();
            foreach (is_array($data['tasks'] ?? null) ? $data['tasks'] : [] as $task) {
                if (! is_array($task) || ($task['name'] ?? '') === '') {
                    continue;
                }
                $visit->tasks()->create(['task_name' => $task['name'], 'is_completed' => (bool) ($task['done'] ?? false)]);
            }
        });
    }

    /**
     * When the report says the visit was finished. The form edits a date in the
     * company's own day; moving it keeps the visit's time of day there (stored in
     * UTC), and leaving it alone keeps the time exactly as it was. A bare date read
     * as midnight UTC would land on the previous evening for companies west of UTC.
     */
    private function completedAt(ServiceVisit $visit, mixed $date): ?Carbon
    {
        $at = $visit->completed_at;
        if (! is_string($date) || $date === '' || ($at !== null && Tenant::localDate($at) === $date)) {
            return $at;
        }
        $time = $at !== null ? Tenant::local($at) : Tenant::localToday()->setTime(12, 0);

        return Carbon::parse($date, $time->getTimezone())->setTimeFrom($time)->utc();
    }
}
