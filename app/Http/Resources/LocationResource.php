<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
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
            'client_id' => $this->client_id,
            'name' => $this->name,
            'street_address' => $this->street_address,
            'street_address_2' => $this->street_address_2,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'pool_type' => $this->pool_type,
            'water_type' => $this->water_type,
            'access_type' => $this->access_type,
            'setting' => $this->setting,
            'installation_type' => $this->installation_type,
            'pool_size' => $this->pool_size,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'assigned_technicians_count' => $this->whenCounted('assignedTechnicians'),
            'reports_count' => $this->whenCounted('reports'),
            'invoices_count' => $this->whenCounted('invoices'),
            
            // Loaded relationships
            'assigned_technicians' => TechnicianResource::collection($this->whenLoaded('assignedTechnicians')),
            'reports' => ReportResource::collection($this->whenLoaded('reports')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
} 