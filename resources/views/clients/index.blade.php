@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Clients</h1>
            <p class="text-base-content/70 mt-2">Manage your pool service clients</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <a href="{{ route('clients.create') }}" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Client
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-stat-card 
            title="Total Clients" 
            :value="$stats['total']" 
            color="primary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'></path>"
        />
        <x-stat-card 
            title="Active Clients" 
            :value="$stats['active']" 
            color="success"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
        <x-stat-card 
            title="Inactive" 
            :value="$stats['inactive']" 
            color="error"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
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
                    @if(request('search') || request('status') || request('sort_by'))
                        <span class="badge badge-primary badge-sm">{{ collect([request('search'), request('status'), request('sort_by')])->filter()->count() }}</span>
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
            <form method="GET" action="{{ route('clients.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-base-content mb-2">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" 
                               class="input input-bordered w-full" placeholder="Name, email, phone...">
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
                    <!-- Sort -->
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                        <select name="sort_by" id="sort_by" class="select select-bordered w-full">
                            <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>Name</option>
                            <option value="status" {{ request('sort_by') == 'status' ? 'selected' : '' }}>Status</option>
                            <option value="balance_desc" {{ request('sort_by') == 'balance_desc' ? 'selected' : '' }}>Balance (High to Low)</option>
                            <option value="balance_asc" {{ request('sort_by') == 'balance_asc' ? 'selected' : '' }}>Balance (Low to High)</option>
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
                        <a href="{{ route('clients.index') }}" class="btn btn-outline">Clear Filters</a>
                    </div>
                    <div class="text-sm text-base-content/70">
                        {{ $clients->total() }} result{{ $clients->total() != 1 ? 's' : '' }}
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden border border-base-300">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Locations</th>
                        <th>Status</th>
                        <th>Balance</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                    <tr>
                        <!-- Client -->
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-10 h-10">
                                        @if($client->profile_photo)
                                            <img src="{{ asset(Storage::url($client->profile_photo)) }}" alt="{{ $client->full_name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-10 h-10">
                                                <span class="text-sm font-semibold">{{ substr($client->first_name, 0, 1) }}{{ substr($client->last_name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">
                                        <a href="{{ route('clients.show', $client) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                            {{ $client->full_name }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <!-- Contact -->
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $client->email }}</div>
                                @if($client->phone)
                                    <div class="text-base-content/70">{{ $client->phone }}</div>
                                @endif
                            </div>
                        </td>
                        <!-- Address -->
                        <td>
                            @if($client->locations->count() > 0)
                                @php
                                    $loc = $client->locations->first();
                                    $address_street = trim($loc->street_address . ($loc->street_address_2 ? ' ' . $loc->street_address_2 : ''));
                                    $address_city = trim($loc->city . ', ' . $loc->state . ' ' . $loc->zip_code);
                                    $full_address = trim($address_street . ', ' . $address_city);
                                    $user = auth()->user();
                                    $mapsProvider = $user->maps_provider ?? 'google';
                                    $mapsUrl = match($mapsProvider) {
                                        'apple' => 'https://maps.apple.com/?q=' . urlencode($full_address),
                                        'bing' => 'https://bing.com/maps/default.aspx?where1=' . urlencode($full_address),
                                        default => 'https://maps.google.com/?q=' . urlencode($full_address),
                                    };
                                @endphp
                                @if($user->role === 'admin' || $user->role === 'technician')
                                    <a href="{{ $mapsUrl }}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <div>{{ $address_street }}</div>
                                        <div class="text-base-content/70">{{ $address_city }}</div>
                                    </a>
                                @else
                                    <div>{{ $address_street }}</div>
                                    <div class="text-base-content/70">{{ $address_city }}</div>
                                @endif
                            @else
                                <span class="text-base-content/50 text-sm">No address</span>
                            @endif
                        </td>
                        <!-- Locations column in each row -->
                        <td>
                            {{ $client->locations->count() }}
                        </td>
                        <!-- Status -->
                        <td>
                            <div class="badge badge-{{ $client->status == 'active' ? 'success' : 'error' }}">
                                {{ ucfirst($client->status) }}
                            </div>
                        </td>
                        <!-- Balance -->
                        <td>
                            @php
                                $balance = $client->invoices()->whereNotIn('status', ['paid', 'draft'])->sum('balance');
                            @endphp
                            <div class="font-bold {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                ${{ number_format($balance, 2) }}
                            </div>
                        </td>
                        <!-- Actions -->
                        <td class="text-right">
                            <div class="flex gap-2 justify-end">
                                <a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-square btn-ghost btn-outline" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this client?')">
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
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-8">
                            <div class="text-base-content/70">
                                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p class="text-lg font-medium">No clients found</p>
                                <p class="text-sm">Get started by adding your first client.</p>
                                <a href="{{ route('clients.create') }}" class="btn btn-primary mt-4">Add First Client</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        @if($clients->hasPages())
        <div class="p-4 border-t border-base-200">
            {{ $clients->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection 