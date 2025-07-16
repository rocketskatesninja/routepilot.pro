@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Invoice {{ $invoice->invoice_number }}</h1>
            <p class="text-base-content/70 mt-2">{{ $invoice->client->full_name }} • {{ $invoice->service_date->format('M d, Y') }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download PDF
            </a>
            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Invoice
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
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <!-- Invoice Icon -->
                <div class="mb-6">
                    <div class="w-full h-80 bg-base-200 rounded-lg flex items-center justify-center">
                        <div class="text-center">
                            <div class="bg-primary text-primary-content rounded-full w-32 h-32 flex items-center justify-center text-6xl font-bold mb-4">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <p class="text-base-content/50">Invoice {{ $invoice->invoice_number }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-center mb-6">
                    <h2 class="text-xl font-semibold text-base-content">Invoice {{ $invoice->invoice_number }}</h2>
                    <p class="text-base-content/70">{{ $invoice->service_date->format('M d, Y') }}</p>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <div class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'error' : 'info') }}">
                        {{ ucfirst($invoice->status) }}
                    </div>
                    @if($invoice->isOverdue())
                        <div class="badge badge-error">Overdue</div>
                    @endif
                    @if($invoice->chemicals_included)
                        <div class="badge badge-info">Chemicals Included</div>
                    @endif
                </div>

                <!-- Invoice Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Invoice Information</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Date:</span>
                            <span class="text-base-content">{{ $invoice->service_date->format('M d, Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Due Date:</span>
                            <span class="text-base-content">{{ $invoice->due_date->format('M d, Y') }}</span>
                        </div>
                        @if($invoice->paid_at)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Paid Date:</span>
                            <span class="text-base-content">{{ $invoice->paid_at->format('M d, Y') }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Created:</span>
                            <span class="text-base-content">{{ $invoice->created_at->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Payment Information</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Total Amount:</span>
                            <span class="text-lg font-bold text-primary">${{ number_format($invoice->total_amount, 2) }}</span>
                        </div>
                        @if($invoice->status !== 'paid')
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Balance Due:</span>
                            <span class="text-lg font-bold text-error">${{ number_format($invoice->balance, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Rate per Visit:</span>
                            <span class="text-base-content">${{ number_format($invoice->rate_per_visit, 2) }}</span>
                        </div>
                        @if($invoice->chemicals_cost > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Chemicals:</span>
                            <span class="text-base-content">${{ number_format($invoice->chemicals_cost, 2) }}</span>
                        </div>
                        @endif
                        @if($invoice->extras_cost > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Extras:</span>
                            <span class="text-base-content">${{ number_format($invoice->extras_cost, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Related Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Related Information</h3>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <a href="{{ route('clients.show', $invoice->client) }}" class="text-base-content hover:text-primary hover:underline">
                                {{ $invoice->client->full_name }}
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <a href="{{ route('locations.show', $invoice->location) }}" class="text-base-content hover:text-primary hover:underline">
                                {{ $invoice->location->nickname ?? 'Location' }}
                            </a>
                        </div>
                        @if($invoice->technician)
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <a href="{{ route('technicians.show', $invoice->technician) }}" class="text-base-content hover:text-primary hover:underline">
                                {{ $invoice->technician->full_name }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Payment Form -->
                @if($invoice->status !== 'paid')
                <div class="space-y-4 mt-6">
                    <h3 class="text-lg font-semibold text-base-content">Record Payment</h3>
                    <form action="{{ route('invoices.record-payment', $invoice) }}" method="POST" class="space-y-3">
                        @csrf
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Payment Amount</span>
                            </label>
                            <input type="number" name="payment_amount" step="0.01" min="0.01" max="{{ $invoice->balance }}" 
                                   value="{{ $invoice->balance }}" class="input input-bordered" required>
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
                @endif
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-stat-card 
                    title="Total Amount" 
                    :value="'$' . number_format($invoice->total_amount, 2)" 
                    color="primary"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'></path>"
                />
                <x-stat-card 
                    title="Balance Due" 
                    :value="'$' . number_format($invoice->balance, 2)" 
                    color="error"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'></path>"
                />
                <x-stat-card 
                    title="Days Overdue" 
                    :value="$invoice->isOverdue() ? $invoice->due_date->diffInDays(now()) : 0" 
                    color="warning"
                    icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
                />
            </div>

            <!-- Tabs -->
            <div class="bg-base-100 shadow-xl rounded-lg">
                <div class="tabs tabs-boxed p-4">
                    <a class="tab tab-active" onclick="showTab('details', event)">Invoice Details</a>
                    <a class="tab" onclick="showTab('client', event)">Client Information</a>
                    <a class="tab" onclick="showTab('location', event)">Location Information</a>
                    <a class="tab" onclick="showTab('history', event)">Payment History</a>
                </div>

                <div class="p-6">
                    <!-- Invoice Details Tab -->
                    <div id="details-tab" class="tab-content" style="display: block !important;">
                        <div class="space-y-6">
                            <!-- Service Details -->
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h3 class="card-title text-lg">Service Details</h3>
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
                                                        <strong>Technician:</strong> {{ $invoice->technician->full_name ?? 'N/A' }}<br>
                                                        <strong>Service Date:</strong> {{ $invoice->service_date->format('M d, Y') }}
                                                    </td>
                                                    <td class="text-right font-bold">${{ number_format($invoice->rate_per_visit, 2) }}</td>
                                                </tr>
                                                
                                                @if($invoice->chemicals_included && $invoice->chemicals_cost > 0)
                                                <tr>
                                                    <td>Chemicals & Supplies</td>
                                                    <td>Pool chemicals and maintenance supplies included in service</td>
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
                            </div>

                            <!-- Notes -->
                            @if($invoice->notes)
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h3 class="card-title text-lg">Notes</h3>
                                    <p class="text-base-content">{{ $invoice->notes }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Client Information Tab -->
                    <div id="client-tab" class="tab-content hidden">
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Client Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-3">
                                        <div>
                                            <span class="font-semibold text-base-content">Name:</span>
                                            <div class="mt-1">
                                                <a href="{{ route('clients.show', $invoice->client) }}" class="text-primary hover:underline">
                                                    {{ $invoice->client->full_name }}
                                                </a>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="font-semibold text-base-content">Email:</span>
                                            <div class="mt-1">
                                                <a href="mailto:{{ $invoice->client->email }}" class="text-primary hover:underline">
                                                    {{ $invoice->client->email }}
                                                </a>
                                            </div>
                                        </div>
                                        @if($invoice->client->phone)
                                        <div>
                                            <span class="font-semibold text-base-content">Phone:</span>
                                            <div class="mt-1">
                                                <a href="tel:{{ $invoice->client->phone }}" class="text-primary hover:underline">
                                                    {{ $invoice->client->phone }}
                                                </a>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="space-y-3">
                                        @if($invoice->client->street_address)
                                        <div>
                                            <span class="font-semibold text-base-content">Address:</span>
                                            <div class="mt-1">
                                                <a href="https://maps.google.com/?q={{ urlencode($invoice->client->street_address . ', ' . $invoice->client->city . ', ' . $invoice->client->state . ' ' . $invoice->client->zip_code) }}" 
                                                   target="_blank" 
                                                   class="text-primary hover:underline">
                                                    {{ $invoice->client->street_address }}<br>
                                                    @if($invoice->client->street_address_2)
                                                        {{ $invoice->client->street_address_2 }}<br>
                                                    @endif
                                                    {{ $invoice->client->city }}, {{ $invoice->client->state }} {{ $invoice->client->zip_code }}
                                                </a>
                                            </div>
                                        </div>
                                        @endif
                                        <div>
                                            <span class="font-semibold text-base-content">Status:</span>
                                            <div class="mt-1">
                                                <div class="badge badge-{{ $invoice->client->is_active ? 'success' : 'error' }}">
                                                    {{ $invoice->client->is_active ? 'Active' : 'Inactive' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Information Tab -->
                    <div id="location-tab" class="tab-content hidden">
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Location Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-3">
                                        <div>
                                            <span class="font-semibold text-base-content">Location:</span>
                                            <div class="mt-1">
                                                <a href="{{ route('locations.show', $invoice->location) }}" class="text-primary hover:underline">
                                                    {{ $invoice->location->nickname ?? 'Location' }}
                                                </a>
                                            </div>
                                        </div>
                                        @if($invoice->location->pool_type)
                                        <div>
                                            <span class="font-semibold text-base-content">Pool Type:</span>
                                            <div class="mt-1">
                                                <div class="badge badge-info">{{ ucfirst($invoice->location->pool_type) }}</div>
                                            </div>
                                        </div>
                                        @endif
                                        @if($invoice->location->water_type)
                                        <div>
                                            <span class="font-semibold text-base-content">Water Type:</span>
                                            <div class="mt-1">
                                                <div class="badge badge-info">{{ ucfirst($invoice->location->water_type) }}</div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="space-y-3">
                                        @if($invoice->location->street_address)
                                        <div>
                                            <span class="font-semibold text-base-content">Address:</span>
                                            <div class="mt-1">
                                                <a href="https://maps.google.com/?q={{ urlencode($invoice->location->street_address . ', ' . $invoice->location->city . ', ' . $invoice->location->state . ' ' . $invoice->location->zip_code) }}" 
                                                   target="_blank" 
                                                   class="text-primary hover:underline">
                                                    {{ $invoice->location->street_address }}<br>
                                                    @if($invoice->location->street_address_2)
                                                        {{ $invoice->location->street_address_2 }}<br>
                                                    @endif
                                                    {{ $invoice->location->city }}, {{ $invoice->location->state }} {{ $invoice->location->zip_code }}
                                                </a>
                                            </div>
                                        </div>
                                        @endif
                                        <div>
                                            <span class="font-semibold text-base-content">Status:</span>
                                            <div class="mt-1">
                                                <div class="badge badge-{{ $invoice->location->status == 'active' ? 'success' : 'error' }}">
                                                    {{ ucfirst($invoice->location->status) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History Tab -->
                    <div id="history-tab" class="tab-content hidden">
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Payment History</h3>
                                @if($invoice->status === 'paid' && $invoice->paid_at)
                                    <div class="alert alert-success">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <h3 class="font-bold">Invoice Paid</h3>
                                            <div class="text-sm">Paid on {{ $invoice->paid_at->format('M d, Y') }}</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="alert alert-warning">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                        <div>
                                            <h3 class="font-bold">Payment Pending</h3>
                                            <div class="text-sm">Due on {{ $invoice->due_date->format('M d, Y') }}</div>
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="mt-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold">Original Amount:</span>
                                        <span class="font-bold">${{ number_format($invoice->total_amount, 2) }}</span>
                                    </div>
                                    @if($invoice->status !== 'paid')
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold">Amount Paid:</span>
                                        <span class="font-bold">${{ number_format($invoice->total_amount - $invoice->balance, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold">Balance Due:</span>
                                        <span class="font-bold text-error">${{ number_format($invoice->balance, 2) }}</span>
                                    </div>
                                    @endif
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
function showTab(tabName, event) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.style.display = 'none';
    });
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.classList.remove('tab-active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.style.display = 'block';
    }
    
    // Add active class to clicked tab
    if (event && event.target) {
        event.target.classList.add('tab-active');
    }
}
</script>
@endsection 