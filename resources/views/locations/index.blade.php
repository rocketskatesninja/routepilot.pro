@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Locations</h1>
            <p class="text-base-content/70 mt-2">Manage pool service locations</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <a href="{{ route('locations.create') }}" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Location
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <x-stat-card 
            title="Total Locations" 
            :value="$stats['total']" 
            color="primary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'></path>"
        />
        <x-stat-card 
            title="Active" 
            :value="$stats['active']" 
            color="success"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
        <x-stat-card 
            title="Favorites" 
            :value="$stats['favorite']" 
            color="warning"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'></path>"
        />
        <x-stat-card 
            title="Residential" 
            :value="$stats['residential']" 
            color="info"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z'></path><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 5a2 2 0 012-2h4a2 2 0 012 2v6H8V5z'></path>"
        />
        <x-stat-card 
            title="Commercial" 
            :value="$stats['commercial']" 
            color="secondary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'></path>"
        />
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
                    @if(request('search') || request('status') || request('pool_type') || request('water_type') || request('sort_by'))
                        <span class="badge badge-primary badge-sm">{{ collect([request('search'), request('status'), request('pool_type'), request('water_type'), request('sort_by')])->filter()->count() }}</span>
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
            <form method="GET" action="{{ route('locations.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-base-content mb-2">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" class="input input-bordered w-full" placeholder="Location, address, client...">
                    </div>
                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-base-content mb-2">Status</label>
                        <select name="status" id="status" class="select select-bordered w-full">
                            <option value="">All Statuses</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <!-- Pool Type Filter -->
                    <div>
                        <label for="pool_type" class="block text-sm font-medium text-base-content mb-2">Pool Type</label>
                        <select name="pool_type" id="pool_type" class="select select-bordered w-full">
                            <option value="">All Types</option>
                            <option value="fiberglass" {{ request('pool_type') == 'fiberglass' ? 'selected' : '' }}>Fiberglass</option>
                            <option value="vinyl_liner" {{ request('pool_type') == 'vinyl_liner' ? 'selected' : '' }}>Vinyl Liner</option>
                            <option value="concrete" {{ request('pool_type') == 'concrete' ? 'selected' : '' }}>Concrete</option>
                            <option value="gunite" {{ request('pool_type') == 'gunite' ? 'selected' : '' }}>Gunite</option>
                        </select>
                    </div>
                    <!-- Water Type Filter -->
                    <div>
                        <label for="water_type" class="block text-sm font-medium text-base-content mb-2">Water Type</label>
                        <select name="water_type" id="water_type" class="select select-bordered w-full">
                            <option value="">All Types</option>
                            <option value="chlorine" {{ request('water_type') == 'chlorine' ? 'selected' : '' }}>Chlorine</option>
                            <option value="salt" {{ request('water_type') == 'salt' ? 'selected' : '' }}>Salt</option>
                        </select>
                    </div>
                    <!-- Sort By -->
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                        <select name="sort_by" id="sort_by" class="select select-bordered w-full">
                            <option value="date_desc" {{ request('sort_by') == 'date_desc' ? 'selected' : '' }}>Date Created (Newest)</option>
                            <option value="date_asc" {{ request('sort_by') == 'date_asc' ? 'selected' : '' }}>Date Created (Oldest)</option>
                            <option value="status" {{ request('sort_by') == 'status' ? 'selected' : '' }}>Status</option>
                            <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>Name</option>
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
                        <a href="{{ route('locations.index') }}" class="btn btn-outline">Clear Filters</a>
                    </div>
                    <div class="text-sm text-base-content/70">
                        {{ $locations->total() }} result{{ $locations->total() != 1 ? 's' : '' }}
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Locations Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden border border-base-300">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Location</th>
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <th>Client</th>
                        @endif
                        <th>Address</th>
                        <th>Pool Details</th>
                        @if(auth()->user()->isCustomer())
                        <th>Rate per Visit</th>
                        @endif
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <th>Technician</th>
                        @endif
                        <th>Status</th>
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <th class="text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($locations as $location)
                    <tr>
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-10 h-10">
                                        @if($location->photos && count($location->photos) > 0)
                                            <img src="{{ Storage::url($location->photos[0]) }}" alt="{{ $location->nickname ?? 'Location' }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-10 h-10">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">
                                        <a href="{{ route('locations.show', $location) }}" class="hover:text-primary hover:underline">
                                            {{ $location->nickname ?? 'Location' }}
                                        </a>
                                    </div>
                                    <div class="text-sm opacity-50">{{ $location->access }}</div>
                                </div>
                            </div>
                        </td>
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">
                                    <a href="{{ route('clients.show', $location->client) }}" class="hover:text-primary hover:underline">
                                        {{ $location->client->full_name }}
                                    </a>
                                </div>
                                <div class="text-base-content/70">{{ $location->client->email }}</div>
                            </div>
                        </td>
                        @endif
                        <td>
                            <div class="text-sm">
                                <a href="https://maps.google.com/?q={{ urlencode($location->street_address . ', ' . $location->city . ', ' . $location->state . ' ' . $location->zip_code) }}" 
                                   target="_blank" 
                                   class="hover:text-primary hover:underline">
                                    <div class="text-base-content">{{ $location->street_address }}</div>
                                    @if($location->street_address_2)
                                        <div class="text-base-content/70">{{ $location->street_address_2 }}</div>
                                    @endif
                                    <div class="text-base-content/70">{{ $location->city }}, {{ $location->state }} {{ $location->zip_code }}</div>
                                </a>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @if($location->pool_type)
                                    <span class="badge badge-info">{{ ucfirst($location->pool_type) }}</span>
                                @endif
                                @if($location->water_type)
                                    <span class="badge badge-secondary">{{ ucfirst($location->water_type) }}</span>
                                @endif
                            </div>
                        </td>
                        @if(auth()->user()->isCustomer())
                        <td>
                            <div class="text-sm">
                                @if($location->rate_per_visit)
                                    <span class="text-base-content font-medium">${{ number_format($location->rate_per_visit, 2) }}</span>
                                @else
                                    <span class="text-base-content/50">Not set</span>
                                @endif
                            </div>
                        </td>
                        @endif
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <td>
                            <div class="text-sm">
                                @if($location->assignedTechnician)
                                    <div class="text-base-content">
                                        <a href="{{ route('technicians.show', $location->assignedTechnician) }}" class="hover:text-primary hover:underline">
                                            {{ $location->assignedTechnician->full_name }}
                                        </a>
                                    </div>
                                    <div class="text-base-content/70">{{ $location->assignedTechnician->email }}</div>
                                @else
                                    <span class="text-base-content/50">Unassigned</span>
                                @endif
                            </div>
                        </td>
                        @endif
                        <td>
                            <div class="flex items-center space-x-2">
                                @if($location->is_favorite)
                                    <span class="badge badge-warning">Favorite</span>
                                @endif
                                <span class="badge badge-{{ $location->status === 'active' ? 'success' : 'error' }}">
                                    {{ ucfirst($location->status) }}
                                </span>
                            </div>
                        </td>
                        @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                        <td class="text-right">
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('locations.edit', $location) }}" class="btn btn-sm btn-square btn-ghost btn-outline" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <form action="{{ route('locations.destroy', $location) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this location?')">
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
                        <td colspan="{{ auth()->user()->isAdmin() || auth()->user()->isTechnician() ? 7 : (auth()->user()->isCustomer() ? 5 : 4) }}" class="text-center py-8">
                            <div class="text-base-content/70">
                                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="text-lg font-medium">No locations found</p>
                                <p class="text-sm">Get started by adding your first location.</p>
                                <a href="{{ route('locations.create') }}" class="btn btn-primary mt-4">Add First Location</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($locations->hasPages())
        <div class="p-4 border-t border-base-200">
            {{ $locations->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection 