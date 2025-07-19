<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Client;
use App\Models\Location;
use App\Models\Invoice;
use App\Models\Report;
use App\Models\Activity;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        if ($user->isAdmin()) {
            return $this->adminDashboard();
        } elseif ($user->isTechnician()) {
            return $this->technicianDashboard();
        } else {
            return $this->customerDashboard();
        }
    }

    private function adminDashboard()
    {
        // General Statistics
        $stats = [
            'total_clients' => Client::count(),
            'active_clients' => Client::where('is_active', true)->count(),
            'total_locations' => Location::count(),
            'active_locations' => Location::where('status', 'active')->count(),
            'total_invoices' => Invoice::count(),
            'unpaid_invoices' => Invoice::where('status', '!=', 'paid')->count(),
            'total_reports' => Report::count(),
            'recent_reports' => Report::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
        ];

        // Upcoming Appointments (next 7 days)
        $upcomingAppointments = Location::with('client')
            ->where('status', 'active')
            ->get()
            ->filter(function ($location) {
                // Simple logic - you might want to implement more sophisticated scheduling
                return $location->service_day_1 || $location->service_day_2;
            })
            ->take(10);

        // Recent Invoices
        $recentInvoices = Invoice::with(['client', 'location'])
            ->latest()
            ->take(5)
            ->get();

        // Recent Reports
        $recentReports = Report::with(['client', 'location', 'technician'])
            ->latest()
            ->take(5)
            ->get();

        // Recent Activities
        $recentActivities = Activity::with('user')
            ->latest()
            ->take(10)
            ->get();

        return view('dashboard.admin', compact(
            'stats',
            'upcomingAppointments',
            'recentInvoices',
            'recentReports',
            'recentActivities'
        ));
    }

    private function technicianDashboard()
    {
        $user = auth()->user();

        // General Statistics for assigned locations
        $assignedLocations = Location::where('assigned_technician_id', $user->id)->count();
        $recentReports = Report::where('technician_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // Upcoming Appointments for assigned locations
        $upcomingAppointments = Location::with('client')
            ->where('assigned_technician_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->filter(function ($location) {
                return $location->service_day_1 || $location->service_day_2;
            })
            ->take(10);

        return view('dashboard.technician', compact(
            'assignedLocations',
            'recentReports',
            'upcomingAppointments'
        ));
    }

    private function customerDashboard()
    {
        $user = auth()->user();

        // Find the client record for this user
        $client = Client::where('email', $user->email)->first();

        if (!$client) {
            // If no client record exists, create one
            $client = Client::create([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => 'client',
            ]);
        }

        // Account Summary
        $stats = [
            'total_locations' => $client->locations()->count(),
            'total_invoices' => $client->invoices()->count(),
            'unpaid_invoices' => $client->invoices()->where('status', '!=', 'paid')->count(),
            'total_reports' => $client->reports()->count(),
        ];

        // Upcoming Appointments
        $upcomingAppointments = $client->locations()
            ->where('status', 'active')
            ->get()
            ->filter(function ($location) {
                return $location->service_day_1 || $location->service_day_2;
            })
            ->take(5);

        // Recent Invoices
        $recentInvoices = $client->invoices()
            ->with('location')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.customer', compact(
            'stats',
            'upcomingAppointments',
            'recentInvoices',
            'client'
        ));
    }
}
