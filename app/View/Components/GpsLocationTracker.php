<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class GpsLocationTracker extends Component
{
    public $updateInterval;
    public $showStatus;

    /**
     * Create a new component instance.
     */
    public function __construct(int $updateInterval = 10000, bool $showStatus = true)
    {
        $this->updateInterval = $updateInterval; // Default: 10 seconds (10000ms)
        $this->showStatus = $showStatus;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.gps-location-tracker');
    }
}
