<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ChemistryService;
use App\Services\PhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Complete (or re-complete) a route stop: upsert the ServiceVisit + its
 * chemical reading (LSI computed by the engine), the chemicals used (see
 * RecordTreatments), task checklist, any work order the tech raised, and mark
 * the stop done — all in one transaction.
 *
 * Re-submitting an already-completed stop UPDATES the existing visit rather than
 * creating a second one: the reading/tasks/chemicals are replaced and any newly
 * uploaded photos appended. What the chemicals took from stock is put back before
 * they're recorded again, so editing never double-deducts.
 */
class CompleteVisit
{
    /** Reading parameters the form may submit. */
    private const READING_KEYS = [
        'free_chlorine', 'total_chlorine', 'ph', 'alkalinity', 'calcium_hardness',
        'cyanuric_acid', 'salt', 'tds', 'phosphates', 'water_temperature',
    ];

    public function __construct(private ChemistryService $chem, private PhotoService $photos, private BillingService $billing, private RecordTreatments $treatments, private CreateWorkOrder $workOrders) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $photos
     */
    public function handle(RouteStop $stop, array $data, User $agent, array $photos = []): ServiceVisit
    {
        $visit = DB::transaction(function () use ($stop, $data, $agent, $photos): ServiceVisit {
            // One visit per stop: lock the stop so two syncs of the same visit (a retry
            // racing its original) queue up, and the second edits what the first made.
            RouteStop::query()->whereKey($stop->id)->lockForUpdate()->first();
            $visit = $stop->serviceVisit()->first();

            $gps = [
                'completed_lat' => $data['completed_lat'] ?? null,
                'completed_lng' => $data['completed_lng'] ?? null,
            ];

            if ($visit === null) {
                $visit = ServiceVisit::create([
                    'route_stop_id' => $stop->id,
                    'pool_id' => $stop->getAttribute('pool_id'),
                    'service_subscription_id' => $stop->getAttribute('service_subscription_id'),
                    'agent_id' => $agent->id,
                    'visited_at' => now(),
                    'completed_at' => now(),
                    'status' => 'completed',
                    'notes' => $data['notes'] ?? null,
                    'office_notes' => $data['office_notes'] ?? null,
                    ...$gps,
                ]);
            } else {
                // Editing an existing visit: wipe replaceable children before re-applying
                // the fresh submission (RecordTreatments replaces the chemicals).
                $visit->tasks()->delete();
                $visit->chemicalReading()->delete();
                $visit->update([
                    'service_subscription_id' => $visit->getAttribute('service_subscription_id') ?? $stop->getAttribute('service_subscription_id'),
                    'completed_at' => now(),
                    'status' => 'completed',
                    'notes' => $data['notes'] ?? null,
                    'office_notes' => $data['office_notes'] ?? null,
                    ...$gps,
                ]);
            }

            $reading = $this->readingValues($data);
            if ($reading !== []) {
                $lsi = $this->chem->calculateLSI([
                    'temperature' => $reading['water_temperature'] ?? null,
                    'ph' => $reading['ph'] ?? null,
                    'alkalinity' => $reading['alkalinity'] ?? null,
                    'calcium_hardness' => $reading['calcium_hardness'] ?? null,
                    'salt' => $reading['salt'] ?? null,
                    'cyanuric_acid' => $reading['cyanuric_acid'] ?? null,
                ]);
                $visit->chemicalReading()->create([...$reading, 'lsi_score' => $lsi]);
            }

            foreach ($this->rows($data['tasks'] ?? null) as $task) {
                if (is_string($task['name'] ?? null) && $task['name'] !== '') {
                    $visit->tasks()->create(['task_name' => $task['name'], 'is_completed' => (bool) ($task['done'] ?? false)]);
                }
            }

            $this->treatments->handle($visit, $this->rows($data['treatments'] ?? null), $agent->id);

            foreach ($photos as $photo) {
                $visit->photos()->create(['photo_path' => $this->photos->store($photo, 'visit-photos/'.$visit->id)]);
            }

            // A work order the tech raised here: one per visit, so a second sync never adds another.
            $workOrder = $this->workOrderFrom($data);
            if ($workOrder !== null && ! $visit->workOrder()->exists()) {
                $this->workOrders->handle([
                    ...$workOrder,
                    'urgent' => (bool) ($data['work_order_urgent'] ?? false),
                    'price' => is_numeric($data['work_order_price'] ?? null) ? (float) $data['work_order_price'] : null,
                    'pool_id' => $visit->getAttribute('pool_id'),
                    'service_visit_id' => $visit->id,
                ], $agent);
            }

            // Who else gets the report, remembered for the pool's next visits (blank clears it).
            if (array_key_exists('report_cc_email', $data)) {
                $visit->pool?->update(['report_cc_email' => $data['report_cc_email'] ?: null]);
            }

            $stop->update(['status' => 'completed', 'completed_at' => now()]);
            $this->billing->chargeVisit($visit); // once, at its plan's price right now

            return $visit;
        });

        // Live-refresh the office schedule board (and any other dispatcher).
        $route = $stop->route()->first();
        if ($route !== null) {
            event(RouteUpdated::for($route));
        }

        return $visit;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    private function readingValues(array $data): array
    {
        $out = [];
        foreach (self::READING_KEYS as $key) {
            $value = $data[$key] ?? null;
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $out[$key] = (float) $value;
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $value
     * @return list<array<string, mixed>>
     */
    private function rows($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * The work order typed on the visit: its title and details, or the one box an older copy
     * of the app sends, split by CreateWorkOrder::fromText. Details with no title lend it their
     * first line, so nothing a tech wrote is dropped.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, details: string|null}|null
     */
    private function workOrderFrom(array $data): ?array
    {
        $text = fn (string $key): string => trim(is_string($data[$key] ?? null) ? $data[$key] : '');
        $title = $text('work_order_title');
        $details = $text('work_order_details') ?: $text('work_order');
        if ($title === '') {
            return $details !== '' ? CreateWorkOrder::fromText($details) : null;
        }

        return ['title' => $title, 'details' => $details ?: null];
    }
}
