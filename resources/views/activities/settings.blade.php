@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Activity Retention Settings</h1>
            <p class="text-base-content/70 mt-2">Manage activity log retention and cleanup policies</p>
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
                        <p class="text-sm text-base-content/70">Older than 30 days</p>
                        <p class="text-2xl font-bold text-base-content">{{ number_format($stats['activities_older_than_30_days']) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-warning/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <p class="text-sm text-base-content/70">Older than 90 days</p>
                        <p class="text-2xl font-bold text-base-content">{{ number_format($stats['activities_older_than_90_days']) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-error/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-body p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-base-content/70">Older than 1 year</p>
                        <p class="text-2xl font-bold text-base-content">{{ number_format($stats['activities_older_than_365_days']) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-error/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Retention Settings -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Current Settings -->
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-header p-6 border-b border-base-300">
                <h3 class="text-lg font-medium text-base-content">Current Retention Settings</h3>
            </div>
            <div class="card-body p-6">
                <form method="POST" action="{{ route('activities.update-settings') }}">
                    @csrf
                    
                    <div class="space-y-6">
                        <div>
                            <label for="retention_days" class="block text-sm font-medium text-base-content mb-2">
                                Retention Period (days)
                            </label>
                            <input type="number" name="retention_days" id="retention_days" 
                                   value="{{ $retentionDays }}" min="1" max="3650"
                                   class="input input-bordered w-full @error('retention_days') input-error @enderror">
                            <p class="text-sm text-base-content/70 mt-1">
                                Activities older than this number of days will be automatically filtered out.
                            </p>
                            @error('retention_days')
                                <p class="text-error text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" name="auto_cleanup" value="1" 
                                       {{ $autoCleanup ? 'checked' : '' }}
                                       class="checkbox">
                                <span class="text-sm font-medium text-base-content">Enable Automatic Cleanup</span>
                            </label>
                            <p class="text-sm text-base-content/70 mt-1">
                                Automatically delete activities older than the retention period.
                            </p>
                        </div>
                        
                        <div class="alert alert-info">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="font-medium">Configuration Note</h4>
                                <p class="text-sm">These settings are stored in your application configuration. You may need to update your config files to apply changes permanently.</p>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manual Cleanup -->
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-header p-6 border-b border-base-300">
                <h3 class="text-lg font-medium text-base-content">Manual Cleanup</h3>
            </div>
            <div class="card-body p-6">
                <div class="space-y-6">
                    <div class="alert alert-warning">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div>
                            <h4 class="font-medium">Warning</h4>
                            <p class="text-sm">Manual cleanup will permanently delete activities. This action cannot be undone.</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                            <div>
                                <h4 class="font-medium text-base-content">Cleanup 30+ days old</h4>
                                <p class="text-sm text-base-content/70">{{ number_format($stats['activities_older_than_30_days']) }} activities</p>
                            </div>
                            <a href="{{ route('activities.cleanup', ['days' => 30]) }}" class="btn btn-warning btn-sm">
                                Cleanup
                            </a>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                            <div>
                                <h4 class="font-medium text-base-content">Cleanup 90+ days old</h4>
                                <p class="text-sm text-base-content/70">{{ number_format($stats['activities_older_than_90_days']) }} activities</p>
                            </div>
                            <a href="{{ route('activities.cleanup', ['days' => 90]) }}" class="btn btn-warning btn-sm">
                                Cleanup
                            </a>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                            <div>
                                <h4 class="font-medium text-base-content">Cleanup 1+ year old</h4>
                                <p class="text-sm text-base-content/70">{{ number_format($stats['activities_older_than_365_days']) }} activities</p>
                            </div>
                            <a href="{{ route('activities.cleanup', ['days' => 365]) }}" class="btn btn-error btn-sm">
                                Cleanup
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="card bg-base-100 shadow-xl border border-base-300 mt-8">
        <div class="card-header p-6 border-b border-base-300">
            <h3 class="text-lg font-medium text-base-content">Activity Timeline</h3>
        </div>
        <div class="card-body p-6">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-success rounded-full"></div>
                        <span class="text-sm text-base-content">Current Retention Period</span>
                    </div>
                    <span class="text-sm text-base-content/70">{{ $retentionDays }} days</span>
                </div>
                
                @if($stats['oldest_activity'])
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-info rounded-full"></div>
                        <span class="text-sm text-base-content">Oldest Activity</span>
                    </div>
                    <span class="text-sm text-base-content/70">{{ \Carbon\Carbon::parse($stats['oldest_activity'])->format('M j, Y') }}</span>
                </div>
                @endif
                
                @if($stats['newest_activity'])
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-primary rounded-full"></div>
                        <span class="text-sm text-base-content">Newest Activity</span>
                    </div>
                    <span class="text-sm text-base-content/70">{{ \Carbon\Carbon::parse($stats['newest_activity'])->format('M j, Y') }}</span>
                </div>
                @endif
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-warning rounded-full"></div>
                        <span class="text-sm text-base-content">Activities at Risk</span>
                    </div>
                    <span class="text-sm text-base-content/70">{{ number_format($stats['activities_older_than_30_days']) }} (30+ days)</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 