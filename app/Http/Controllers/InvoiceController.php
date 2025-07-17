<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['client', 'location', 'technician']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('first_name', 'like', "%{$search}%")
                                 ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('location', function ($locationQuery) use ($search) {
                      $locationQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('service_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('service_date', '<=', $request->date_to);
        }



        // Sort functionality
        $sortBy = $request->get('sort_by', 'date_desc');
        
        switch ($sortBy) {
            case 'date_desc':
                $query->orderBy('service_date', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('service_date', 'asc');
                break;
            case 'status':
                $query->orderBy('status', 'asc');
                break;
            case 'amount':
                $query->orderBy('total_amount', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $invoices = $query->paginate(15);

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'technician_id' => 'required|exists:users,id',
            'service_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:service_date',
            'rate_per_visit' => 'required|numeric|min:0',
            'chemicals_cost' => 'nullable|numeric|min:0',
            'chemicals_included' => 'boolean',
            'extras_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Calculate total amount
        $total = $validated['rate_per_visit'];
        if ($validated['chemicals_included']) {
            $total += $validated['chemicals_cost'] ?? 0;
        }
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
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $invoice = Invoice::with(['client', 'location', 'technician'])->findOrFail($id);
        
        return view('invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $invoice = Invoice::findOrFail($id);
        $clients = Client::where('is_active', true)->get();
        $locations = Location::where('status', 'active')->get();
        $technicians = User::where('role', 'technician')->where('is_active', true)->get();

        return view('invoices.edit', compact('invoice', 'clients', 'locations', 'technicians'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'technician_id' => 'required|exists:users,id',
            'service_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:service_date',
            'rate_per_visit' => 'required|numeric|min:0',
            'chemicals_cost' => 'nullable|numeric|min:0',
            'chemicals_included' => 'boolean',
            'extras_cost' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
            'notes' => 'nullable|string',
        ]);

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
        $query = Invoice::with(['client', 'location', 'technician']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('first_name', 'like', "%{$search}%")
                                 ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('service_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('service_date', '<=', $request->date_to);
        }

        $invoices = $query->get();

        $filename = 'invoices_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function() use ($invoices) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Invoice Number', 'Client', 'Location', 'Technician', 'Service Date', 
                'Due Date', 'Rate', 'Chemicals Cost', 'Extras Cost', 'Total Amount', 
                'Balance', 'Status', 'Created At'
            ]);

            // CSV data
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->client->full_name,
                    $invoice->location->name,
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
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get invoice statistics
     */
    public function statistics()
    {
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
        $invoice = Invoice::with(['client', 'location', 'technician'])->findOrFail($id);
        
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        
        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    /**
     * Generate PDF for the specified invoice and display in browser.
     */
    public function viewPdf(string $id)
    {
        $invoice = Invoice::with(['client', 'location', 'technician'])->findOrFail($id);
        
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }
}
