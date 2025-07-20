@extends('layouts.app')

@section('title', 'Customer Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Welcome, {{ $client->first_name }}!</h1>
            <p class="text-base-content/70 mt-2">Manage your pool service locations and view your account overview</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('locations.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Add Location
            </a>

        </div>
    </div>

    <div class="space-y-6">

        <!-- Account Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <x-stat-card 
                title="Total Locations" 
                :value="$stats['total_locations']" 
                color="primary"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'></path><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 11a3 3 0 11-6 0 3 3 0 016 0z'></path>"
            />

            <x-stat-card 
                title="Total Invoices" 
                :value="$stats['total_invoices']" 
                color="warning"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
            />
            <x-stat-card 
                title="Unpaid Invoices" 
                :value="$stats['unpaid_invoices']" 
                color="error"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'></path>"
            />
            <x-stat-card 
                title="Total Reports" 
                :value="$stats['total_reports']" 
                color="secondary"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'></path>"
            />
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upcoming Appointments -->
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-base-content">Upcoming Appointments</h3>
                        <a href="{{ route('locations.index') }}" class="text-sm text-primary hover:text-primary/80">View All</a>
                    </div>
                </div>
                <div class="card-body p-6">
                    @if($upcomingAppointments->count() > 0)
                        <div class="space-y-4">
                            @foreach($upcomingAppointments as $location)
                            <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-base-content">{{ $location->full_address }}</p>
                                    <p class="text-sm text-base-content/70">
                                        Service: {{ $location->service_frequency }} - {{ $location->service_day_1 }}
                                    </p>
                                    <p class="text-xs text-base-content/50">
                                        Next: {{ $location->next_service_date ? $location->next_service_date->format('M d, Y') : 'TBD' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-success">
                                        Active
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 text-center py-4">No upcoming appointments</p>
                    @endif
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-base-content">Recent Invoices</h3>
                        <a href="{{ route('invoices.index') }}" class="text-sm text-primary hover:text-primary/80">View All</a>
                    </div>
                </div>
                <div class="card-body p-6">
                    @if($recentInvoices->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentInvoices as $invoice)
                            <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-base-content">#{{ $invoice->invoice_number }}</p>
                                    <p class="text-sm text-base-content/70">{{ $invoice->location->full_address }}</p>
                                    <p class="text-xs text-base-content/50">{{ $invoice->service_date->format('M d, Y') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-base-content">${{ number_format($invoice->total_amount, 2) }}</p>
                                    <span class="badge 
                                        @if($invoice->status === 'paid') badge-success
                                        @elseif($invoice->status === 'overdue') badge-error
                                        @elseif($invoice->status === 'sent') badge-info
                                        @else badge-neutral @endif">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 text-center py-4">No recent invoices</p>
                    @endif
                </div>
            </div>




        </div>
    </div>
</div>
@endsection 