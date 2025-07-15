<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Create Recurring Billing Profile
        </h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('recurring-billing-profiles.store') }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">Name</label>
                            <input type="text" name="name" class="input input-bordered w-full" required>
                        </div>
                        <div>
                            <label class="label">Description</label>
                            <input type="text" name="description" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Client</label>
                            <select name="client_id" class="select select-bordered w-full" required>
                                <option value="">Select Client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <select name="location_id" class="select select-bordered w-full" required>
                                <option value="">Select Location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Technician</label>
                            <select name="technician_id" class="select select-bordered w-full" required>
                                <option value="">Select Technician</option>
                                @foreach($technicians as $technician)
                                    <option value="{{ $technician->id }}">{{ $technician->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Rate Per Visit</label>
                            <input type="number" name="rate_per_visit" step="0.01" min="0" class="input input-bordered w-full" required>
                        </div>
                        <div>
                            <label class="label">Chemicals Cost</label>
                            <input type="number" name="chemicals_cost" step="0.01" min="0" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Chemicals Included</label>
                            <input type="checkbox" name="chemicals_included" value="1" class="checkbox">
                        </div>
                        <div>
                            <label class="label">Extras Cost</label>
                            <input type="number" name="extras_cost" step="0.01" min="0" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Frequency</label>
                            <select name="frequency" class="select select-bordered w-full" required>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Biweekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Frequency Value (for custom)</label>
                            <input type="number" name="frequency_value" min="1" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Start Date</label>
                            <input type="date" name="start_date" class="input input-bordered w-full" required>
                        </div>
                        <div>
                            <label class="label">End Date</label>
                            <input type="date" name="end_date" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Day of Week (1=Mon, 7=Sun)</label>
                            <input type="number" name="day_of_week" min="1" max="7" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Day of Month</label>
                            <input type="number" name="day_of_month" min="1" max="31" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Advance Notice Days</label>
                            <input type="number" name="advance_notice_days" min="0" class="input input-bordered w-full" value="7">
                        </div>
                        <div>
                            <label class="label">Status</label>
                            <select name="status" class="select select-bordered w-full">
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Auto Generate Invoices</label>
                            <input type="checkbox" name="auto_generate_invoices" value="1" class="checkbox" checked>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="btn btn-primary">Create Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout> 