<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\User;
use App\Models\Client;
use App\Models\Location;
use App\Models\Invoice;
use App\Models\Report;
use Carbon\Carbon;
use App\Services\LoggingService;
use App\Constants\AppConstants;

class ActivityController extends Controller
{
    /**
     * Display a listing of activities with filtering and retention settings.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Build query with eager loading
        $query = Activity::with(['user', 'subject']);
        
        // Apply filters
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }
        
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }
        
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', "%{$searchTerm}%")
                  ->orWhere('action', 'like', "%{$searchTerm}%")
                  ->orWhere('ip_address', 'like', "%{$searchTerm}%");
            });
        }
        
        // Apply retention settings
        $retentionDays = config('app.activity_retention_days', 365); // Default 1 year
        $query->where('created_at', '>=', Carbon::now()->subDays($retentionDays));
        
        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        // Paginate results
        $activities = $query->paginate(AppConstants::DEFAULT_PAGINATION);
        
        // Get filter options
        $actions = Activity::distinct()->pluck('action')->sort()->values();
        $users = User::orderBy('first_name')->get();
        $modelTypes = Activity::distinct()->pluck('model_type')->sort()->values();
        
        // Get statistics
        $stats = [
            'total_activities' => Activity::count(),
            'activities_today' => Activity::where('created_at', '>=', Carbon::today())->count(),
            'activities_this_week' => Activity::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'activities_this_month' => Activity::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            'retention_days' => $retentionDays,
            'oldest_activity' => Activity::min('created_at'),
            'newest_activity' => Activity::max('created_at'),
        ];
        
        // Get activity breakdown by action
        $actionBreakdown = Activity::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();
        
        // Get activity breakdown by user
        $userBreakdown = Activity::with('user')
            ->selectRaw('user_id, COUNT(*) as count')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->take(10)
            ->get();
        
        return view('activities.index', compact(
            'activities',
            'actions',
            'users',
            'modelTypes',
            'stats',
            'actionBreakdown',
            'userBreakdown'
        ));
    }
    
    /**
     * Show retention settings and management options.
     */
    public function settings()
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Only administrators can access activity settings.');
        }
        
        // Get current retention settings
        $retentionDays = config('app.activity_retention_days', 365);
        $autoCleanup = config('app.activity_auto_cleanup', true);
        
        // Get statistics for retention management
        $stats = [
            'total_activities' => Activity::count(),
            'activities_older_than_30_days' => Activity::where('created_at', '<', Carbon::now()->subDays(30))->count(),
            'activities_older_than_90_days' => Activity::where('created_at', '<', Carbon::now()->subDays(90))->count(),
            'activities_older_than_365_days' => Activity::where('created_at', '<', Carbon::now()->subDays(365))->count(),
            'oldest_activity' => Activity::min('created_at'),
            'newest_activity' => Activity::max('created_at'),
        ];
        
        return view('activities.settings', compact('retentionDays', 'autoCleanup', 'stats'));
    }
    
    /**
     * Update retention settings.
     */
    public function updateSettings(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Only administrators can update activity settings.');
        }
        
        $request->validate([
            'retention_days' => 'required|integer|min:1|max:3650', // Max 10 years
            'auto_cleanup' => 'boolean',
        ]);
        
        // Update configuration (you might want to store this in database instead)
        // For now, we'll just return success and suggest manual config update
        
        LoggingService::logUserAction('updated activity retention settings', [
            'retention_days' => $request->retention_days,
            'auto_cleanup' => $request->auto_cleanup,
        ]);
        
        return redirect()->route('activities.settings')
                        ->with('success', 'Activity retention settings updated successfully. Please update your configuration file to apply changes.');
    }
    
    /**
     * Clean up old activities based on retention settings.
     */
    public function cleanup(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Only administrators can perform activity cleanup.');
        }
        
        $retentionDays = $request->get('days', config('app.activity_retention_days', 365));
        
        // Count activities that will be deleted
        $activitiesToDelete = Activity::where('created_at', '<', Carbon::now()->subDays($retentionDays))->count();
        
        if ($request->isMethod('post')) {
            // Perform the cleanup
            $deletedCount = Activity::where('created_at', '<', Carbon::now()->subDays($retentionDays))->delete();
            
            LoggingService::logUserAction('performed activity cleanup', [
                'retention_days' => $retentionDays,
                'deleted_count' => $deletedCount,
            ]);
            
            return redirect()->route('activities.settings')
                            ->with('success', "Successfully deleted {$deletedCount} old activities.");
        }
        
        return view('activities.cleanup', compact('retentionDays', 'activitiesToDelete'));
    }
    
    /**
     * Export activities to CSV.
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Only administrators can export activities.');
        }
        
        // Build query similar to index method
        $query = Activity::with(['user', 'subject']);
        
        // Apply filters
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from));
        }
        
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        
        $activities = $query->orderBy('created_at', 'desc')->get();
        
        $filename = 'activities_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, [
                'ID',
                'User',
                'Action',
                'Description',
                'Model Type',
                'Model ID',
                'IP Address',
                'User Agent',
                'Created At',
                'Updated At'
            ]);
            
            // Add data
            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->id,
                    $activity->user ? $activity->user->full_name : 'System',
                    $activity->action,
                    $activity->description,
                    $activity->model_type,
                    $activity->model_id,
                    $activity->ip_address,
                    $activity->user_agent,
                    $activity->created_at,
                    $activity->updated_at
                ]);
            }
            
            fclose($file);
        };
        
        LoggingService::logUserAction('exported activities', [
            'count' => $activities->count(),
            'filters' => $request->all(),
        ]);
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Show detailed view of a specific activity.
     */
    public function show(Activity $activity)
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Only administrators can view activity details.');
        }
        
        return view('activities.show', compact('activity'));
    }
} 