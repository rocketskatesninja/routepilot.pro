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
    <div class="bg-base-100 shadow-xl mb-6">
        <div class="card-body">
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
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Report #</th>
                        <th>Service Date</th>
                        <th>Client</th>
                        <th>Location</th>
                        @if(auth()->user()->role === 'admin')
                            <th>Technician</th>
                        @endif
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                    <tr>
                        <td>
                            <a href="{{ route('reports.show', $report) }}" class="hover:text-primary hover:underline">
                                #{{ $report->id }}
                            </a>
                        </td>
                        <td>{{ $report->service_date ? $report->service_date->format('M d, Y') : '-' }}</td>
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
                                            <a href="{{ route('clients.show', $report->client) }}" class="hover:text-primary hover:underline">
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
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">
                                    @if($report->location)
                                        <a href="{{ route('locations.show', $report->location) }}" class="hover:text-primary hover:underline">
                                            {{ $report->location->nickname ?? 'Location' }}
                                        </a>
                                    @else
                                        Location
                                    @endif
                                </div>
                                @if($report->location && $report->location->city && $report->location->state)
                                    <a href="https://maps.google.com/?q={{ urlencode($report->location->street_address . ', ' . $report->location->city . ', ' . $report->location->state . ' ' . $report->location->zip_code) }}" 
                                       target="_blank" 
                                       class="text-base-content/70 hover:text-primary hover:underline">
                                        {{ $report->location->full_address ?? $report->location->city . ', ' . $report->location->state }}
                                    </a>
                                @else
                                    <div class="text-base-content/70">{{ $report->location->full_address ?? '-' }}</div>
                                @endif
                            </div>
                        </td>
                        @if(auth()->user()->role === 'admin')
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
                                            <a href="{{ route('technicians.show', $report->technician) }}" class="hover:text-primary hover:underline">
                                                {{ $report->technician->full_name }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="text-sm opacity-50">{{ $report->technician->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        @endif
                        <td class="text-right">
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-ghost btn-xs">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                    </svg>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-40">
                                    <li><a href="{{ route('reports.show', $report) }}">View Details</a></li>
                                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                        <li><a href="{{ route('reports.edit', $report) }}">Edit</a></li>
                                        <li>
                                            <form action="{{ route('reports.destroy', $report) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this report?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left text-error">Delete</button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()->role === 'admin' ? 6 : 5 }}" class="text-center py-8">
                            <div class="text-base-content/70">
                                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h2a4 4 0 014 4v2M7 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                <p class="text-lg font-medium">No reports found</p>
                                <p class="text-sm">Get started by adding your first report.</p>
                                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'technician')
                                <a href="{{ route('reports.create') }}" class="btn btn-primary mt-4">Add First Report</a>
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