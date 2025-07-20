@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Chemical Calculator</h1>
            <p class="text-base-content/70 mt-2">Calculate chemical dosages to balance your pool water chemistry</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Calculator Form -->
        <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
            <div class="p-6 border-b border-base-200">
                <h2 class="text-xl font-semibold text-base-content">Water Chemistry Input</h2>
                <p class="text-base-content/70 mt-1">Enter current and target values for your pool</p>
            </div>
            
            <form method="POST" action="{{ route('chem-calc.calculate') }}" class="p-6 space-y-6">
                @csrf
                
                <!-- Pool Volume -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Pool Volume (Gallons)</span>
                        <span class="label-text-alt text-base-content/60">Required</span>
                    </label>
                    <input type="number" name="pool_volume" value="{{ old('pool_volume', $calculatorData['pool_volume'] ?? '') }}" 
                           class="input input-bordered w-full" placeholder="e.g., 15000" required>
                    @error('pool_volume')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </div>

                <!-- pH Section -->
                <div class="card bg-base-200/50">
                    <div class="card-body p-4">
                        <h3 class="card-title text-lg text-base-content mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            pH Level
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Current pH</span>
                                    <span class="label-text-alt">Target: 7.2-7.6</span>
                                </label>
                                <input type="number" name="current_ph" value="{{ old('current_ph', $calculatorData['current_ph'] ?? '') }}" 
                                       class="input input-bordered" step="0.1" min="0" max="14" placeholder="7.4" required>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Target pH</span>
                                    <span class="label-text-alt">Ideal: 7.4</span>
                                </label>
                                <input type="number" name="target_ph" value="{{ old('target_ph', $calculatorData['target_ph'] ?? '7.4') }}" 
                                       class="input input-bordered" step="0.1" min="0" max="14" placeholder="7.4" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chlorine Section -->
                <div class="card bg-base-200/50">
                    <div class="card-body p-4">
                        <h3 class="card-title text-lg text-base-content mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Free Chlorine (ppm)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Current Chlorine</span>
                                    <span class="label-text-alt">Target: 1-3 ppm</span>
                                </label>
                                <input type="number" name="current_chlorine" value="{{ old('current_chlorine', $calculatorData['current_chlorine'] ?? '') }}" 
                                       class="input input-bordered" step="0.1" min="0" placeholder="2.0" required>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Target Chlorine</span>
                                    <span class="label-text-alt">Ideal: 2.0 ppm</span>
                                </label>
                                <input type="number" name="target_chlorine" value="{{ old('target_chlorine', $calculatorData['target_chlorine'] ?? '2.0') }}" 
                                       class="input input-bordered" step="0.1" min="0" placeholder="2.0" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alkalinity Section -->
                <div class="card bg-base-200/50">
                    <div class="card-body p-4">
                        <h3 class="card-title text-lg text-base-content mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            Total Alkalinity (ppm)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Current Alkalinity</span>
                                    <span class="label-text-alt">Target: 80-120 ppm</span>
                                </label>
                                <input type="number" name="current_alkalinity" value="{{ old('current_alkalinity', $calculatorData['current_alkalinity'] ?? '') }}" 
                                       class="input input-bordered" step="1" min="0" placeholder="100" required>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Target Alkalinity</span>
                                    <span class="label-text-alt">Ideal: 100 ppm</span>
                                </label>
                                <input type="number" name="target_alkalinity" value="{{ old('target_alkalinity', $calculatorData['target_alkalinity'] ?? '100') }}" 
                                       class="input input-bordered" step="1" min="0" placeholder="100" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calcium Hardness Section -->
                <div class="card bg-base-200/50">
                    <div class="card-body p-4">
                        <h3 class="card-title text-lg text-base-content mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                            Calcium Hardness (ppm)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Current Calcium</span>
                                    <span class="label-text-alt">Target: 200-400 ppm</span>
                                </label>
                                <input type="number" name="current_calcium" value="{{ old('current_calcium', $calculatorData['current_calcium'] ?? '') }}" 
                                       class="input input-bordered" step="1" min="0" placeholder="250" required>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Target Calcium</span>
                                    <span class="label-text-alt">Ideal: 250 ppm</span>
                                </label>
                                <input type="number" name="target_calcium" value="{{ old('target_calcium', $calculatorData['target_calcium'] ?? '250') }}" 
                                       class="input input-bordered" step="1" min="0" placeholder="250" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cyanuric Acid Section -->
                <div class="card bg-base-200/50">
                    <div class="card-body p-4">
                        <h3 class="card-title text-lg text-base-content mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h4a1 1 0 011 1v2m4 0V2a1 1 0 011-1h1a1 1 0 011 1v2m-7 0V2a1 1 0 011-1h4a1 1 0 011 1v2m4 0V2a1 1 0 011-1h1a1 1 0 011 1v2M7 4v16a1 1 0 001 1h10a1 1 0 001-1V4H7z"></path>
                            </svg>
                            Cyanuric Acid (ppm)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Current Cyanuric Acid</span>
                                    <span class="label-text-alt">Target: 30-80 ppm</span>
                                </label>
                                <input type="number" name="current_cyanuric_acid" value="{{ old('current_cyanuric_acid', $calculatorData['current_cyanuric_acid'] ?? '') }}" 
                                       class="input input-bordered" step="1" min="0" placeholder="50" required>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Target Cyanuric Acid</span>
                                    <span class="label-text-alt">Ideal: 50 ppm</span>
                                </label>
                                <input type="number" name="target_cyanuric_acid" value="{{ old('target_cyanuric_acid', $calculatorData['target_cyanuric_acid'] ?? '50') }}" 
                                       class="input input-bordered" step="1" min="0" placeholder="50" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Calculate Dosages
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="space-y-6">
            @if(isset($results) && count($results) > 0)
                <!-- Results Card -->
                <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                    <div class="p-6 border-b border-base-200">
                        <h2 class="text-xl font-semibold text-base-content">Chemical Dosages</h2>
                        <p class="text-base-content/70 mt-1">Recommended amounts to add to your pool</p>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        @if(isset($results['soda_ash']))
                            <div class="alert alert-info">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Soda Ash (pH+)</h3>
                                    <p class="text-sm">{{ $results['soda_ash'] }} lbs to raise pH</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['muriatic_acid']))
                            <div class="alert alert-warning">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Muriatic Acid (pH-)</h3>
                                    <p class="text-sm">{{ $results['muriatic_acid'] }} quarts to lower pH</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['chlorine']))
                            <div class="alert alert-success">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Chlorine</h3>
                                    <p class="text-sm">{{ $results['chlorine'] }} lbs to raise chlorine level</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['baking_soda']))
                            <div class="alert alert-info">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Baking Soda (Alkalinity+)</h3>
                                    <p class="text-sm">{{ $results['baking_soda'] }} lbs to raise alkalinity</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['muriatic_acid_alkalinity']))
                            <div class="alert alert-warning">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Muriatic Acid (Alkalinity-)</h3>
                                    <p class="text-sm">{{ $results['muriatic_acid_alkalinity'] }} quarts to lower alkalinity</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['calcium_chloride']))
                            <div class="alert alert-info">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Calcium Chloride</h3>
                                    <p class="text-sm">{{ $results['calcium_chloride'] }} lbs to raise calcium hardness</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['cyanuric_acid']))
                            <div class="alert alert-info">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h4a1 1 0 011 1v2m4 0V2a1 1 0 011-1h1a1 1 0 011 1v2m-7 0V2a1 1 0 011-1h4a1 1 0 011 1v2m4 0V2a1 1 0 011-1h1a1 1 0 011 1v2M7 4v16a1 1 0 001 1h10a1 1 0 001-1V4H7z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Cyanuric Acid</h3>
                                    <p class="text-sm">{{ $results['cyanuric_acid'] }} lbs to raise cyanuric acid level</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($results['water_dilution']))
                            <div class="alert alert-error">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <h3 class="font-semibold">Water Dilution Required</h3>
                                    <p class="text-sm">Drain {{ $results['water_dilution'] }}% of pool water to lower cyanuric acid</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Information Card -->
            <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                <div class="p-6 border-b border-base-200">
                    <h2 class="text-xl font-semibold text-base-content">Pool Chemistry Guidelines</h2>
                    <p class="text-base-content/70 mt-1">Ideal ranges for balanced pool water</p>
                </div>
                
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Ideal Range</th>
                                    <th>Optimal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-medium">pH</td>
                                    <td>7.2 - 7.6</td>
                                    <td>7.4</td>
                                </tr>
                                <tr>
                                    <td class="font-medium">Free Chlorine</td>
                                    <td>1.0 - 3.0 ppm</td>
                                    <td>2.0 ppm</td>
                                </tr>
                                <tr>
                                    <td class="font-medium">Total Alkalinity</td>
                                    <td>80 - 120 ppm</td>
                                    <td>100 ppm</td>
                                </tr>
                                <tr>
                                    <td class="font-medium">Calcium Hardness</td>
                                    <td>200 - 400 ppm</td>
                                    <td>250 ppm</td>
                                </tr>
                                <tr>
                                    <td class="font-medium">Cyanuric Acid</td>
                                    <td>30 - 80 ppm</td>
                                    <td>50 ppm</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Safety Tips Card -->
            <div class="bg-base-100 shadow-xl rounded-lg border border-base-300">
                <div class="p-6 border-b border-base-200">
                    <h2 class="text-xl font-semibold text-base-content">Safety Tips</h2>
                    <p class="text-base-content/70 mt-1">Important reminders for chemical handling</p>
                </div>
                
                <div class="p-6 space-y-3">
                    <div class="flex items-start space-x-3">
                        <div class="badge badge-warning badge-sm mt-1">⚠️</div>
                        <p class="text-sm text-base-content/80">Always add chemicals to water, never water to chemicals</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="badge badge-warning badge-sm mt-1">⚠️</div>
                        <p class="text-sm text-base-content/80">Wear protective equipment when handling chemicals</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="badge badge-warning badge-sm mt-1">⚠️</div>
                        <p class="text-sm text-base-content/80">Add chemicals gradually and test water between additions</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="badge badge-warning badge-sm mt-1">⚠️</div>
                        <p class="text-sm text-base-content/80">Wait 4-6 hours between chemical additions</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="badge badge-warning badge-sm mt-1">⚠️</div>
                        <p class="text-sm text-base-content/80">Keep pool pump running for 24 hours after chemical additions</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 