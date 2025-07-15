<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Profile: {{ $profile->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('recurring-billing-profiles.edit', $profile) }}" class="btn btn-outline">Edit</a>
                <a href="{{ route('recurring-billing-profiles.index') }}" class="btn btn-ghost">Back</a>
            </div>
        </div>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Profile Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div><span class="font-semibold">Name:</span> {{ $profile->name }}</div>
                            <div><span class="font-semibold">Description:</span> {{ $profile->description }}</div>
                            <div><span class="font-semibold">Client:</span> {{ $profile->client->full_name ?? '-' }}</div>
                            <div><span class="font-semibold">Location:</span> {{ $profile->location->name ?? '-' }}</div>
                            <div><span class="font-semibold">Technician:</span> {{ $profile->technician->full_name ?? '-' }}</div>
                        </div>
                        <div>
                            <div><span class="font-semibold">Frequency:</span> {{ ucfirst($profile->frequency) }}</div>
                            <div><span class="font-semibold">Start Date:</span> {{ $profile->start_date ? $profile->start_date->format('M d, Y') : '-' }}</div>
                            <div><span class="font-semibold">End Date:</span> {{ $profile->end_date ? $profile->end_date->format('M d, Y') : '-' }}</div>
                            <div><span class="font-semibold">Next Billing:</span> {{ $profile->next_billing_date ? $profile->next_billing_date->format('M d, Y') : '-' }}</div>
                            <div><span class="font-semibold">Status:</span> <span class="badge badge-{{ $profile->status === 'active' ? 'success' : ($profile->status === 'paused' ? 'warning' : 'neutral') }}">{{ ucfirst($profile->status) }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Billing Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div><span class="font-semibold">Rate Per Visit:</span> ${{ number_format($profile->rate_per_visit, 2) }}</div>
                            <div><span class="font-semibold">Chemicals Cost:</span> ${{ number_format($profile->chemicals_cost, 2) }}</div>
                            <div><span class="font-semibold">Chemicals Included:</span> {{ $profile->chemicals_included ? 'Yes' : 'No' }}</div>
                            <div><span class="font-semibold">Extras Cost:</span> ${{ number_format($profile->extras_cost, 2) }}</div>
                        </div>
                        <div>
                            <div><span class="font-semibold">Advance Notice Days:</span> {{ $profile->advance_notice_days }}</div>
                            <div><span class="font-semibold">Auto Generate Invoices:</span> {{ $profile->auto_generate_invoices ? 'Yes' : 'No' }}</div>
                            <div><span class="font-semibold">Invoices Generated:</span> {{ $profile->invoices_generated }}</div>
                            <div><span class="font-semibold">Total Amount Generated:</span> ${{ number_format($profile->total_amount_generated, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-bold mb-2">Generated Invoices</h3>
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Service Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($profile->invoices as $invoice)
                                    <tr>
                                        <td>{{ $invoice->invoice_number }}</td>
                                        <td>{{ $invoice->service_date ? $invoice->service_date->format('M d, Y') : '-' }}</td>
                                        <td>{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '-' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'error' : 'info') }}">{{ ucfirst($invoice->status) }}</span>
                                        </td>
                                        <td>${{ number_format($invoice->total_amount, 2) }}</td>
                                        <td>
                                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-xs btn-outline">View</a>
                                            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-xs btn-primary">PDF</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-8">No invoices generated yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 