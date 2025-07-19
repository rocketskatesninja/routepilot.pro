@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Invoice {{ $invoice->invoice_number }}</h1>
            <p class="text-base-content/70 mt-2">{{ $invoice->client->full_name }} - {{ $invoice->service_date->format('M j, Y') }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Invoice
            </a>
            @endif
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download PDF
            </a>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Invoices
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Invoice Profile -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6 border border-base-300">
                <div class="text-center mb-6">
                    <div class="avatar mb-4">
                        <div class="mask mask-squircle w-24 h-24">
                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center text-3xl font-bold">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-xl font-semibold text-base-content">Invoice {{ $invoice->invoice_number }}</h2>
                    <p class="text-base-content/70">{{ $invoice->service_date->format('M j, Y') }}</p>
                </div>

                <!-- Status Badge -->
                <div class="flex justify-center mb-6">
                    <div class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'error' : ($invoice->status === 'sent' ? 'warning' : 'info')) }} badge-lg">
                        {{ ucfirst($invoice->status) }}
                    </div>
                </div>

                <!-- Client Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Client</h3>
                    <div class="flex items-center space-x-3">
                        <div class="avatar">
                            <div class="mask mask-squircle w-12 h-12">
                                @if($invoice->client->profile_photo)
                                    <img src="{{ Storage::url($invoice->client->profile_photo) }}" alt="{{ $invoice->client->full_name }}">
                                @else
                                    <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                        <span class="text-sm font-semibold">{{ substr($invoice->client->first_name, 0, 1) }}{{ substr($invoice->client->last_name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('clients.show', $invoice->client) }}" class="font-medium text-base-content hover:text-primary hover:underline transition-colors">
                                    {{ $invoice->client->full_name }}
                                </a>
                            @else
                                <div class="font-medium text-base-content">{{ $invoice->client->full_name }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Service Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Service Information</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Location</span>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('locations.show', $invoice->location) }}" class="text-base-content font-medium hover:text-primary hover:underline transition-colors">
                                    {{ $invoice->location->nickname ?? 'Location' }}
                                </a>
                            @else
                                <span class="text-base-content font-medium">{{ $invoice->location->nickname ?? 'Location' }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Technician</span>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('technicians.show', $invoice->technician) }}" class="text-base-content font-medium hover:text-primary hover:underline transition-colors">
                                    {{ $invoice->technician->full_name }}
                                </a>
                            @else
                                <span class="text-base-content font-medium">{{ $invoice->technician->full_name }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Date</span>
                            <span class="text-base-content font-medium">{{ $invoice->service_date->format('M j, Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Due Date</span>
                            <span class="text-base-content font-medium">{{ $invoice->due_date->format('M j, Y') }}</span>
                        </div>
                        @if($invoice->paid_at)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Paid Date</span>
                            <span class="text-base-content font-medium">{{ $invoice->paid_at->format('M j, Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Billing Summary -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Billing Summary</h3>
                    <div class="space-y-3">
                        @if($invoice->rate_per_visit > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Visit</span>
                            <span class="text-base-content font-medium">${{ number_format($invoice->rate_per_visit, 2) }}</span>
                        </div>
                        @endif
                        @if($invoice->chemicals_cost > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Chemicals</span>
                            <span class="text-base-content font-medium">${{ number_format($invoice->chemicals_cost, 2) }}</span>
                        </div>
                        @endif
                        @if($invoice->extras_cost > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Additional Services</span>
                            <span class="text-base-content font-medium">${{ number_format($invoice->extras_cost, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-base-300 pt-2">
                            <span class="text-base-content font-semibold">Total Amount</span>
                            <span class="text-base-content font-bold text-lg">${{ number_format($invoice->total_amount, 2) }}</span>
                        </div>
                        @if($totalClientBalance > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content font-semibold">Balance Due</span>
                            <span class="text-base-content font-bold text-lg text-error">${{ number_format($totalClientBalance, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Related Report -->
                @php
                    $relatedReport = \App\Models\Report::where('invoice_id', $invoice->id)->first();
                @endphp
                @if($relatedReport)
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Related Report</h3>
                    <div class="bg-base-200 rounded-lg p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Report #</span>
                                <a href="{{ route('reports.show', $relatedReport) }}" class="text-base-content font-medium hover:text-primary hover:underline transition-colors">
                                    {{ $relatedReport->id }}
                                </a>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Service Date</span>
                                <span class="text-base-content font-medium">{{ $relatedReport->service_date->format('M j, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Technician</span>
                                <span class="text-base-content font-medium">{{ $relatedReport->technician->full_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-stat-card 
                    title="Total Amount" 
                    value="${{ number_format($invoice->total_amount, 2) }}" 
                    color="primary"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'></path>"/>
                <x-stat-card 
                    title="Balance Due" 
                    value="${{ number_format($totalClientBalance, 2) }}" 
                    color="{{ $totalClientBalance > 0 ? 'error' : 'success' }}"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'></path>"/>
                <x-stat-card 
                    title="Days Overdue" 
                    value="{{ $invoice->due_date->isPast() && $invoice->status !== 'paid' ? $invoice->due_date->diffInDays(now()) : 0 }}" 
                    color="info"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'></path>"/>
            </div>

            <!-- Tabs -->
            <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                <div class="tabs tabs-boxed p-4">
                    <a class="tab tab-active" onclick="showTab('details')">Invoice Details</a>
                    <a class="tab" onclick="showTab('payment')">Payment History</a>
                    <a class="tab" onclick="showTab('actions')">Actions</a>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <!-- Details Tab -->
                    <div id="details-tab" class="tab-content">
                        <div class="space-y-6">
                            <!-- Service Details -->
                            <div>
                                <h3 class="text-lg font-semibold text-base-content mb-4">Service Details</h3>
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Details</th>
                                                <th class="text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Pool Service Visit</td>
                                                <td>
                                                    <strong>Location:</strong> {{ $invoice->location->nickname ?? 'Location' }}<br>
                                                    <strong>Technician:</strong> {{ $invoice->technician->full_name }}<br>
                                                    <strong>Service Date:</strong> {{ $invoice->service_date->format('M d, Y') }}
                                                </td>
                                                <td class="text-right font-bold">${{ number_format($invoice->rate_per_visit, 2) }}</td>
                                            </tr>
                                            
                                            @if($invoice->chemicals_cost > 0)
                                            <tr>
                                                <td>Chemicals & Supplies</td>
                                                <td>Pool chemicals and maintenance supplies</td>
                                                <td class="text-right font-bold">${{ number_format($invoice->chemicals_cost, 2) }}</td>
                                            </tr>
                                            @endif
                                            
                                            @if($invoice->extras_cost > 0)
                                            <tr>
                                                <td>Additional Services</td>
                                                <td>Extra services and materials</td>
                                                <td class="text-right font-bold">${{ number_format($invoice->extras_cost, 2) }}</td>
                                            </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Notes -->
                            @if($invoice->notes)
                            <div>
                                <h3 class="text-lg font-semibold text-base-content mb-4">Notes</h3>
                                <div class="bg-base-200 rounded-lg p-4">
                                    <p class="text-base-content">{{ $invoice->notes }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Payment History Tab -->
                    <div id="payment-tab" class="tab-content hidden">
                        <div class="space-y-6">
                            <h3 class="text-lg font-semibold text-base-content mb-4">Payment History</h3>
                            
                            @if($invoice->status === 'paid')
                            <div class="alert alert-success">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-bold">Invoice Paid</h4>
                                    <p>This invoice was paid on {{ $invoice->paid_at->format('M j, Y') }}</p>
                                </div>
                            </div>
                            @else
                            <div class="alert alert-warning">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <h4 class="font-bold">Payment Pending</h4>
                                    <p>This invoice is still awaiting payment. Total balance due: ${{ number_format($totalClientBalance, 2) }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Actions Tab -->
                    <div id="actions-tab" class="tab-content hidden">
                        <div class="space-y-6">
                            <h3 class="text-lg font-semibold text-base-content mb-4">Invoice Actions</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if($invoice->status !== 'paid')
                                <div class="card bg-base-200">
                                    <div class="card-body">
                                        <h4 class="card-title text-md">Record Payment</h4>
                                        <form action="{{ route('invoices.record-payment', $invoice) }}" method="POST" class="space-y-3">
                                            @csrf
                                            <div class="form-control">
                                                <label class="label">
                                                    <span class="label-text">Payment Amount</span>
                                                </label>
                                                <input type="number" name="payment_amount" step="0.01" min="0.01" max="{{ $totalClientBalance }}" 
                                                       value="{{ $totalClientBalance }}" class="input input-bordered" required>
                                            </div>
                                            <div class="form-control">
                                                <label class="label">
                                                    <span class="label-text">Payment Notes (Optional)</span>
                                                </label>
                                                <textarea name="payment_notes" class="textarea textarea-bordered" rows="2"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm w-full">Record Payment</button>
                                        </form>
                                    </div>
                                </div>
                                @endif
                                
                                <div class="card bg-base-200">
                                    <div class="card-body">
                                        <h4 class="card-title text-md">Quick Actions</h4>
                                        <div class="space-y-2">
                                            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-outline btn-sm w-full">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Download PDF
                                            </a>
                                            <a href="{{ route('invoices.pdf.view', $invoice) }}" target="_blank" class="btn btn-outline btn-sm w-full">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                View PDF
                                            </a>
                                            @if($invoice->status !== 'paid')
                                            <form action="{{ route('invoices.mark-paid', $invoice) }}" method="POST" class="inline w-full">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm w-full">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    Mark as Paid
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-error text-error-content">
                                <div class="card-body">
                                    <h4 class="card-title text-md">Danger Zone</h4>
                                    <p class="text-sm">Once you delete an invoice, there is no going back. Please be certain.</p>
                                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this invoice?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-error btn-sm">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete Invoice
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('tab-active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    
    // Add active class to clicked tab
    event.target.classList.add('tab-active');
}
</script>
@endsection 