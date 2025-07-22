@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Activity Log</h1>
            <p class="text-base-content/70 mt-2">Detailed system activity tracking and retention management</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            @if(auth()->user()->isAdmin())
            <a href="{{ route('activities.settings') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Retention Settings
            </a>
            <a href="{{ route('activities.export') }}?{{ http_build_query(request()->all()) }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export CSV
            </a>
            @endif
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-body p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-base-content/70">Total Activities</p>
                        <p class="text-2xl font-bold text-base-content">{{ number_format($stats['total_activities']) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-body p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-base-content/70">Today</p>
                        <p class="text-2xl font-bold text-base-content">{{ number_format($stats['activities_today']) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-success/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-body p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-base-content/70">This Week</p>
                        <p class="text-2xl font-bold text-base-content">{{ number_format($stats['activities_this_week']) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-info/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-body p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-base-content/70">Retention</p>
                        <p class="text-2xl font-bold text-base-content">{{ $stats['retention_days'] }} days</p>
                    </div>
                    <div class="w-12 h-12 bg-warning/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-xl border border-base-300 mb-8">
        <div class="card-header p-6 border-b border-base-300">
            <h3 class="text-lg font-medium text-base-content">Filters</h3>
        </div>
        <div class="card-body p-6">
            <form method="GET" action="{{ route('activities.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-base-content mb-2">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" 
                           class="input input-bordered w-full" placeholder="Search activities...">
                </div>
                
                <div>
                    <label for="action" class="block text-sm font-medium text-base-content mb-2">Action</label>
                    <select name="action" id="action" class="select select-bordered w-full">
                        <option value="">All Actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                                {{ ucfirst($action) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label for="user_id" class="block text-sm font-medium text-base-content mb-2">User</label>
                    <select name="user_id" id="user_id" class="select select-bordered w-full">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label for="model_type" class="block text-sm font-medium text-base-content mb-2">Model Type</label>
                    <select name="model_type" id="model_type" class="select select-bordered w-full">
                        <option value="">All Types</option>
                        @foreach($modelTypes as $type)
                            <option value="{{ $type }}" {{ request('model_type') == $type ? 'selected' : '' }}>
                                {{ class_basename($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-base-content mb-2">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" 
                           class="input input-bordered w-full">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-base-content mb-2">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" 
                           class="input input-bordered w-full">
                </div>
                
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                    <select name="sort_by" id="sort_by" class="select select-bordered w-full">
                        <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>Date</option>
                        <option value="action" {{ request('sort_by') == 'action' ? 'selected' : '' }}>Action</option>
                        <option value="user_id" {{ request('sort_by') == 'user_id' ? 'selected' : '' }}>User</option>
                    </select>
                </div>
                
                <div>
                    <label for="sort_direction" class="block text-sm font-medium text-base-content mb-2">Direction</label>
                    <select name="sort_direction" id="sort_direction" class="select select-bordered w-full">
                        <option value="desc" {{ request('sort_direction') == 'desc' ? 'selected' : '' }}>Descending</option>
                        <option value="asc" {{ request('sort_direction') == 'asc' ? 'selected' : '' }}>Ascending</option>
                    </select>
                </div>
                
                <div class="md:col-span-2 lg:col-span-4 flex space-x-2">
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Apply Filters
                    </button>
                    <a href="{{ route('activities.index') }}" class="btn btn-outline">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Action Breakdown -->
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-header p-6 border-b border-base-300">
                <h3 class="text-lg font-medium text-base-content">Activity by Action</h3>
            </div>
            <div class="card-body p-6">
                @if($actionBreakdown->count() > 0)
                    <div class="space-y-3">
                        @foreach($actionBreakdown as $breakdown)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content">{{ ucfirst($breakdown->action) }}</span>
                            <div class="flex items-center space-x-2">
                                <div class="w-32 bg-base-300 rounded-full h-2">
                                    @php
                                        $percentage = $stats['total_activities'] > 0 ? ($breakdown->count / $stats['total_activities']) * 100 : 0;
                                    @endphp
                                    <div class="bg-primary h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="text-sm text-base-content/70">{{ number_format($breakdown->count) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-base-content/50 text-center py-4">No activity data available</p>
                @endif
            </div>
        </div>

        <!-- User Breakdown -->
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-header p-6 border-b border-base-300">
                <h3 class="text-lg font-medium text-base-content">Activity by User</h3>
            </div>
            <div class="card-body p-6">
                @if($userBreakdown->count() > 0)
                    <div class="space-y-3">
                        @foreach($userBreakdown as $breakdown)
                        <div class="flex items-center justify-between">
                            <span class="text-base-content">{{ $breakdown->user ? $breakdown->user->full_name : 'System' }}</span>
                            <div class="flex items-center space-x-2">
                                <div class="w-32 bg-base-300 rounded-full h-2">
                                    @php
                                        $percentage = $stats['total_activities'] > 0 ? ($breakdown->count / $stats['total_activities']) * 100 : 0;
                                    @endphp
                                    <div class="bg-secondary h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                                <span class="text-sm text-base-content/70">{{ number_format($breakdown->count) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-base-content/50 text-center py-4">No user activity data available</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Activities List -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <div class="card-header p-6 border-b border-base-300">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-base-content">Recent Activities</h3>
                <p class="text-sm text-base-content/70">{{ $activities->total() }} total activities</p>
            </div>
        </div>
        <div class="card-body p-0">
            @if($activities->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Model</th>
                                <th>IP Address</th>
                                <th>Date</th>
                                @if(auth()->user()->isAdmin())
                                <th>Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activities as $activity)
                            <tr>
                                <td>
                                    @if($activity->user)
                                        <div class="flex items-center space-x-3">
                                            <div class="avatar">
                                                <div class="mask mask-squircle w-8 h-8">
                                                    @if($activity->user->profile_photo)
                                                        <img src="{{ Storage::url($activity->user->profile_photo) }}" alt="{{ $activity->user->full_name }}">
                                                    @else
                                                        <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-8 h-8">
                                                            <span class="text-xs font-semibold">{{ substr($activity->user->first_name, 0, 1) . substr($activity->user->last_name, 0, 1) }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium text-base-content">{{ $activity->user->full_name }}</div>
                                                <div class="text-sm text-base-content/70">{{ $activity->user->email }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-base-content/50">System</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $activity->action == 'create' ? 'success' : ($activity->action == 'update' ? 'info' : ($activity->action == 'delete' ? 'error' : 'warning')) }}">
                                        {{ ucfirst($activity->action) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="max-w-xs">
                                        <p class="text-sm text-base-content">{{ $activity->description }}</p>
                                    </div>
                                </td>
                                <td>
                                    @if($activity->model_type)
                                        <span class="text-sm text-base-content/70">{{ class_basename($activity->model_type) }}</span>
                                        @if($activity->model_id)
                                            <br><span class="text-xs text-base-content/50">ID: {{ $activity->model_id }}</span>
                                        @endif
                                    @else
                                        <span class="text-base-content/50">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm text-base-content/70">{{ $activity->ip_address ?? '-' }}</span>
                                </td>
                                <td>
                                    <div class="text-sm text-base-content">{{ $activity->created_at->format('M j, Y') }}</div>
                                    <div class="text-xs text-base-content/50">{{ $activity->created_at->format('g:i A') }}</div>
                                </td>
                                @if(auth()->user()->isAdmin())
                                <td>
                                    <a href="{{ route('activities.show', $activity) }}" class="btn btn-ghost btn-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Details
                                    </a>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="p-6 border-t border-base-300">
                    {{ $activities->appends(request()->query())->links() }}
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="w-16 h-16 mx-auto mb-4 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="text-base-content/70">No activities found matching your criteria.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 