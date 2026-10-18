<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailPreferencesRequest;
use App\Models\Customer;
use App\Support\EmailPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A customer choosing, from their portal, which optional emails they get. */
class EmailsController extends Controller
{
    public function edit(Request $request): Response
    {
        $customer = $this->customer($request);

        return Inertia::render('settings/Emails', [
            'preferences' => EmailPreferences::for($customer),
            'types' => EmailPreferences::TYPES,
        ]);
    }

    public function update(UpdateEmailPreferencesRequest $request): RedirectResponse
    {
        EmailPreferences::set($this->customer($request), $request->changes());

        return back()->with('success', 'Saved.');
    }

    private function customer(Request $request): Customer
    {
        $customer = Customer::query()->where('user_id', $request->user()?->id)->first();
        abort_if($customer === null, 404);

        return $customer;
    }
}
