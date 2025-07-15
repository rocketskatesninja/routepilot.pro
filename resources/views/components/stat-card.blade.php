@props([
    'title',
    'value',
    'icon' => null,
    'color' => 'primary',
    'description' => null,
    'trend' => null,
    'trendDirection' => 'up'
])

<div class="card bg-base-100 shadow-xl border border-base-300">
    <div class="card-body p-6">
        <div class="flex items-center">
            @if($icon)
                <div class="p-3 rounded-full bg-{{ $color }}/20">
                    <svg class="w-6 h-6 text-{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $icon !!}
                    </svg>
                </div>
            @endif
            <div class="{{ $icon ? 'ml-4' : '' }}">
                <p class="text-sm font-medium text-base-content/70">{{ $title }}</p>
                <p class="text-2xl font-semibold text-base-content">{{ $value }}</p>
                @if($description)
                    <p class="text-xs text-base-content/50">{{ $description }}</p>
                @endif
                @if($trend)
                    <div class="flex items-center mt-1">
                        <svg class="w-4 h-4 text-{{ $trendDirection === 'up' ? 'success' : 'error' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($trendDirection === 'up')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            @endif
                        </svg>
                        <span class="text-xs text-{{ $trendDirection === 'up' ? 'success' : 'error' }} ml-1">{{ $trend }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div> 