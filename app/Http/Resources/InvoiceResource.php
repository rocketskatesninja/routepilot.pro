<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'client_id' => $this->client_id,
            'location_id' => $this->location_id,
            'technician_id' => $this->technician_id,
            'report_id' => $this->report_id,
            'invoice_date' => $this->invoice_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'location' => new LocationResource($this->whenLoaded('location')),
            'technician' => new TechnicianResource($this->whenLoaded('technician')),
            'report' => new ReportResource($this->whenLoaded('report')),
            
            // Invoice items
            'invoice_items' => InvoiceItemResource::collection($this->whenLoaded('invoiceItems')),
        ];
    }
} 