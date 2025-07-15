<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Recurring Billing Profiles
            </h2>
            <a href="{{ route('recurring-billing-profiles.create') }}" class="btn btn-primary">Create Profile</a>
        </div>
    </x-slot>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Client</th>
                                <th>Location</th>
                                <th>Frequency</th>
                                <th>Status</th>
                                <th>Next Billing</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($profiles as $profile)
                                <tr>
                                    <td>{{ $profile->name }}</td>
                                    <td>{{ $profile->client->full_name ?? '-' }}</td>
                                    <td>{{ $profile->location->name ?? '-' }}</td>
                                    <td>{{ ucfirst($profile->frequency) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $profile->status === 'active' ? 'success' : ($profile->status === 'paused' ? 'warning' : 'neutral') }}">
                                            {{ ucfirst($profile->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $profile->next_billing_date ? $profile->next_billing_date->format('M d, Y') : '-' }}</td>
                                    <td class="flex gap-2">
                                        <a href="{{ route('recurring-billing-profiles.show', $profile) }}" class="btn btn-xs btn-outline">View</a>
                                        <a href="{{ route('recurring-billing-profiles.edit', $profile) }}" class="btn btn-xs btn-outline">Edit</a>
                                        <form action="{{ route('recurring-billing-profiles.destroy', $profile) }}" method="POST" onsubmit="return confirm('Delete this profile?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-error">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-8">No recurring billing profiles found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 