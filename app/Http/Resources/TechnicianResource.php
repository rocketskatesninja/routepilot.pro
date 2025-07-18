<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
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
            'hire_date' => $this->hire_date,
            'hourly_rate' => $this->hourly_rate,
            'specializations' => $this->specializations,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'assigned_locations_count' => $this->whenCounted('assignedLocations'),
            'reports_count' => $this->whenCounted('reports'),
            'invoices_count' => $this->whenCounted('invoices'),
            'activities_count' => $this->whenCounted('activities'),
            
            // Loaded relationships
            'assigned_locations' => LocationResource::collection($this->whenLoaded('assignedLocations')),
            'recent_reports' => ReportResource::collection($this->whenLoaded('reports')),
            'recent_invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'recent_activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
} 