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
                Add New Location
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
    <div class="bg-base-100 shadow-xl rounded-lg p-6 mb-6">
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
            <div class="flex flex-col sm:flex-row gap-4">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Search
                </button>
                <a href="{{ route('locations.index') }}" class="btn btn-outline">Clear Filters</a>
            </div>
        </form>
    </div>

    <!-- Locations Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Location</th>
                        <th>Client</th>
                        <th>Address</th>
                        <th>Pool Details</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($locations as $location)
                    <tr>
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-12 h-12">
                                        @if($location->photos && count($location->photos) > 0)
                                            <img src="{{ Storage::url($location->photos[0]) }}" alt="{{ $location->nickname ?? $location->name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">{{ $location->nickname ?? $location->name }}</div>
                                    <div class="text-sm opacity-50">{{ $location->access }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $location->client->full_name }}</div>
                                <div class="text-base-content/70">{{ $location->client->email }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $location->street_address }}</div>
                                @if($location->street_address_2)
                                    <div class="text-base-content/70">{{ $location->street_address_2 }}</div>
                                @endif
                                <div class="text-base-content/70">{{ $location->city }}, {{ $location->state }} {{ $location->zip_code }}</div>
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
                        <td>
                            <div class="text-sm">
                                @if($location->assignedTechnician)
                                    <div class="text-base-content">{{ $location->assignedTechnician->full_name }}</div>
                                    <div class="text-base-content/70">{{ $location->assignedTechnician->email }}</div>
                                @else
                                    <span class="text-base-content/50">Unassigned</span>
                                @endif
                            </div>
                        </td>
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
                        <td class="text-right">
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-ghost btn-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                    </svg>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                                    <li><a href="{{ route('locations.show', $location) }}">View Details</a></li>
                                    <li><a href="{{ route('locations.edit', $location) }}">Edit</a></li>
                                    <li>
                                        <form action="{{ route('locations.toggle-favorite', $location) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="w-full text-left">
                                                {{ $location->is_favorite ? 'Remove from Favorites' : 'Add to Favorites' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('locations.toggle-status', $location) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="w-full text-left">
                                                {{ $location->status === 'active' ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('locations.destroy', $location) }}" method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this location?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full text-left text-error">Delete</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8">
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