@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Service Report #{{ $report->id }}</h1>
            <p class="text-base-content/70 mt-2">{{ $report->service_date->format('M j, Y') }} - {{ $report->location->nickname ?? 'Location' }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
            <a href="{{ route('reports.edit', $report) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Report
            </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Report Summary -->
        <div class="lg:col-span-1">
            <div class="bg-base-100 shadow-xl rounded-lg p-6 border border-base-300">
                <div class="text-center mb-6">
                    <!-- Slideshow or fallback image -->
                    <div class="mb-4">
                        @php
                            $reportPhotos = $report->photos && is_array($report->photos) && count($report->photos) > 0 ? $report->photos : null;
                            $locationPhotos = $report->location && $report->location->photos && is_array($report->location->photos) && count($report->location->photos) > 0 ? $report->location->photos : null;
                        @endphp
                        @if($reportPhotos)
                            <div class="carousel w-full h-80 rounded-lg overflow-hidden">
                                @foreach($reportPhotos as $index => $photo)
                                    <div id="slide-report-{{ $index }}" class="carousel-item relative w-full">
                                        <img src="{{ asset(Storage::url($photo)) }}" alt="Report Photo {{ $index + 1 }}" class="w-full h-full object-cover">
                                        @if(count($reportPhotos) > 1)
                                            <div class="absolute flex justify-between transform -translate-y-1/2 left-5 right-5 top-1/2">
                                                <a href="#slide-report-{{ $index == 0 ? count($reportPhotos) - 1 : $index - 1 }}" class="btn btn-circle btn-sm bg-base-100/50 hover:bg-base-100/80">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                    </svg>
                                                </a>
                                                <a href="#slide-report-{{ $index == count($reportPhotos) - 1 ? 0 : $index + 1 }}" class="btn btn-circle btn-sm bg-base-100/50 hover:bg-base-100/80">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if(count($reportPhotos) > 1)
                                <div class="flex justify-center w-full py-2 gap-2">
                                    @foreach($reportPhotos as $index => $photo)
                                        <a href="#slide-report-{{ $index }}" class="btn btn-xs">{{ $index + 1 }}</a>
                                    @endforeach
                                </div>
                            @endif
                        @elseif($locationPhotos)
                            <div class="carousel w-full h-80 rounded-lg overflow-hidden">
                                @foreach($locationPhotos as $index => $photo)
                                    <div id="slide-location-{{ $index }}" class="carousel-item relative w-full">
                                        <img src="{{ asset(Storage::url($photo)) }}" alt="Location Photo {{ $index + 1 }}" class="w-full h-full object-cover">
                                        @if(count($locationPhotos) > 1)
                                            <div class="absolute flex justify-between transform -translate-y-1/2 left-5 right-5 top-1/2">
                                                <a href="#slide-location-{{ $index == 0 ? count($locationPhotos) - 1 : $index - 1 }}" class="btn btn-circle btn-sm bg-base-100/50 hover:bg-base-100/80">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                    </svg>
                                                </a>
                                                <a href="#slide-location-{{ $index == count($locationPhotos) - 1 ? 0 : $index + 1 }}" class="btn btn-circle btn-sm bg-base-100/50 hover:bg-base-100/80">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if(count($locationPhotos) > 1)
                                <div class="flex justify-center w-full py-2 gap-2">
                                    @foreach($locationPhotos as $index => $photo)
                                        <a href="#slide-location-{{ $index }}" class="btn btn-xs">{{ $index + 1 }}</a>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="w-full h-80 bg-base-200 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="w-24 h-24 mx-auto text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <p class="text-base-content/50 mt-2">No photos available</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <h2 class="text-xl font-semibold text-base-content mt-4">Service Report</h2>
                    <p class="text-base-content/70">{{ $report->service_date->format('M j, Y') }}</p>
                </div>

                <!-- Technician Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Technician</h3>
                    <div class="flex items-center space-x-3">
                        <div class="avatar">
                            <div class="mask mask-squircle w-12 h-12">
                                @if($report->technician->profile_photo)
                                    <img src="{{ asset(Storage::url($report->technician->profile_photo)) }}" alt="{{ $report->technician->full_name }}">
                                @else
                                    <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                        <span class="text-sm font-semibold">{{ substr($report->technician->first_name, 0, 1) }}{{ substr($report->technician->last_name, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('technicians.show', $report->technician) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ $report->technician->full_name }}
                                </a>
                            @else
                                <div class="font-medium text-base-content">{{ $report->technician->full_name }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Service Information -->
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-semibold text-base-content">Service Information</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Client</span>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('clients.show', $report->client) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ $report->client->full_name }}
                                </a>
                            @else
                                <span class="text-base-content font-medium">{{ $report->client->full_name }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">Location</span>
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('locations.show', $report->location) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ $report->location->nickname ?? 'Location' }}
                                </a>
                            @else
                                <span class="text-base-content font-medium">{{ $report->location->nickname ?? 'Location' }}</span>
                            @endif
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
                @if($report->invoice)
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-base-content">Related Invoice</h3>
                    <div class="bg-base-200 rounded-lg p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Invoice #</span>
                                <a href="{{ route('invoices.show', $report->invoice) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ $report->invoice->invoice_number }}
                                </a>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Status</span>
                                <div class="badge badge-{{ $report->invoice->status == 'paid' ? 'success' : ($report->invoice->status == 'overdue' ? 'error' : 'warning') }}">
                                    {{ ucfirst($report->invoice->status) }}
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Total Amount</span>
                                <span class="text-base-content font-medium">${{ number_format($report->invoice->total_amount, 2) }}</span>
                            </div>
                            @if($report->invoice->balance > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Balance</span>
                                <span class="text-base-content font-medium">${{ number_format($report->invoice->balance, 2) }}</span>
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
            <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                <div class="tabs tabs-boxed p-4">
                    <a id="tab-chemistry" onclick="showTab('chemistry', event)" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out border-primary text-base-content focus:outline-none focus:border-primary-focus" style="margin-right: 1.5rem; cursor:pointer;">Chemistry Readings</a>
                    <a id="tab-cleaning" onclick="showTab('cleaning', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Cleaning Tasks</a>
                    <a id="tab-maintenance" onclick="showTab('maintenance', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Maintenance Tasks</a>
                    <a id="tab-chemicals" onclick="showTab('chemicals', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Chemicals & Services</a>
                    <a id="tab-notes" onclick="showTab('notes', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="margin-right: 1.5rem; cursor:pointer;">Notes</a>
                    <a id="tab-photos" onclick="showTab('photos', event)" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out" style="cursor:pointer;">Photos</a>
                </div>
                <div class="p-6">
                    <div id="chemistry-tab" class="tab-content" style="display: block !important;">
                        <!-- Chemistry Readings Section -->
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
                    <div id="cleaning-tab" class="tab-content hidden">
                        <!-- Cleaning Tasks Section -->
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
                    <div id="maintenance-tab" class="tab-content hidden">
                        <!-- Maintenance Tasks Section -->
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
                    <div id="chemicals-tab" class="tab-content hidden">
                        <!-- Chemicals & Services Section -->
                        @if($report->chemicals_used || $report->other_services)
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
                        @endif
                    </div>
                    <div id="notes-tab" class="tab-content hidden">
                        <!-- Notes Section -->
                        @if($report->notes_to_client || $report->notes_to_admin)
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
                        @endif
                    </div>
                    <div id="photos-tab" class="tab-content hidden">
                        <!-- Photos Section -->
                        @if($report->photos && is_array($report->photos) && count($report->photos) > 0)
                        <h3 class="text-lg font-semibold text-base-content mb-4">Photos</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($report->photos as $photo)
                                <div class="aspect-square rounded-lg overflow-hidden relative">
                                    <a href="{{ asset(Storage::url($photo)) }}" target="_blank" class="block w-full h-full">
<img src="{{ asset(Storage::url($photo)) }}" alt="Report Photo" class="w-full h-full object-cover hover:opacity-80 transition-opacity">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
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
    showTab('chemistry');
});
</script>
@endsection 