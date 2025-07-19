@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Admin Dashboard</h1>
            <p class="text-base-content/70 mt-2">Overview of your pool service business</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('clients.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Client
            </a>
            {{-- <a href="{{ route('reports.create') }}" class="btn-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                New Report
            </a> --}}
        </div>
    </div>

    <div class="space-y-6">
        <!-- General Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <x-stat-card 
                title="Total Clients" 
                :value="$stats['total_clients']" 
                color="primary"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'></path>"
            />
            <x-stat-card 
                title="Active Locations" 
                :value="$stats['active_locations']" 
                color="success"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'></path>"
            />
            <x-stat-card 
                title="Unpaid Invoices" 
                :value="$stats['unpaid_invoices']" 
                color="warning"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
            />
            <x-stat-card 
                title="Recent Reports" 
                :value="$stats['recent_reports']" 
                color="secondary"
                icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'></path>"
            />
        </div>

        <!-- Main Content Grid -->
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
                        <a href="#" class="text-sm text-primary hover:text-primary/80">View All</a>
                    </div>
                </div>
                <div class="card-body p-6">
                    @if($recentInvoices->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentInvoices as $invoice)
                            <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-base-content">#{{ $invoice->invoice_number }}</p>
                                    <p class="text-sm text-base-content/70">{{ $invoice->client->full_name }}</p>
                                    <p class="text-xs text-base-content/50">{{ $invoice->service_date->format('M d, Y') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-base-content">${{ number_format($invoice->total_amount, 2) }}</p>
                                    <span class="badge 
                                        @if($invoice->status === 'paid') badge-success
                                        @elseif($invoice->status === 'overdue') badge-error
                                        @else badge-warning @endif">
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

            <!-- Recent Reports -->
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-base-content">Recent Reports</h3>
                        <a href="#" class="text-sm text-primary hover:text-primary/80">View All</a>
                    </div>
                </div>
                <div class="card-body p-6">
                    @if($recentReports->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentReports as $report)
                            <div class="flex items-center justify-between p-4 bg-base-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-base-content">{{ $report->client->full_name }}</p>
                                    <p class="text-sm text-base-content/70">{{ $report->location->full_address }}</p>
                                    <p class="text-xs text-base-content/50">{{ $report->service_date->format('M d, Y') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-base-content/70">{{ $report->technician->full_name }}</p>
                                    <span class="badge badge-info">
                                        Completed
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 text-center py-4">No recent reports</p>
                    @endif
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card bg-base-100 shadow-xl border border-base-300">
                <div class="card-header p-6 border-b border-base-300">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-base-content">Recent Activities</h3>
                        <a href="#" class="text-sm text-primary hover:text-primary/80">View All</a>
                    </div>
                </div>
                <div class="card-body p-6">
                    @if($recentActivities->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentActivities as $activity)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-base-300 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-base-content/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-base-content">{{ $activity->description }}</p>
                                    <p class="text-xs text-base-content/50">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-base-content/50 text-center py-4">No recent activities</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 