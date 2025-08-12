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
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Invoice Profile -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6 border border-base-300">
                <div class="mb-6">
                    <div class="w-full h-80 rounded-lg overflow-hidden mb-4">
                        @if($invoice->location && $invoice->location->photos && count($invoice->location->photos) > 0)
                            <img src="{{ asset(Storage::url($invoice->location->photos[0])) }}" alt="Location Photo" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full bg-base-200 flex items-center justify-center rounded-lg">
                                <div class="text-center">
                                    <svg class="w-24 h-24 mx-auto text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <p class="text-base-content/50 mt-2">No photos available</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <div class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'error' : ($invoice->status === 'sent' ? 'info' : 'neutral')) }} badge-lg">
                            {{ ucfirst($invoice->status) }}
                        </div>
                    </div>
                    <h2 class="text-xl font-semibold text-base-content">Invoice {{ $invoice->invoice_number }}</h2>
                    <p class="text-base-content/70">{{ $invoice->service_date->format('M j, Y') }}</p>
                </div>

                <!-- Client Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Client</h3>
                    <div class="flex items-center space-x-3">
                        <div class="avatar">
                            <div class="mask mask-squircle w-10 h-10">
                                @if($invoice->client->profile_photo)
                                    <img src="{{ asset(Storage::url($invoice->client->profile_photo)) }}" alt="{{ $invoice->client->full_name }}">
                                @else
                                    <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-10 h-10">
                                        <span class="text-sm font-semibold">{{ substr($invoice->client->first_name, 0, 1) }}{{ substr($invoice->client->last_name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('clients.show', $invoice->client) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
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
                                <a href="{{ route('locations.show', $invoice->location) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ $invoice->location->nickname ?? 'Location' }}
                                </a>
                            @else
                                <span class="text-base-content font-medium">{{ $invoice->location->nickname ?? 'Location' }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Technician</span>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('technicians.show', $invoice->technician) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
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
                                <a href="{{ route('reports.show', $relatedReport) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
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
                    <a id="tab-details" onclick="showTab('details', event)" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out border-primary text-base-content focus:outline-none focus:border-primary-focus" style="margin-right: 1.5rem; cursor:pointer;">Invoice Details</a>
                    <a id="tab-payment" onclick="showTab('payment', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Payment History</a>
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
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName, event = null) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.style.display = 'none';
    });

    // Remove active classes and set inactive styles for all tab links
    document.querySelectorAll('.tabs a').forEach(link => {
        link.classList.remove('tab-active', 'border-primary', 'text-base-content', 'focus:border-primary-focus');
        link.classList.add('border-transparent', 'text-base-content/70');
    });

    // Show selected tab content
    const targetTab = document.getElementById(tabName + '-tab');
    if (targetTab) {
        targetTab.classList.remove('hidden');
        targetTab.style.display = 'block';
    }

    // Add active classes and styles to the clicked tab link
    const activeTab = document.getElementById('tab-' + tabName);
    if (activeTab) {
        activeTab.classList.add('tab-active', 'border-primary', 'text-base-content', 'focus:border-primary-focus');
        activeTab.classList.remove('border-transparent', 'text-base-content/70');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    showTab('details');
});
</script>
@endsection 