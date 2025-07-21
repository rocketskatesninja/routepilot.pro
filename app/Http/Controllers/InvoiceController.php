<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use App\Http\Requests\InvoiceRequest;
use App\Traits\HasSearchable;
use App\Traits\HasSortable;
use App\Traits\HasExportable;
use App\Constants\AppConstants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    use HasSearchable, HasSortable, HasExportable;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if ($user->role === AppConstants::ROLE_ADMIN || $user->role === AppConstants::ROLE_TECHNICIAN) {
            $query = Invoice::with(['client', 'location', 'technician']);
        } elseif ($user->role === AppConstants::ROLE_CLIENT) {
            // For customers, get invoices through their client record (excluding drafts)
            $client = Client::where('email', $user->email)->first();
            if ($client) {
                $query = Invoice::where('client_id', $client->id)
                    ->where('status', '!=', 'draft')
                    ->with(['client', 'location', 'technician']);
            } else {
                $query = Invoice::where('id', 0); // Empty query if no client found
            }
        } else {
            abort(403);
        }

        // Apply search
        $searchTerm = $this->getSearchTerm($request);
        $this->applySearch($query, ['invoice_number', 'client.first_name', 'client.last_name', 'location.nickname'], $searchTerm);

        // Apply filters
        $this->applyFilters($query, $request, [
            'status' => ['type' => 'string'],
            'date_from' => ['column' => 'service_date', 'operator' => '>='],
            'date_to' => ['column' => 'service_date', 'operator' => '<='],
        ]);

        // Apply sorting
        $sortOptions = [
            'date_desc' => ['column' => 'service_date', 'direction' => 'desc'],
            'date_asc' => ['column' => 'service_date', 'direction' => 'asc'],
            'status' => ['column' => 'status', 'direction' => 'asc'],
            'amount' => ['column' => 'total_amount', 'direction' => 'desc'],
        ];
        $this->applySorting($query, $sortOptions, 'created_at');

        $invoices = $query->paginate(AppConstants::DEFAULT_PAGINATION);

        // Calculate current balance (sum of all unpaid balances, excluding drafts)
        $currentBalance = $query->where('status', '!=', 'paid')->where('status', '!=', 'draft')->sum('balance');

        return view('invoices.index', compact('invoices', 'currentBalance'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        
        // Only admins and technicians can create invoices
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot create invoices.');
        }
        
        $technicians = User::where('role', 'technician')->where('is_active', true)->get();
        $locationId = $request->get('location_id');
        
        // If location_id is provided, get the location and client data for auto-population
        $location = null;
        $client = null;
        if ($locationId) {
            $location = Location::with('client')->find($locationId);
            if ($location) {
                $client = $location->client;
            }
        }

        return view('invoices.create', compact('technicians', 'locationId', 'location', 'client'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceRequest $request)
    {
        $user = auth()->user();
        
        // Only admins and technicians can create invoices
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot create invoices.');
        }
        
        $validated = $request->validated();

        // Calculate total amount
        $total = $validated['rate_per_visit'];
        $total += $validated['chemicals_cost'] ?? 0;
        $total += $validated['extras_cost'] ?? 0;

        // Generate invoice number
        $lastInvoice = Invoice::orderBy('id', 'desc')->first();
        $invoiceNumber = $lastInvoice ? 'INV-' . str_pad($lastInvoice->id + 1, 6, '0', STR_PAD_LEFT) : 'INV-000001';

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'client_id' => $validated['client_id'],
            'location_id' => $validated['location_id'],
            'technician_id' => $validated['technician_id'],
            'service_date' => $validated['service_date'],
            'due_date' => $validated['due_date'],
            'rate_per_visit' => $validated['rate_per_visit'],
            'chemicals_cost' => $validated['chemicals_cost'] ?? 0,
            'chemicals_included' => $validated['chemicals_included'] ?? false,
            'extras_cost' => $validated['extras_cost'] ?? 0,
            'total_amount' => $total,
            'balance' => $total,
            'status' => 'sent',
            'notes' => $validated['notes'] ?? $validated['service_notes'] ?? null,
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = auth()->user();
        $invoice = Invoice::with(['client', 'location', 'technician'])->findOrFail($id);
        
        // Check if customer can view this invoice
        if ($user->role === AppConstants::ROLE_CLIENT) {
            $client = Client::where('email', $user->email)->first();
            if (!$client || $invoice->client_id !== $client->id) {
                abort(403, 'You can only view your own invoices.');
            }
            // Clients cannot view draft invoices
            if ($invoice->status === 'draft') {
                abort(403, 'Draft invoices are not visible to customers.');
            }
        }
        
        // Calculate total balance across all invoices for this client (excluding drafts)
        $totalClientBalance = Invoice::where('client_id', $invoice->client_id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'draft')
            ->sum('balance');
        
        return view('invoices.show', compact('invoice', 'totalClientBalance'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = auth()->user();
        
        // Only admins and technicians can edit invoices
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot edit invoices.');
        }
        
        $invoice = Invoice::findOrFail($id);
        $clients = Client::where('is_active', true)->get();
        $locations = Location::where('status', 'active')->get();
        $technicians = User::where('role', 'technician')->where('is_active', true)->get();

        return view('invoices.edit', compact('invoice', 'clients', 'locations', 'technicians'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InvoiceRequest $request, string $id)
    {
        $user = auth()->user();
        
        // Only admins and technicians can update invoices
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot update invoices.');
        }
        
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validated();

        // Calculate total amount
        $total = $validated['rate_per_visit'];
        if ($validated['chemicals_included']) {
            $total += $validated['chemicals_cost'] ?? 0;
        }
        $total += $validated['extras_cost'] ?? 0;

        // Update paid_at if status changed to paid
        if ($validated['status'] === 'paid' && $invoice->status !== 'paid') {
            $validated['paid_at'] = now();
        }

        $validated['total_amount'] = $total;
        $validated['balance'] = $total; // Reset balance when editing

        $invoice->update($validated);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = auth()->user();
        
        // Only admins and technicians can delete invoices
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot delete invoices.');
        }
        
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(string $id)
    {
        $user = auth()->user();
        
        // Only admins and technicians can mark invoices as paid
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot mark invoices as paid.');
        }
        
        $invoice = Invoice::findOrFail($id);
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'balance' => 0,
        ]);

        return redirect()->back()->with('success', 'Invoice marked as paid successfully.');
    }

    /**
     * Record partial payment
     */
    public function recordPayment(Request $request, string $id)
    {
        $user = auth()->user();
        
        // Only admins and technicians can record payments
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot record payments.');
        }
        
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($id);
        $paymentAmount = $request->payment_amount;

        if ($paymentAmount >= $invoice->balance) {
            // Full payment
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'balance' => 0,
            ]);
        } else {
            // Partial payment
            $invoice->update([
                'balance' => $invoice->balance - $paymentAmount,
            ]);
        }

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Export invoices to CSV
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        
        // Only admins and technicians can export invoices
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot export invoices.');
        }
        
        $query = Invoice::with(['client', 'location', 'technician']);

        // Apply search
        $searchTerm = $this->getSearchTerm($request);
        $this->applySearch($query, ['invoice_number', 'client.first_name', 'client.last_name'], $searchTerm);

        // Apply filters
        $this->applyFilters($query, $request, [
            'status' => ['type' => 'string'],
            'date_from' => ['column' => 'service_date', 'operator' => '>='],
            'date_to' => ['column' => 'service_date', 'operator' => '<='],
        ]);

        $invoices = $query->get();

        $headers = [
            'Invoice Number', 'Client', 'Location', 'Technician', 'Service Date', 
            'Due Date', 'Rate', 'Chemicals Cost', 'Extras Cost', 'Total Amount', 
            'Balance', 'Status', 'Created At'
        ];

        $data = $invoices->map(function ($invoice) {
            return [
                $invoice->invoice_number,
                $invoice->client->full_name,
                $invoice->location->nickname,
                $invoice->technician->full_name,
                $invoice->service_date->format('Y-m-d'),
                $invoice->due_date->format('Y-m-d'),
                $invoice->rate_per_visit,
                $invoice->chemicals_cost,
                $invoice->extras_cost,
                $invoice->total_amount,
                $invoice->balance,
                $invoice->status,
                $invoice->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->exportToCsv($data, $headers, 'invoices');
    }

    /**
     * Get invoice statistics
     */
    public function statistics()
    {
        $user = auth()->user();
        
        // Only admins and technicians can view statistics
        if ($user->role === AppConstants::ROLE_CLIENT) {
            abort(403, 'Customers cannot view invoice statistics.');
        }
        
        $stats = [
            'total_invoices' => Invoice::count(),
            'paid_invoices' => Invoice::where('status', 'paid')->count(),
            'pending_invoices' => Invoice::where('status', 'sent')->count(),
            'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
            'total_revenue' => Invoice::where('status', 'paid')->sum('total_amount'),
            'pending_revenue' => Invoice::where('status', '!=', 'paid')->sum('balance'),
            'overdue_revenue' => Invoice::where('status', 'overdue')->sum('balance'),
        ];

        return view('invoices.statistics', compact('stats'));
    }

    /**
     * Generate PDF for the specified invoice.
     */
    public function generatePdf(string $id)
    {
        $user = auth()->user();
        $invoice = Invoice::with(['client', 'location', 'technician'])->findOrFail($id);
        
        // Check if customer can download this invoice PDF
        if ($user->role === AppConstants::ROLE_CLIENT) {
            $client = Client::where('email', $user->email)->first();
            if (!$client || $invoice->client_id !== $client->id) {
                abort(403, 'You can only download your own invoice PDFs.');
            }
            // Clients cannot download draft invoice PDFs
            if ($invoice->status === 'draft') {
                abort(403, 'Draft invoices are not visible to customers.');
            }
        }
        
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        
        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    /**
     * Generate PDF for the specified invoice and display in browser.
     */
    public function viewPdf(string $id)
    {
        $user = auth()->user();
        $invoice = Invoice::with(['client', 'location', 'technician'])->findOrFail($id);
        
        // Check if customer can view this invoice PDF
        if ($user->role === AppConstants::ROLE_CLIENT) {
            $client = Client::where('email', $user->email)->first();
            if (!$client || $invoice->client_id !== $client->id) {
                abort(403, 'You can only view your own invoice PDFs.');
            }
            // Clients cannot view draft invoice PDFs
            if ($invoice->status === 'draft') {
                abort(403, 'Draft invoices are not visible to customers.');
            }
        }
        
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }
}
