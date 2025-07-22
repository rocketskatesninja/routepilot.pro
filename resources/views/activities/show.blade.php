@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Activity Details</h1>
            <p class="text-base-content/70 mt-2">Detailed information about activity #{{ $activity->id }}</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('activities.index') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Activities
            </a>
        </div>
    </div>

    <!-- Activity Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Details -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-base-content">Activity Information</h3>
                        <span class="badge badge-{{ $activity->action == 'create' ? 'success' : ($activity->action == 'update' ? 'info' : ($activity->action == 'delete' ? 'error' : 'warning')) }}">
                            {{ ucfirst($activity->action) }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-6">
                    <div class="space-y-6">
                        <!-- Description -->
                        <div>
                            <h4 class="font-medium text-base-content mb-2">Description</h4>
                            <p class="text-base-content">{{ $activity->description }}</p>
                        </div>

                        <!-- User Information -->
                        <div>
                            <h4 class="font-medium text-base-content mb-2">User</h4>
                            @if($activity->user)
                                <div class="flex items-center space-x-3">
                                    <div class="avatar">
                                        <div class="mask mask-squircle w-10 h-10">
                                            @if($activity->user->profile_photo)
                                                <img src="{{ Storage::url($activity->user->profile_photo) }}" alt="{{ $activity->user->full_name }}">
                                            @else
                                                <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center w-10 h-10">
                                                    <span class="text-sm font-semibold">{{ substr($activity->user->first_name, 0, 1) . substr($activity->user->last_name, 0, 1) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-base-content">{{ $activity->user->full_name }}</div>
                                        <div class="text-sm text-base-content/70">{{ $activity->user->email }}</div>
                                        <div class="text-xs text-base-content/50">{{ ucfirst($activity->user->role) }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="text-base-content/50">System Activity</span>
                            @endif
                        </div>

                        <!-- Related Model -->
                        @if($activity->model_type)
                        <div>
                            <h4 class="font-medium text-base-content mb-2">Related Model</h4>
                            <div class="bg-base-200 rounded-lg p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-sm text-base-content/70">Model Type:</span>
                                        <p class="text-base-content">{{ class_basename($activity->model_type) }}</p>
                                    </div>
                                    @if($activity->model_id)
                                    <div>
                                        <span class="text-sm text-base-content/70">Model ID:</span>
                                        <p class="text-base-content">{{ $activity->model_id }}</p>
                                    </div>
                                    @endif
                                </div>
                                
                                @if($activity->subject)
                                <div class="mt-4 pt-4 border-t border-base-300">
                                    <h5 class="font-medium text-base-content mb-2">Model Details</h5>
                                    <div class="text-sm text-base-content/70">
                                        @if($activity->subject instanceof \App\Models\Client)
                                            <p><strong>Client:</strong> {{ $activity->subject->full_name }}</p>
                                            <p><strong>Email:</strong> {{ $activity->subject->email }}</p>
                                        @elseif($activity->subject instanceof \App\Models\Location)
                                            <p><strong>Location:</strong> {{ $activity->subject->nickname ?? $activity->subject->full_address }}</p>
                                            <p><strong>Address:</strong> {{ $activity->subject->full_address }}</p>
                                        @elseif($activity->subject instanceof \App\Models\User)
                                            <p><strong>User:</strong> {{ $activity->subject->full_name }}</p>
                                            <p><strong>Email:</strong> {{ $activity->subject->email }}</p>
                                        @elseif($activity->subject instanceof \App\Models\Invoice)
                                            <p><strong>Invoice:</strong> {{ $activity->subject->invoice_number }}</p>
                                            <p><strong>Amount:</strong> ${{ number_format($activity->subject->total_amount, 2) }}</p>
                                        @elseif($activity->subject instanceof \App\Models\Report)
                                            <p><strong>Report:</strong> #{{ $activity->subject->id }}</p>
                                            <p><strong>Date:</strong> {{ $activity->subject->service_date->format('M j, Y') }}</p>
                                        @else
                                            <p>Model data available but display not configured</p>
                                        @endif
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Properties -->
                        @if($activity->properties && count($activity->properties) > 0)
                        <div>
                            <h4 class="font-medium text-base-content mb-2">Additional Properties</h4>
                            <div class="bg-base-200 rounded-lg p-4">
                                <pre class="text-sm text-base-content overflow-x-auto">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Timestamp Information -->
            <div class="card bg-base-100 shadow-xl border border-base-300 mb-6">
                <div class="card-header p-6 border-b border-base-300">
                    <h3 class="text-lg font-medium text-base-content">Timestamps</h3>
                </div>
                <div class="card-body p-6">
                    <div class="space-y-4">
                        <div>
                            <span class="text-sm text-base-content/70">Created:</span>
                            <p class="text-base-content">{{ $activity->created_at->format('M j, Y g:i A') }}</p>
                            <p class="text-xs text-base-content/50">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                        
                        @if($activity->updated_at != $activity->created_at)
                        <div>
                            <span class="text-sm text-base-content/70">Last Updated:</span>
                            <p class="text-base-content">{{ $activity->updated_at->format('M j, Y g:i A') }}</p>
                            <p class="text-xs text-base-content/50">{{ $activity->updated_at->diffForHumans() }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Technical Information -->
            <div class="card bg-base-100 shadow-xl border border-base-300 mb-6">
                <div class="card-header p-6 border-b border-base-300">
                    <h3 class="text-lg font-medium text-base-content">Technical Details</h3>
                </div>
                <div class="card-body p-6">
                    <div class="space-y-4">
                        <div>
                            <span class="text-sm text-base-content/70">Activity ID:</span>
                            <p class="text-base-content font-mono">{{ $activity->id }}</p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-base-content/70">Action:</span>
                            <p class="text-base-content">{{ $activity->action }}</p>
                        </div>
                        
                        @if($activity->ip_address)
                        <div>
                            <span class="text-sm text-base-content/70">IP Address:</span>
                            <p class="text-base-content font-mono">{{ $activity->ip_address }}</p>
                        </div>
                        @endif
                        
                        @if($activity->user_agent)
                        <div>
                            <span class="text-sm text-base-content/70">User Agent:</span>
                            <p class="text-xs text-base-content/70 break-all">{{ $activity->user_agent }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Related Activities -->
            @if($activity->user_id)
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <h3 class="text-lg font-medium text-base-content">Recent Activities by Same User</h3>
                </div>
                <div class="card-body p-6">
                    @php
                        $recentActivities = \App\Models\Activity::where('user_id', $activity->user_id)
                            ->where('id', '!=', $activity->id)
                            ->latest()
                            ->take(5)
                            ->get();
                    @endphp
                    
                    @if($recentActivities->count() > 0)
                        <div class="space-y-3">
                            @foreach($recentActivities as $recentActivity)
                            <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
                                <div class="flex-1">
                                    <p class="text-sm text-base-content">{{ Str::limit($recentActivity->description, 50) }}</p>
                                    <p class="text-xs text-base-content/50">{{ $recentActivity->created_at->diffForHumans() }}</p>
                                </div>
                                <a href="{{ route('activities.show', $recentActivity) }}" class="btn btn-ghost btn-xs">
                                    View
                                </a>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 text-center py-4">No other activities by this user</p>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection 