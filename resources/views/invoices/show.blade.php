<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Invoice Details
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('invoices.pdf', $invoice) }}" 
                   class="btn btn-primary btn-sm">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </a>
                <a href="{{ route('invoices.pdf.view', $invoice) }}" 
                   target="_blank"
                   class="btn btn-outline btn-sm">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View PDF
                </a>
                <a href="{{ route('invoices.edit', $invoice) }}" 
                   class="btn btn-outline btn-sm">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <!-- Invoice Header -->
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-primary">Invoice {{ $invoice->invoice_number }}</h1>
                            <p class="text-gray-600 dark:text-gray-400">Created {{ $invoice->created_at->format('M d, Y') }}</p>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'error' : 'info') }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Invoice Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        
                        <!-- Client Information -->
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Client Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-semibold">Name:</span>
                                        <span>{{ $invoice->client->full_name }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Email:</span>
                                        <span>{{ $invoice->client->email }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Phone:</span>
                                        <span>{{ $invoice->client->phone }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Address:</span>
                                        <span>{{ $invoice->client->address }}</span>
                                    </div>
                                    @if($invoice->client->city && $invoice->client->state)
                                    <div>
                                        <span class="font-semibold">City/State:</span>
                                        <span>{{ $invoice->client->city }}, {{ $invoice->client->state }} {{ $invoice->client->zip_code }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Service Information -->
                        <div class="card bg-base-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Service Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-semibold">Location:</span>
                                        <span>{{ $invoice->location->name }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Technician:</span>
                                        <span>{{ $invoice->technician->full_name }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Service Date:</span>
                                        <span>{{ $invoice->service_date->format('M d, Y') }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Due Date:</span>
                                        <span>{{ $invoice->due_date->format('M d, Y') }}</span>
                                    </div>
                                    @if($invoice->paid_at)
                                    <div>
                                        <span class="font-semibold">Paid Date:</span>
                                        <span>{{ $invoice->paid_at->format('M d, Y') }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Details -->
                    <div class="card bg-base-100 shadow-sm mb-6">
                        <div class="card-body">
                            <h3 class="card-title text-lg">Service Details</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Details</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Pool Service Visit</td>
                                            <td>
                                                <strong>Location:</strong> {{ $invoice->location->name }}<br>
                                                <strong>Technician:</strong> {{ $invoice->technician->full_name }}<br>
                                                <strong>Service Date:</strong> {{ $invoice->service_date->format('M d, Y') }}
                                            </td>
                                            <td class="text-right font-bold">${{ number_format($invoice->rate_per_visit, 2) }}</td>
                                        </tr>
                                        
                                        @if($invoice->chemicals_included && $invoice->chemicals_cost > 0)
                                        <tr>
                                            <td>Chemicals & Supplies</td>
                                            <td>Pool chemicals and maintenance supplies included in service</td>
                                            <td class="text-right font-bold">${{ number_format($invoice->chemicals_cost, 2) }}</td>
                                        </tr>
                                        @endif
                                        
                                        @if($invoice->extras_cost > 0)
                                        <tr>
                                            <td>Additional Services</td>
                                            <td>Extra services and materials</td>
                                            <td class="text-right font-bold">${{ number_format($invoice->extras_cost, 2) }}</td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="card bg-base-100 shadow-sm mb-6">
                        <div class="card-body">
                            <h3 class="card-title text-lg">Payment Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Rate per Visit:</span>
                                        <span class="font-bold">${{ number_format($invoice->rate_per_visit, 2) }}</span>
                                    </div>
                                    
                                    @if($invoice->chemicals_included && $invoice->chemicals_cost > 0)
                                    <div class="flex justify-between">
                                        <span>Chemicals & Supplies:</span>
                                        <span class="font-bold">${{ number_format($invoice->chemicals_cost, 2) }}</span>
                                    </div>
                                    @endif
                                    
                                    @if($invoice->extras_cost > 0)
                                    <div class="flex justify-between">
                                        <span>Additional Services:</span>
                                        <span class="font-bold">${{ number_format($invoice->extras_cost, 2) }}</span>
                                    </div>
                                    @endif
                                    
                                    <div class="divider"></div>
                                    
                                    <div class="flex justify-between text-lg font-bold text-primary">
                                        <span>Total Amount:</span>
                                        <span>${{ number_format($invoice->total_amount, 2) }}</span>
                                    </div>
                                    
                                    @if($invoice->status !== 'paid')
                                    <div class="flex justify-between text-lg font-bold text-error">
                                        <span>Balance Due:</span>
                                        <span>${{ number_format($invoice->balance, 2) }}</span>
                                    </div>
                                    @endif
                                </div>
                                
                                @if($invoice->status !== 'paid')
                                <div class="card bg-base-200">
                                    <div class="card-body">
                                        <h4 class="card-title text-md">Record Payment</h4>
                                        <form action="{{ route('invoices.record-payment', $invoice) }}" method="POST" class="space-y-3">
                                            @csrf
                                            <div class="form-control">
                                                <label class="label">
                                                    <span class="label-text">Payment Amount</span>
                                                </label>
                                                <input type="number" name="payment_amount" step="0.01" min="0.01" max="{{ $invoice->balance }}" 
                                                       value="{{ $invoice->balance }}" class="input input-bordered" required>
                                            </div>
                                            <div class="form-control">
                                                <label class="label">
                                                    <span class="label-text">Payment Notes (Optional)</span>
                                                </label>
                                                <textarea name="payment_notes" class="textarea textarea-bordered" rows="2"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm w-full">Record Payment</button>
                                        </form>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($invoice->notes)
                    <div class="card bg-base-100 shadow-sm mb-6">
                        <div class="card-body">
                            <h3 class="card-title text-lg">Notes</h3>
                            <p class="text-gray-700 dark:text-gray-300">{{ $invoice->notes }}</p>
                        </div>
                    </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex justify-between items-center">
                        <a href="{{ route('invoices.index') }}" class="btn btn-outline">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to Invoices
                        </a>
                        
                        <div class="flex gap-2">
                            @if($invoice->status !== 'paid')
                            <form action="{{ route('invoices.mark-paid', $invoice) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Mark as Paid
                                </button>
                            </form>
                            @endif
                            
                            <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline" 
                                  onsubmit="return confirm('Are you sure you want to delete this invoice?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error btn-sm">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 