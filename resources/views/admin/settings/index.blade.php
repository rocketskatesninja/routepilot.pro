@extends('layouts.app')

@section('title', 'Admin Settings')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-base-content mb-2">Admin Settings</h1>
        <p class="text-base-content/70">Manage system configuration and preferences</p>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-8">
        <div class="flex space-x-8">
            <a href="{{ route('admin.settings.index', ['tab' => 'general']) }}" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ $activeTab === 'general' ? 'border-primary text-base-content' : 'border-transparent text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300' }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                General
            </a>
            <a href="{{ route('admin.settings.index', ['tab' => 'database']) }}" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ $activeTab === 'database' ? 'border-primary text-base-content' : 'border-transparent text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300' }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
                Database
            </a>
            <a href="{{ route('admin.settings.index', ['tab' => 'mail']) }}" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ $activeTab === 'mail' ? 'border-primary text-base-content' : 'border-transparent text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300' }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Mail
            </a>
            <a href="{{ route('admin.settings.index', ['tab' => 'security']) }}" 
               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ $activeTab === 'security' ? 'border-primary text-base-content' : 'border-transparent text-base-content/70 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300' }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                Security
            </a>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="bg-base-100 rounded-lg shadow-lg p-6">
        @if($activeTab === 'general')
            @include('admin.settings.partials.general')
        @elseif($activeTab === 'database')
            @include('admin.settings.partials.database')
        @elseif($activeTab === 'mail')
            @include('admin.settings.partials.mail')
        @elseif($activeTab === 'security')
            @include('admin.settings.partials.security')
        @endif
    </div>
</div>

@push('scripts')
<script>
// Handle backup creation
function createBackup() {
    const button = document.getElementById('create-backup-btn');
    const originalText = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Creating backup...';
    
    fetch('{{ route("admin.settings.backup") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Backup created successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Backup failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Backup failed: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

// Handle mail testing
function testMail() {
    const email = document.getElementById('test_email').value;
    if (!email) {
        showNotification('Please enter an email address', 'error');
        return;
    }
    
    const button = document.getElementById('test-mail-btn');
    const originalText = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Sending test...';
    
    fetch('{{ route("admin.settings.test-mail") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ test_email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Test email sent successfully!', 'success');
        } else {
            showNotification('Mail test failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Mail test failed: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fixed top-4 right-4 z-50 max-w-sm`;
    notification.innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}
</script>
@endpush
@endsection 