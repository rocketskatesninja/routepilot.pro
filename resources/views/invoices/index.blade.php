@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Invoices</h1>
            <p class="text-base-content/70 mt-2">Manage your pool service invoices and payments</p>
        </div>
        <div class="flex space-x-2">
            <a href="{{ route('invoices.statistics') }}" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Statistics
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Invoice
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 mt-8">
        <x-stat-card 
            title="Total Invoices" 
            :value="$invoices->total()" 
            color="primary"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'></path>"
        />
        <x-stat-card 
            title="Pending" 
            :value="$invoices->where('status', 'sent')->count()" 
            color="warning"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
        <x-stat-card 
            title="Paid" 
            :value="$invoices->where('status', 'paid')->count()" 
            color="success"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
        <x-stat-card 
            title="Overdue" 
            :value="$invoices->where('status', 'overdue')->count()" 
            color="error"
            icon="<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>"
        />
    </div>

    <!-- Search and Filters -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <form method="GET" action="{{ route('invoices.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by invoice #, client, or location" class="input input-bordered w-full">
                    </div>
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Status</label>
                        <select name="status" class="select select-bordered w-full">
                            <option value="">All Status</option>
                            <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <!-- Date From -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="input input-bordered w-full">
                    </div>
                    <!-- Date To -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="input input-bordered w-full">
                    </div>
                    <!-- Sort By -->
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-2">Sort By</label>
                        <select name="sort_by" class="select select-bordered w-full">
                            <option value="date_desc" {{ request('sort_by') == 'date_desc' ? 'selected' : '' }}>Date (Newest)</option>
                            <option value="date_asc" {{ request('sort_by') == 'date_asc' ? 'selected' : '' }}>Date (Oldest)</option>
                            <option value="status" {{ request('sort_by') == 'status' ? 'selected' : '' }}>Status</option>
                            <option value="amount" {{ request('sort_by') == 'amount' ? 'selected' : '' }}>Amount</option>
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
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline">Clear Filters</a>
                    <a href="{{ route('invoices.export') }}?{{ http_build_query(request()->all()) }}" class="btn btn-secondary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="bg-base-100 shadow-xl rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-base-200 text-base-content">
                    <tr>
                        <th>Invoice</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Service Date</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td>
                            <div class="font-bold text-base-content">{{ $invoice->invoice_number }}</div>
                            <div class="text-sm opacity-50">{{ $invoice->technician->full_name ?? '' }}</div>
                        </td>
                        <td>
                            <div class="flex items-center space-x-3">
                                <div class="avatar">
                                    <div class="mask mask-squircle w-12 h-12">
                                        @if($invoice->client && $invoice->client->profile_photo)
                                            <img src="{{ Storage::url($invoice->client->profile_photo) }}" alt="{{ $invoice->client->full_name }}">
                                        @else
                                            <div class="bg-primary text-primary-content rounded-lg flex items-center justify-center">
                                                <span class="text-lg font-semibold">{{ $invoice->client ? substr($invoice->client->first_name, 0, 1) . substr($invoice->client->last_name, 0, 1) : '?' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="font-bold text-base-content">{{ $invoice->client->full_name ?? '-' }}</div>
                                    <div class="text-sm opacity-50">{{ $invoice->client->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $invoice->location->name ?? '-' }}</div>
                                <div class="text-base-content/70">{{ $invoice->location->city ?? '' }}, {{ $invoice->location->state ?? '' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="text-base-content">{{ $invoice->service_date ? $invoice->service_date->format('M d, Y') : '-' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="text-sm">
                                <div class="{{ $invoice->isOverdue() ? 'text-red-600 font-semibold' : 'text-base-content' }}">
                                    {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '-' }}
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="font-bold text-base-content">${{ number_format($invoice->total_amount, 2) }}</div>
                        </td>
                        <td>
                            <div class="font-bold {{ $invoice->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                ${{ number_format($invoice->balance, 2) }}
                            </div>
                        </td>
                        <td>
                            @if($invoice->status === 'paid')
                                <span class="badge badge-success">Paid</span>
                            @elseif($invoice->status === 'sent')
                                <span class="badge badge-warning">Sent</span>
                            @elseif($invoice->status === 'overdue')
                                <span class="badge badge-error">Overdue</span>
                            @else
                                <span class="badge badge-neutral">{{ ucfirst($invoice->status) }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-ghost btn-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                    </svg>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                                    <li><a href="{{ route('invoices.show', $invoice) }}">View Details</a></li>
                                    <li><a href="{{ route('invoices.edit', $invoice) }}">Edit</a></li>
                                    <li><a href="{{ route('invoices.pdf', $invoice) }}">Download PDF</a></li>
                                    <li><a href="{{ route('invoices.pdf.view', $invoice) }}" target="_blank">View PDF</a></li>
                                    <li>
                                        <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this invoice?')">
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
                        <td colspan="9" class="text-center py-8">
                            <div class="text-base-content/70">
                                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <p class="text-lg font-medium">No invoices found</p>
                                <p class="text-sm">Get started by adding your first invoice.</p>
                                <a href="{{ route('invoices.create') }}" class="btn btn-primary mt-4">Add First Invoice</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="p-4 border-t border-base-200">
            {{ $invoices->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

</div>
@endsection 