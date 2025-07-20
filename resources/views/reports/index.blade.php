@extends('layouts.app')

@section('title', 'Service Reports')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Service Reports</h1>
            <p class="text-base-content/70 mt-2">View and manage pool service reports</p>
        </div>
        @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
        <div class="mt-4 lg:mt-0">
            <a href="{{ route('reports.create') }}" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Report
            </a>
        </div>
        @endif
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-stat-card 
            title="Total Reports" 
            :value="$stats['total'] ?? $reports->total()" 
            color="primary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"/>
        <x-stat-card 
            title="This Month" 
            :value="$stats['this_month'] ?? 0" 
            color="success"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'></path>"/>
        <x-stat-card 
            title="This Week" 
            :value="$stats['this_week'] ?? 0" 
            color="info"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"/>
    </div>

    <!-- Search and Filters -->
    <div class="bg-base-100 shadow-xl rounded-lg mb-6 border border-base-300" x-data="{ filtersOpen: false }">
        <!-- Filter Header -->
        <div class="p-4 border-b border-base-200">
            <button 
                @click="filtersOpen = !filtersOpen" 
                class="flex items-center justify-between w-full text-left hover:bg-base-200 p-2 rounded transition-colors"
            >
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-base-content/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                    </svg>
                    <span class="font-medium text-base-content">Filters</span>
                    @if(request('search') || request('date_from') || request('date_to') || request('sort_by'))
                        <span class="badge badge-primary badge-sm">{{ collect([request('search'), request('date_from'), request('date_to'), request('sort_by')])->filter()->count() }}</span>
                    @endif
                </div>
                <svg 
                    class="w-5 h-5 text-base-content/70 transition-transform duration-200" 
                    :class="{ 'rotate-180': filtersOpen }"
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>

        <!-- Filter Content -->
        <div 
            x-show="filtersOpen" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            class="p-4"
        >
            <form method="GET" action="{{ route('reports.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Client, location, tech..." class="input input-bordered w-full">
                    </div>
                    <!-- Date From -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="input input-bordered w-full">
                    </div>
                    <!-- Date To -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="input input-bordered w-full">
                    </div>
                    <!-- Sort By -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                        <select name="sort_by" class="select select-bordered w-full">
                            <option value="date_desc" {{ request('sort_by') == 'date_desc' ? 'selected' : '' }}>Date (Newest)</option>
                            <option value="date_asc" {{ request('sort_by') == 'date_asc' ? 'selected' : '' }}>Date (Oldest)</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit" class="btn btn-primary">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                        <a href="{{ route('reports.index') }}" class="btn btn-outline">Clear Filters</a>
                    </div>
                    <div class="text-sm text-base-content/70">
                        {{ $reports->total() }} result{{ $reports->total() != 1 ? 's' : '' }}
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden border border-base-300">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Report #</th>
                        <th>Service Date</th>
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <th>Client</th>
                        @endif
                        <th>Location</th>
                        @if(auth()->user()->isAdmin() || auth()->user()->isCustomer())
                            <th>Technician</th>
                        @endif
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <th class="text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                    <tr>
                        <td>
                            <a href="{{ route('reports.show', $report) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                #{{ $report->id }}
                            </a>
                        </td>
                        <td>{{ $report->service_date ? $report->service_date->format('M d, Y') : '-' }}</td>
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-10 h-10">
                                        @if($report->client && $report->client->profile_photo)
                                            <img src="{{ Storage::url($report->client->profile_photo) }}" alt="{{ $report->client->full_name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-10 h-10">
                                                <span class="text-sm font-semibold">{{ $report->client ? substr($report->client->first_name, 0, 1) . substr($report->client->last_name, 0, 1) : '?' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">
                                        @if($report->client)
                                            <a href="{{ route('clients.show', $report->client) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $report->client->full_name }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="text-sm opacity-50">{{ $report->client->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        @endif
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">
                                    @if($report->location)
                                        <a href="{{ route('locations.show', $report->location) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                            {{ $report->location->nickname ?? 'Location' }}
                                        </a>
                                    @else
                                        Location
                                    @endif
                                </div>
                                @if($report->location && $report->location->city && $report->location->state)
                                    <a href="https://maps.google.com/?q={{ urlencode($report->location->street_address . ', ' . $report->location->city . ', ' . $report->location->state . ' ' . $report->location->zip_code) }}" 
                                       target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $report->location->full_address ?? $report->location->city . ', ' . $report->location->state }}
                                    </a>
                                @else
                                    <div class="text-base-content/70">{{ $report->location->full_address ?? '-' }}</div>
                                @endif
                            </div>
                        </td>
                        @if(auth()->user()->isAdmin() || auth()->user()->isCustomer())
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-10 h-10">
                                        @if($report->technician && $report->technician->profile_photo)
                                            <img src="{{ Storage::url($report->technician->profile_photo) }}" alt="{{ $report->technician->full_name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-10 h-10">
                                                <span class="text-sm font-semibold">{{ $report->technician ? substr($report->technician->first_name, 0, 1) . substr($report->technician->last_name, 0, 1) : '?' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">
                                        @if($report->technician)
                                            @if(auth()->user()->isAdmin())
                                                <a href="{{ route('technicians.show', $report->technician) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    {{ $report->technician->full_name }}
                                                </a>
                                            @else
                                                {{ $report->technician->full_name }}
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="text-sm opacity-50">{{ $report->technician->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        @endif
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <td class="text-right">
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-square btn-ghost btn-outline" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <form action="{{ route('reports.destroy', $report) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this report?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-square btn-error" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isAdmin() || auth()->user()->isTechnician() ? (auth()->user()->role === 'admin' ? 6 : 5) : (auth()->user()->isCustomer() ? 4 : 3) }}" class="text-center py-8">
                            <div class="text-base-content/70">
                                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-lg font-medium">No reports found</p>
                                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <p class="text-sm">Get started by adding your first report.</p>
                                <a href="{{ route('reports.create') }}" class="btn btn-primary mt-4">Add First Report</a>
                                @else
                                <p class="text-sm">No service reports have been created yet.</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        @if($reports->hasPages())
        <div class="p-4 border-t border-base-200">
            {{ $reports->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

</div>
@endsection 