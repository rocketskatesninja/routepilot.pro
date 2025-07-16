@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Service Report</h1>
            <p class="text-base-content/70 mt-2">{{ $report->service_date->format('M j, Y') }} - {{ $report->location->nickname ?? $report->location->name }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('reports.edit', $report) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Report
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Reports
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Report Summary -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <div class="text-center mb-6">
                    <div class="avatar mb-4">
                        <div class="mask mask-squircle w-24 h-24">
                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center text-3xl font-bold">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-xl font-semibold text-base-content">Service Report</h2>
                    <p class="text-base-content/70">{{ $report->service_date->format('M j, Y') }}</p>
                </div>

                <!-- Technician Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Technician</h3>
                    <div class="flex items-center space-x-3">
                        <div class="avatar">
                            <div class="mask mask-squircle w-12 h-12">
                                @if($report->technician->profile_photo)
                                    <img src="{{ Storage::url($report->technician->profile_photo) }}" alt="{{ $report->technician->full_name }}">
                                @else
                                    <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                        <span class="text-sm font-semibold">{{ substr($report->technician->first_name, 0, 1) }}{{ substr($report->technician->last_name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="font-medium text-base-content">{{ $report->technician->full_name }}</div>
                        </div>
                    </div>
                </div>

                <!-- Service Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Service Information</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Client</span>
                            <span class="text-base-content font-medium">{{ $report->client->full_name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Location</span>
                            <span class="text-base-content font-medium">{{ $report->location->nickname ?? $report->location->name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Date</span>
                            <span class="text-base-content font-medium">{{ $report->service_date->format('M j, Y') }}</span>
                        </div>
                        @if($report->service_time)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Time</span>
                            <span class="text-base-content font-medium">{{ $report->service_time->format('g:i A') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Billing Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Billing Summary</h3>
                    <div class="space-y-3">
                        @if($report->location->rate_per_visit && $report->location->rate_per_visit > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Visit Cost</span>
                            <span class="text-base-content font-medium">${{ number_format($report->location->rate_per_visit, 2) }}</span>
                        </div>
                        @else
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Service Visit Cost</span>
                            <span class="text-base-content font-medium text-base-content/50">Not set</span>
                        </div>
                        @endif
                        @if($report->chemicals_cost > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Chemicals Cost</span>
                            <span class="text-base-content font-medium">${{ number_format($report->chemicals_cost, 2) }}</span>
                        </div>
                        @endif
                        @if($report->other_services_cost > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Other Services</span>
                            <span class="text-base-content font-medium">${{ number_format($report->other_services_cost, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-base-300 pt-2">
                            <span class="text-base-content font-semibold">Total Cost</span>
                            <span class="text-base-content font-bold text-lg">${{ number_format($report->total_cost, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Related Invoice -->
                @php
                    $relatedInvoice = \App\Models\Invoice::where('location_id', $report->location_id)
                        ->where('service_date', $report->service_date)
                        ->first();
                @endphp
                @if($relatedInvoice)
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Related Invoice</h3>
                    <div class="bg-base-200 rounded-lg p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Invoice #</span>
                                <span class="text-base-content font-medium">{{ $relatedInvoice->invoice_number }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Status</span>
                                <div class="badge badge-{{ $relatedInvoice->status == 'paid' ? 'success' : ($relatedInvoice->status == 'overdue' ? 'error' : 'warning') }}">
                                    {{ ucfirst($relatedInvoice->status) }}
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Total Amount</span>
                                <span class="text-base-content font-medium">${{ number_format($relatedInvoice->total_amount, 2) }}</span>
                            </div>
                            @if($relatedInvoice->balance > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Balance</span>
                                <span class="text-base-content font-medium">${{ number_format($relatedInvoice->balance, 2) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Chemistry Readings -->
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <h3 class="text-lg font-semibold text-base-content mb-4">Chemistry Readings</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($report->chemistryReadings as $reading => $value)
                        @if($value !== null)
                        <div class="bg-base-200 rounded-lg p-3">
                            <div class="text-sm text-base-content/70 mb-1">{{ strtoupper($reading) }}</div>
                            <div class="text-lg font-semibold text-base-content">{{ $value }}</div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Cleaning Tasks -->
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <h3 class="text-lg font-semibold text-base-content mb-4">Cleaning Tasks</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach($report->cleaningTasks as $task => $completed)
                        <div class="flex items-center space-x-3">
                            <div class="badge badge-{{ $completed ? 'success' : 'error' }} badge-sm">
                                {{ $completed ? '✓' : '✗' }}
                            </div>
                            <span class="text-base-content">{{ ucwords(str_replace('_', ' ', $task)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Maintenance Tasks -->
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <h3 class="text-lg font-semibold text-base-content mb-4">Maintenance Tasks</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach($report->maintenanceTasks as $task => $completed)
                        <div class="flex items-center space-x-3">
                            <div class="badge badge-{{ $completed ? 'success' : 'error' }} badge-sm">
                                {{ $completed ? '✓' : '✗' }}
                            </div>
                            <span class="text-base-content">{{ ucwords(str_replace('_', ' ', $task)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Chemicals & Services -->
            @if($report->chemicals_used || $report->other_services)
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <h3 class="text-lg font-semibold text-base-content mb-4">Chemicals & Services</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($report->chemicals_used)
                    <div>
                        <h4 class="font-semibold text-base-content mb-2">Chemicals Used</h4>
                        <div class="bg-base-200 rounded-lg p-4">
                            @if(is_array($report->chemicals_used))
                                <ul class="space-y-1">
                                    @foreach($report->chemicals_used as $chemical)
                                        <li class="text-base-content">• {{ $chemical }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-base-content">{{ $report->chemicals_used }}</p>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if($report->other_services)
                    <div>
                        <h4 class="font-semibold text-base-content mb-2">Other Services</h4>
                        <div class="bg-base-200 rounded-lg p-4">
                            @if(is_array($report->other_services))
                                <ul class="space-y-1">
                                    @foreach($report->other_services as $service)
                                        <li class="text-base-content">• {{ $service }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-base-content">{{ $report->other_services }}</p>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Notes -->
            @if($report->notes_to_client || $report->notes_to_admin)
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <h3 class="text-lg font-semibold text-base-content mb-4">Notes</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($report->notes_to_client)
                    <div>
                        <h4 class="font-semibold text-base-content mb-2">Notes to Client</h4>
                        <div class="bg-base-200 rounded-lg p-4">
                            <p class="text-base-content">{{ $report->notes_to_client }}</p>
                        </div>
                    </div>
                    @endif

                    @if($report->notes_to_admin)
                    <div>
                        <h4 class="font-semibold text-base-content mb-2">Notes to Admin</h4>
                        <div class="bg-base-200 rounded-lg p-4">
                            <p class="text-base-content">{{ $report->notes_to_admin }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Photos -->
            @if($report->photos && is_array($report->photos) && count($report->photos) > 0)
            <div class="bg-base-100 shadow-xl rounded-lg p-6">
                <h3 class="text-lg font-semibold text-base-content mb-4">Photos</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($report->photos as $photo)
                        <div class="aspect-square rounded-lg overflow-hidden">
                            <img src="{{ $photo }}" alt="Report Photo" class="w-full h-full object-cover">
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection 