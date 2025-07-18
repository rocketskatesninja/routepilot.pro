<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'client_id' => $this->client_id,
            'location_id' => $this->location_id,
            'technician_id' => $this->technician_id,
            'report_date' => $this->report_date?->toISOString(),
            'report_type' => $this->report_type,
            'status' => $this->status,
            'water_quality' => $this->water_quality,
            'chemical_levels' => $this->chemical_levels,
            'equipment_status' => $this->equipment_status,
            'maintenance_performed' => $this->maintenance_performed,
            'issues_found' => $this->issues_found,
            'recommendations' => $this->recommendations,
            'photos' => $this->photos,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'location' => new LocationResource($this->whenLoaded('location')),
            'technician' => new TechnicianResource($this->whenLoaded('technician')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            
            // Report items
            'report_items' => ReportItemResource::collection($this->whenLoaded('reportItems')),
        ];
    }
} 