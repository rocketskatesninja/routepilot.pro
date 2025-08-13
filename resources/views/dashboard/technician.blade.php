@extends('layouts.app')

@section('title', 'Technician Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-bold text-base-content">Technician Dashboard</h1>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-stat-card 
                title="Assigned Locations" 
                :value="$assignedLocations" 
                color="primary"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'></path>"
            />
            <x-stat-card 
                title="Recent Reports" 
                :value="$recentReports" 
                color="success"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'></path>"
            />
            <x-stat-card 
                title="Today's Schedule" 
                :value="$upcomingAppointments->count()" 
                color="secondary"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'></path>"
            />
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upcoming Appointments -->
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-base-content">Upcoming Appointments</h3>
                        <a href="#" class="text-sm text-primary hover:text-primary/80">View All</a>
                    </div>
                </div>
                <div class="card-body p-6">
                    @if($upcomingAppointments->count() > 0)
                        <div class="space-y-4">
                            @foreach($upcomingAppointments as $location)
                            <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-base-content">{{ $location->client->full_name }}</p>
                                    <p class="text-sm text-base-content/70">{{ $location->full_address }}</p>
                                    <p class="text-xs text-base-content/50">
                                        Service: {{ $location->service_frequency }} - {{ $location->service_day_1 }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <a href="{{ route('reports.create', ['location_id' => $location->id]) }}" 
                                       class="btn btn-sm btn-primary">
                                        Start Report
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 text-center py-4">No upcoming appointments</p>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <h3 class="text-lg font-medium text-base-content">Quick Actions</h3>
                </div>
                <div class="card-body p-6">
                    <div class="grid grid-cols-1 gap-4">
                        <a href="{{ route('chem-calc') }}" class="flex items-center p-4 bg-primary/10 rounded-lg hover:bg-primary/20 transition-colors">
                            <div class="p-2 rounded-full bg-primary/20">
                                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="font-medium text-base-content">Chemical Calculator</p>
                                <p class="text-sm text-base-content/70">Calculate chemical dosages</p>
                            </div>
                        </a>

                        <a href="{{ route('reports.create') }}" class="flex items-center p-4 bg-success/10 rounded-lg hover:bg-success/20 transition-colors">
                            <div class="p-2 rounded-full bg-success/20">
                                <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="font-medium text-base-content">Create Service Report</p>
                                <p class="text-sm text-base-content/70">Record service details</p>
                            </div>
                        </a>

                        {{-- <a href="{{ route('billing.create') }}" class="flex items-center p-4 bg-warning/10 rounded-lg hover:bg-warning/20 transition-colors">
                            <div class="p-2 rounded-full bg-warning/20">
                                <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="font-medium text-base-content">Create Invoice</p>
                                <p class="text-sm text-base-content/70">Bill for services</p>
                            </div>
                        </a> --}}

                        <a href="{{ route('clients.index') }}" class="flex items-center p-4 bg-secondary/10 rounded-lg hover:bg-secondary/20 transition-colors">
                            <div class="p-2 rounded-full bg-secondary/20">
                                <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="font-medium text-base-content">View Clients</p>
                                <p class="text-sm text-base-content/70">Manage client information</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- GPS Location Tracker -->
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="card-header p-6 border-b border-base-300">
                <h3 class="text-lg font-medium text-base-content">Location Sharing</h3>
            </div>
            <div class="card-body p-6">
                                        <x-gps-location-tracker :update-interval="60000" :show-status="true" />
            </div>
        </div>
    </div>
</div>
@endsection 