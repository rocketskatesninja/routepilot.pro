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
                Add New Client
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
            title="Pending" 
            :value="$stats['pending']" 
            color="warning"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
        <x-stat-card 
            title="Inactive" 
            :value="$stats['inactive']" 
            color="error"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
    </div>

    <!-- Search and Filters -->
    <div class="bg-base-100 shadow-xl rounded-lg p-6 mb-8">
        <form method="GET" action="{{ route('clients.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <!-- Active Filter -->
                <div>
                    <label for="active" class="block text-sm font-medium text-base-content mb-2">Active Status</label>
                    <select name="active" id="active" class="select select-bordered w-full">
                        <option value="">All</option>
                        <option value="1" {{ request('active') == '1' ? 'selected' : '' }}>Active Only</option>
                        <option value="0" {{ request('active') == '0' ? 'selected' : '' }}>Inactive Only</option>
                    </select>
                </div>
                <!-- Sort -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                    <select name="sort_by" id="sort_by" class="select select-bordered w-full">
                        <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>Date Created</option>
                        <option value="first_name" {{ request('sort_by') == 'first_name' ? 'selected' : '' }}>First Name</option>
                        <option value="last_name" {{ request('sort_by') == 'last_name' ? 'selected' : '' }}>Last Name</option>
                        <option value="email" {{ request('sort_by') == 'email' ? 'selected' : '' }}>Email</option>
                        <option value="status" {{ request('sort_by') == 'status' ? 'selected' : '' }}>Status</option>
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
                <a href="{{ route('clients.index') }}" class="btn btn-outline">Clear Filters</a>
            </div>
        </form>
    </div>

    <!-- Clients Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                    <tr>
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-12 h-12">
                                        @if($client->profile_photo)
                                            <img src="{{ Storage::url($client->profile_photo) }}" alt="{{ $client->full_name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                                <span class="text-lg font-semibold">{{ substr($client->first_name, 0, 1) }}{{ substr($client->last_name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">{{ $client->full_name }}</div>
                                    <div class="text-sm opacity-50">{{ $client->role }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $client->email }}</div>
                                @if($client->phone)
                                    <div class="text-base-content/70">{{ $client->phone }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($client->city && $client->state)
                                <div class="text-sm">
                                    <div class="text-base-content">{{ $client->city }}, {{ $client->state }}</div>
                                    @if($client->zip_code)
                                        <div class="text-base-content/70">{{ $client->zip_code }}</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-base-content/50 text-sm">No address</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center space-x-2">
                                @if($client->is_active)
                                    <div class="badge badge-success">Active</div>
                                @else
                                    <div class="badge badge-error">Inactive</div>
                                @endif
                                <div class="badge badge-{{ $client->status == 'active' ? 'success' : ($client->status == 'pending' ? 'warning' : 'error') }}">
                                    {{ ucfirst($client->status) }}
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm text-base-content">
                                {{ $client->created_at->format('M j, Y') }}
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
                                    <li><a href="{{ route('clients.show', $client) }}">View Details</a></li>
                                    <li><a href="{{ route('clients.edit', $client) }}">Edit</a></li>
                                    <li>
                                        <form action="{{ route('clients.toggle-status', $client) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="w-full text-left">
                                                {{ $client->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this client?')">
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