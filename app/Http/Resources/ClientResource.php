<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'profile_photo' => $this->profile_photo ? asset('storage/' . $this->profile_photo) : null,
            'street_address' => $this->street_address,
            'street_address_2' => $this->street_address_2,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'notes_by_admin' => $this->notes_by_admin,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'locations_count' => $this->whenCounted('locations'),
            'invoices_count' => $this->whenCounted('invoices'),
            'reports_count' => $this->whenCounted('reports'),
            
            // Loaded relationships
            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'reports' => ReportResource::collection($this->whenLoaded('reports')),
        ];
    }
} 