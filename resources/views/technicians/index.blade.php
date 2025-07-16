@extends('layouts.app')

@section('title', 'Technicians')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Technicians</h1>
            <p class="text-base-content/70 mt-2">Manage your pool service technicians</p>
        </div>
        <a href="{{ route('technicians.create') }}" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add Technician
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8 mt-8">
        <x-stat-card 
            title="Total Technicians" 
            :value="$technicians->total()" 
            color="primary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'></path>"
        />
        <x-stat-card 
            title="Active Technicians" 
            :value="$technicians->where('is_active', true)->count()" 
            color="success"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
        <x-stat-card 
            title="Inactive Technicians" 
            :value="$technicians->where('is_active', false)->count()" 
            color="warning"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
    </div>

    <!-- Search and Filters -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <form method="GET" action="{{ route('technicians.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, or phone" class="input input-bordered w-full">
                </div>
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Status</label>
                        <select name="status" class="select select-bordered w-full">
                            <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                    <!-- Sort By -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                        <select name="sort_by" class="select select-bordered w-full">
                        <option value="created_at" {{ request('sort_by', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                        <option value="first_name" {{ request('sort_by') === 'first_name' ? 'selected' : '' }}>First Name</option>
                        <option value="last_name" {{ request('sort_by') === 'last_name' ? 'selected' : '' }}>Last Name</option>
                        <option value="email" {{ request('sort_by') === 'email' ? 'selected' : '' }}>Email</option>
                    </select>
                </div>
                    <!-- Sort Order -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Sort Order</label>
                        <select name="sort_order" class="select select-bordered w-full">
                        <option value="desc" {{ request('sort_order', 'desc') === 'desc' ? 'selected' : '' }}>Descending</option>
                        <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>Ascending</option>
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
                    <a href="{{ route('technicians.index') }}" class="btn btn-outline">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Technicians Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Technician</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($technicians as $technician)
                    <tr>
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-12 h-12">
                                        @if($technician->profile_photo)
                                            <img src="{{ Storage::url($technician->profile_photo) }}" alt="{{ $technician->full_name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                                <span class="text-lg font-semibold">{{ strtoupper(substr($technician->first_name, 0, 1) . substr($technician->last_name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">{{ $technician->full_name }}</div>
                                    <div class="text-sm opacity-50">ID: {{ $technician->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $technician->email }}</div>
                                @if($technician->phone)
                                    <div class="text-base-content/70">{{ $technician->phone }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($technician->city && $technician->state)
                                <div class="text-sm">
                                    <div class="text-base-content">{{ $technician->city }}, {{ $technician->state }}</div>
                                    @if($technician->zip_code)
                                        <div class="text-base-content/70">{{ $technician->zip_code }}</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-base-content/50 text-sm">No address</span>
                            @endif
                        </td>
                        <td>
                            @if($technician->is_active)
                                <div class="badge badge-success">Active</div>
                            @else
                                <div class="badge badge-error">Inactive</div>
                            @endif
                        </td>
                        <td>
                            <div class="text-sm text-base-content">
                                {{ $technician->created_at->format('M j, Y') }}
                                <div class="text-base-content/70">{{ $technician->created_at->format('g:i A') }}</div>
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
                                    <li><a href="{{ route('technicians.show', $technician) }}">View Details</a></li>
                                    <li><a href="{{ route('technicians.edit', $technician) }}">Edit</a></li>
                                    <li>
                                        <form action="{{ route('technicians.toggle-status', $technician) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="w-full text-left">
                                                {{ $technician->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('technicians.destroy', $technician) }}" method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this technician? This action cannot be undone.')">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <p class="text-lg font-medium">No technicians found</p>
                                <p class="text-sm">Get started by adding your first technician.</p>
                                <a href="{{ route('technicians.create') }}" class="btn btn-primary mt-4">Add First Technician</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        @if($technicians->hasPages())
        <div class="p-4 border-t border-base-200">
            {{ $technicians->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection 