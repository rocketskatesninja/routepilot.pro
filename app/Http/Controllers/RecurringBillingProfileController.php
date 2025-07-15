<?php

namespace App\Http\Controllers;

use App\Models\RecurringBillingProfile;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;

class RecurringBillingProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $profiles = RecurringBillingProfile::with(['client', 'location', 'technician'])->orderBy('created_at', 'desc')->get();
        return view('recurring-billing-profiles.index', compact('profiles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $clients = Client::orderBy('last_name')->get();
        $locations = Location::orderBy('name')->get();
        $technicians = User::where('role', 'technician')->orderBy('last_name')->get();
        return view('recurring-billing-profiles.create', compact('clients', 'locations', 'technicians'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'technician_id' => 'required|exists:users,id',
            'rate_per_visit' => 'required|numeric|min:0',
            'chemicals_cost' => 'nullable|numeric|min:0',
            'chemicals_included' => 'nullable|boolean',
            'extras_cost' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:weekly,biweekly,monthly,quarterly,custom',
            'frequency_value' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'day_of_week' => 'nullable|integer|min:1|max:7',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'advance_notice_days' => 'nullable|integer|min:0',
            'status' => 'required|in:active,paused,cancelled',
            'auto_generate_invoices' => 'nullable|boolean',
        ]);
        $validated['chemicals_included'] = $request->has('chemicals_included');
        $validated['auto_generate_invoices'] = $request->has('auto_generate_invoices');
        $profile = RecurringBillingProfile::create($validated);
        return redirect()->route('recurring-billing-profiles.show', $profile)->with('success', 'Recurring billing profile created.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $profile = RecurringBillingProfile::with(['client', 'location', 'technician', 'invoices'])->findOrFail($id);
        return view('recurring-billing-profiles.show', compact('profile'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $profile = RecurringBillingProfile::findOrFail($id);
        $clients = Client::orderBy('last_name')->get();
        $locations = Location::orderBy('name')->get();
        $technicians = User::where('role', 'technician')->orderBy('last_name')->get();
        return view('recurring-billing-profiles.edit', compact('profile', 'clients', 'locations', 'technicians'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $profile = RecurringBillingProfile::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'technician_id' => 'required|exists:users,id',
            'rate_per_visit' => 'required|numeric|min:0',
            'chemicals_cost' => 'nullable|numeric|min:0',
            'chemicals_included' => 'nullable|boolean',
            'extras_cost' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:weekly,biweekly,monthly,quarterly,custom',
            'frequency_value' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'day_of_week' => 'nullable|integer|min:1|max:7',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'advance_notice_days' => 'nullable|integer|min:0',
            'status' => 'required|in:active,paused,cancelled',
            'auto_generate_invoices' => 'nullable|boolean',
        ]);
        $validated['chemicals_included'] = $request->has('chemicals_included');
        $validated['auto_generate_invoices'] = $request->has('auto_generate_invoices');
        $profile->update($validated);
        return redirect()->route('recurring-billing-profiles.show', $profile)->with('success', 'Recurring billing profile updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $profile = RecurringBillingProfile::findOrFail($id);
        $profile->delete();
        return redirect()->route('recurring-billing-profiles.index')->with('success', 'Recurring billing profile deleted.');
    }
}
