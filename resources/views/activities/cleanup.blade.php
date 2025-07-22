@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Activity Cleanup</h1>
            <p class="text-base-content/70 mt-2">Confirm deletion of old activities</p>
        </div>
        <div class="mt-4 lg:mt-0 flex space-x-2">
            <a href="{{ route('activities.settings') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Settings
            </a>
        </div>
    </div>

    <!-- Warning Alert -->
    <div class="alert alert-error mb-8">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
        </svg>
        <div>
            <h4 class="font-medium">Warning: Irreversible Action</h4>
            <p class="text-sm">This action will permanently delete activities older than {{ $retentionDays }} days. This cannot be undone.</p>
        </div>
    </div>

    <!-- Cleanup Details -->
    <div class="card bg-base-100 shadow-xl border border-base-300 mb-8">
        <div class="card-header p-6 border-b border-base-300">
            <h3 class="text-lg font-medium text-base-content">Cleanup Details</h3>
        </div>
        <div class="card-body p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-base-content mb-4">What will be deleted:</h4>
                    <ul class="space-y-2 text-sm text-base-content/70">
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>Activities older than {{ $retentionDays }} days</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>{{ number_format($activitiesToDelete) }} activities total</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span>All associated metadata and properties</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-base-content mb-4">What will be preserved:</h4>
                    <ul class="space-y-2 text-sm text-base-content/70">
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Activities newer than {{ $retentionDays }} days</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>All other system data</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>User accounts and permissions</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Form -->
    <div class="card bg-base-100 shadow-xl border border-base-300">
        <div class="card-header p-6 border-b border-base-300">
            <h3 class="text-lg font-medium text-base-content">Confirm Cleanup</h3>
        </div>
        <div class="card-body p-6">
            <form method="POST" action="{{ route('activities.cleanup') }}">
                @csrf
                <input type="hidden" name="days" value="{{ $retentionDays }}">
                
                <div class="space-y-6">
                    <div class="alert alert-info">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="font-medium">Before proceeding:</h4>
                            <ul class="text-sm mt-2 space-y-1">
                                <li>• Consider exporting activities first if you need a backup</li>
                                <li>• Ensure no critical audit requirements depend on these activities</li>
                                <li>• Verify that {{ $retentionDays }} days is an appropriate retention period</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" id="confirm_cleanup" name="confirm_cleanup" value="1" 
                               class="checkbox checkbox-error" required>
                        <label for="confirm_cleanup" class="text-sm font-medium text-base-content">
                            I understand that this action will permanently delete {{ number_format($activitiesToDelete) }} activities and cannot be undone.
                        </label>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="btn btn-error" disabled id="cleanup-btn">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete {{ number_format($activitiesToDelete) }} Activities
                        </button>
                        
                        <a href="{{ route('activities.settings') }}" class="btn btn-outline">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('confirm_cleanup');
    const button = document.getElementById('cleanup-btn');
    
    checkbox.addEventListener('change', function() {
        button.disabled = !this.checked;
    });
});
</script>
@endsection 