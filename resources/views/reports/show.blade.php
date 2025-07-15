<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Service Report Details
            </h2>
            <a href="{{ route('reports.index') }}" class="btn btn-ghost">Back to Reports</a>
        </div>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">General Info</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><span class="font-semibold">Client:</span> {{ $report->client->full_name ?? '-' }}</div>
                        <div><span class="font-semibold">Location:</span> {{ $report->location->name ?? '-' }}</div>
                        <div><span class="font-semibold">Service Date:</span> {{ $report->service_date ? $report->service_date->format('M d, Y') : '-' }}</div>
                        <div><span class="font-semibold">Service Time:</span> {{ $report->service_time ? date('g:i A', strtotime($report->service_time)) : '-' }}</div>
                        <div><span class="font-semibold">Pool Gallons:</span> {{ $report->pool_gallons ?? '-' }}</div>
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Chemistry Readings</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div><span class="font-semibold">FAC:</span> {{ $report->fac }}</div>
                        <div><span class="font-semibold">CC:</span> {{ $report->cc }}</div>
                        <div><span class="font-semibold">pH:</span> {{ $report->ph }}</div>
                        <div><span class="font-semibold">Alkalinity:</span> {{ $report->alkalinity }}</div>
                        <div><span class="font-semibold">Calcium:</span> {{ $report->calcium }}</div>
                        <div><span class="font-semibold">Salt:</span> {{ $report->salt }}</div>
                        <div><span class="font-semibold">CYA:</span> {{ $report->cya }}</div>
                        <div><span class="font-semibold">TDS:</span> {{ $report->tds }}</div>
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Cleaning Tasks</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach(['vacuumed','brushed','skimmed','cleaned_skimmer_basket','cleaned_pump_basket','cleaned_pool_deck'] as $task)
                            <div>
                                <span class="font-semibold">{{ str_replace('_', ' ', ucfirst($task)) }}:</span>
                                <span>{{ $report->$task ? 'Yes' : 'No' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Maintenance Tasks</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach(['cleaned_filter_cartridge','backwashed_sand_filter','adjusted_water_level','adjusted_auto_fill','adjusted_pump_timer','adjusted_heater','checked_cover','checked_lights','checked_fountain','checked_heater'] as $task)
                            <div>
                                <span class="font-semibold">{{ str_replace('_', ' ', ucfirst($task)) }}:</span>
                                <span>{{ $report->$task ? 'Yes' : 'No' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Chemicals & Other Services</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="font-semibold">Chemicals Used:</span>
                            <pre class="bg-base-200 p-2 rounded">{{ is_array($report->chemicals_used) ? json_encode($report->chemicals_used, JSON_PRETTY_PRINT) : $report->chemicals_used }}</pre>
                        </div>
                        <div><span class="font-semibold">Chemicals Cost:</span> ${{ number_format($report->chemicals_cost, 2) }}</div>
                        <div>
                            <span class="font-semibold">Other Services:</span>
                            <pre class="bg-base-200 p-2 rounded">{{ is_array($report->other_services) ? json_encode($report->other_services, JSON_PRETTY_PRINT) : $report->other_services }}</pre>
                        </div>
                        <div><span class="font-semibold">Other Services Cost:</span> ${{ number_format($report->other_services_cost, 2) }}</div>
                        <div><span class="font-semibold">Total Cost:</span> ${{ number_format($report->total_cost, 2) }}</div>
                    </div>
                </div>
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2">Notes & Photos</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="font-semibold">Notes to Client:</span>
                            <div class="bg-base-200 p-2 rounded">{{ $report->notes_to_client }}</div>
                        </div>
                        <div>
                            <span class="font-semibold">Notes to Admin:</span>
                            <div class="bg-base-200 p-2 rounded">{{ $report->notes_to_admin }}</div>
                        </div>
                        <div class="col-span-2">
                            <span class="font-semibold">Photos:</span>
                            @if(is_array($report->photos))
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach($report->photos as $photo)
                                        <img src="{{ $photo }}" alt="Report Photo" class="w-32 h-32 object-cover rounded border">
                                    @endforeach
                                </div>
                            @else
                                <div class="bg-base-200 p-2 rounded">{{ $report->photos }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 