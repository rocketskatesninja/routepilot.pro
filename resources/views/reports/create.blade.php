<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            New Service Report
        </h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('reports.store') }}" method="POST" id="report-form">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                            <label class="label">Service Date</label>
                            <input type="date" name="service_date" class="input input-bordered w-full" required>
                        </div>
                        <div>
                            <label class="label">Service Time</label>
                            <input type="time" name="service_time" class="input input-bordered w-full" required>
                        </div>
                        <div>
                            <label class="label">Pool Gallons</label>
                            <input type="number" name="pool_gallons" class="input input-bordered w-full">
                        </div>
                    </div>

                    <div class="divider my-8">Chemistry Readings</div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div><label class="label">FAC</label><input type="number" step="0.01" name="fac" class="input input-bordered w-full"></div>
                        <div><label class="label">CC</label><input type="number" step="0.01" name="cc" class="input input-bordered w-full"></div>
                        <div><label class="label">pH</label><input type="number" step="0.1" name="ph" class="input input-bordered w-full"></div>
                        <div><label class="label">Alkalinity</label><input type="number" name="alkalinity" class="input input-bordered w-full"></div>
                        <div><label class="label">Calcium</label><input type="number" name="calcium" class="input input-bordered w-full"></div>
                        <div><label class="label">Salt</label><input type="number" name="salt" class="input input-bordered w-full"></div>
                        <div><label class="label">CYA</label><input type="number" name="cya" class="input input-bordered w-full"></div>
                        <div><label class="label">TDS</label><input type="number" name="tds" class="input input-bordered w-full"></div>
                    </div>

                    <div class="divider my-8">Cleaning Tasks</div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        @foreach(['vacuumed','brushed','skimmed','cleaned_skimmer_basket','cleaned_pump_basket','cleaned_pool_deck'] as $task)
                            <div>
                                <label class="cursor-pointer label">
                                    <input type="checkbox" name="{{ $task }}" class="checkbox">
                                    <span class="ml-2 capitalize">{{ str_replace('_', ' ', $task) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="divider my-8">Maintenance Tasks</div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        @foreach(['cleaned_filter_cartridge','backwashed_sand_filter','adjusted_water_level','adjusted_auto_fill','adjusted_pump_timer','adjusted_heater','checked_cover','checked_lights','checked_fountain','checked_heater'] as $task)
                            <div>
                                <label class="cursor-pointer label">
                                    <input type="checkbox" name="{{ $task }}" class="checkbox">
                                    <span class="ml-2 capitalize">{{ str_replace('_', ' ', $task) }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="divider my-8">Chemicals & Other Services</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">Chemicals Used (JSON or comma list)</label>
                            <input type="text" name="chemicals_used" class="input input-bordered w-full" placeholder='[{"name":"Chlorine","amount":2}] or "Chlorine, Acid"'>
                        </div>
                        <div>
                            <label class="label">Chemicals Cost</label>
                            <input type="number" step="0.01" name="chemicals_cost" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Other Services (JSON or comma list)</label>
                            <input type="text" name="other_services" class="input input-bordered w-full" placeholder='[{"name":"Filter Clean","cost":30}] or "Filter Clean"'>
                        </div>
                        <div>
                            <label class="label">Other Services Cost</label>
                            <input type="number" step="0.01" name="other_services_cost" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label">Total Cost</label>
                            <input type="number" step="0.01" name="total_cost" class="input input-bordered w-full">
                        </div>
                    </div>

                    <div class="divider my-8">Notes & Photos</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">Notes to Client</label>
                            <textarea name="notes_to_client" class="textarea textarea-bordered w-full"></textarea>
                        </div>
                        <div>
                            <label class="label">Notes to Admin</label>
                            <textarea name="notes_to_admin" class="textarea textarea-bordered w-full"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="label">Photos (comma-separated URLs or paths)</label>
                            <input type="text" name="photos" class="input input-bordered w-full">
                        </div>
                    </div>

                    <div class="divider my-8">Chemical Calculator Integration</div>
                    <div class="mb-6">
                        <button type="button" class="btn btn-info" onclick="populateFromCalculator()">Populate from Chemical Calculator</button>
                        <p class="text-xs text-gray-500 mt-2">This will auto-fill chemistry and chemical fields from the calculator's output (integration required).</p>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="btn btn-primary">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // Placeholder for chemical calculator integration
        function populateFromCalculator() {
            // Example: Simulate calculator output
            const calc = {
                fac: 2.5,
                cc: 0.5,
                ph: 7.4,
                alkalinity: 90,
                calcium: 250,
                salt: 3200,
                cya: 40,
                tds: 1500,
                chemicals_used: '[{"name":"Chlorine","amount":2}]',
                chemicals_cost: 10.00
            };
            for (const [key, value] of Object.entries(calc)) {
                const input = document.querySelector(`[name='${key}']`);
                if (input) input.value = value;
            }
        }
    </script>
</x-app-layout> 