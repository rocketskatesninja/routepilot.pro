<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Models\InventoryTransaction;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\ChemistryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Complete a route stop: create the ServiceVisit + its chemical reading
 * (LSI computed by the engine), treatments (deducting matching inventory),
 * task checklist, and mark the stop done — all in one transaction.
 */
class CompleteVisit
{
    /** Reading parameters the form may submit. */
    private const READING_KEYS = [
        'free_chlorine', 'total_chlorine', 'ph', 'alkalinity', 'calcium_hardness',
        'cyanuric_acid', 'salt', 'tds', 'phosphates', 'water_temperature',
    ];

    public function __construct(private ChemistryService $chem) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $photos
     */
    public function handle(RouteStop $stop, array $data, User $agent, array $photos = []): ServiceVisit
    {
        return DB::transaction(function () use ($stop, $data, $agent, $photos): ServiceVisit {
            $pool = $stop->pool;

            $visit = ServiceVisit::create([
                'route_stop_id' => $stop->id,
                'pool_id' => $stop->getAttribute('pool_id'),
                'agent_id' => $agent->id,
                'visited_at' => now(),
                'completed_at' => now(),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

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

            foreach ($this->rows($data['treatments'] ?? null) as $t) {
                $name = is_string($t['name'] ?? null) ? $t['name'] : '';
                if ($name === '') {
                    continue;
                }
                $amount = (float) ($t['amount'] ?? 0);
                $unit = is_string($t['unit'] ?? null) ? $t['unit'] : 'oz';
                $visit->treatments()->create(['chemical_name' => $name, 'amount' => $amount, 'unit' => $unit]);
                $this->deductInventory($name, $amount, $unit, $visit, $agent);
            }

            foreach ($photos as $photo) {
                $path = $photo->store('visit-photos/'.$visit->id, 'public');
                if ($path !== false) {
                    $visit->photos()->create(['photo_path' => $path]);
                }
            }

            $stop->update(['status' => 'completed', 'completed_at' => now()]);

            return $visit;
        });
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

    /** Deduct a treatment from matching inventory (same unit) and log it. */
    private function deductInventory(string $name, float $amount, string $unit, ServiceVisit $visit, User $agent): void
    {
        if ($amount <= 0) {
            return;
        }
        $item = ChemicalInventory::query()->where('chemical_name', $name)->where('unit', $unit)->where('is_active', true)->first();
        if ($item === null) {
            return;
        }

        $item->update(['current_stock' => max(0.0, (float) $item->current_stock - $amount)]);

        InventoryTransaction::create([
            'chemical_inventory_id' => $item->id,
            'type' => 'usage',
            'quantity' => -$amount,
            'service_visit_id' => $visit->id,
            'agent_id' => $agent->id,
            'pool_id' => $visit->getAttribute('pool_id'),
            'created_by' => $agent->id,
        ]);
    }
}
