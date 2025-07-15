<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit Recurring Billing Profile
        </h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('recurring-billing-profiles.update', $profile) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">Name</label>
                            <input type="text" name="name" class="input input-bordered w-full" value="{{ old('name', $profile->name) }}" required>
                        </div>
                        <div>
                            <label class="label">Description</label>
                            <input type="text" name="description" class="input input-bordered w-full" value="{{ old('description', $profile->description) }}">
                        </div>
                        <div>
                            <label class="label">Client</label>
                            <select name="client_id" class="select select-bordered w-full" required>
                                <option value="">Select Client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ $profile->client_id == $client->id ? 'selected' : '' }}>{{ $client->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <select name="location_id" class="select select-bordered w-full" required>
                                <option value="">Select Location</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ $profile->location_id == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Technician</label>
                            <select name="technician_id" class="select select-bordered w-full" required>
                                <option value="">Select Technician</option>
                                @foreach($technicians as $technician)
                                    <option value="{{ $technician->id }}" {{ $profile->technician_id == $technician->id ? 'selected' : '' }}>{{ $technician->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Rate Per Visit</label>
                            <input type="number" name="rate_per_visit" step="0.01" min="0" class="input input-bordered w-full" value="{{ old('rate_per_visit', $profile->rate_per_visit) }}" required>
                        </div>
                        <div>
                            <label class="label">Chemicals Cost</label>
                            <input type="number" name="chemicals_cost" step="0.01" min="0" class="input input-bordered w-full" value="{{ old('chemicals_cost', $profile->chemicals_cost) }}">
                        </div>
                        <div>
                            <label class="label">Chemicals Included</label>
                            <input type="checkbox" name="chemicals_included" value="1" class="checkbox" {{ $profile->chemicals_included ? 'checked' : '' }}>
                        </div>
                        <div>
                            <label class="label">Extras Cost</label>
                            <input type="number" name="extras_cost" step="0.01" min="0" class="input input-bordered w-full" value="{{ old('extras_cost', $profile->extras_cost) }}">
                        </div>
                        <div>
                            <label class="label">Frequency</label>
                            <select name="frequency" class="select select-bordered w-full" required>
                                <option value="weekly" {{ $profile->frequency == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="biweekly" {{ $profile->frequency == 'biweekly' ? 'selected' : '' }}>Biweekly</option>
                                <option value="monthly" {{ $profile->frequency == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="quarterly" {{ $profile->frequency == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                <option value="custom" {{ $profile->frequency == 'custom' ? 'selected' : '' }}>Custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Frequency Value (for custom)</label>
                            <input type="number" name="frequency_value" min="1" class="input input-bordered w-full" value="{{ old('frequency_value', $profile->frequency_value) }}">
                        </div>
                        <div>
                            <label class="label">Start Date</label>
                            <input type="date" name="start_date" class="input input-bordered w-full" value="{{ old('start_date', $profile->start_date ? $profile->start_date->format('Y-m-d') : '') }}" required>
                        </div>
                        <div>
                            <label class="label">End Date</label>
                            <input type="date" name="end_date" class="input input-bordered w-full" value="{{ old('end_date', $profile->end_date ? $profile->end_date->format('Y-m-d') : '') }}">
                        </div>
                        <div>
                            <label class="label">Day of Week (1=Mon, 7=Sun)</label>
                            <input type="number" name="day_of_week" min="1" max="7" class="input input-bordered w-full" value="{{ old('day_of_week', $profile->day_of_week) }}">
                        </div>
                        <div>
                            <label class="label">Day of Month</label>
                            <input type="number" name="day_of_month" min="1" max="31" class="input input-bordered w-full" value="{{ old('day_of_month', $profile->day_of_month) }}">
                        </div>
                        <div>
                            <label class="label">Advance Notice Days</label>
                            <input type="number" name="advance_notice_days" min="0" class="input input-bordered w-full" value="{{ old('advance_notice_days', $profile->advance_notice_days) }}">
                        </div>
                        <div>
                            <label class="label">Status</label>
                            <select name="status" class="select select-bordered w-full">
                                <option value="active" {{ $profile->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="paused" {{ $profile->status == 'paused' ? 'selected' : '' }}>Paused</option>
                                <option value="cancelled" {{ $profile->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Auto Generate Invoices</label>
                            <input type="checkbox" name="auto_generate_invoices" value="1" class="checkbox" {{ $profile->auto_generate_invoices ? 'checked' : '' }}>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout> 