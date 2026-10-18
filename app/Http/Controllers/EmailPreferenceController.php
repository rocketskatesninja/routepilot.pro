<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmailPreferencesRequest;
use App\Models\Customer;
use App\Support\EmailBrand;
use App\Support\EmailPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A customer's email choices, reached from a signed link in one of their
 * emails — no login needed. Opening the link only shows the page; nothing
 * changes until they tap a button, so link-checking scanners can't
 * unsubscribe anyone. The inbox's own one-click "Unsubscribe" (RFC 8058)
 * posts to its own signed address.
 */
class EmailPreferenceController extends Controller
{
    public function show(Request $request, Customer $customer): Response
    {
        $stop = (string) $request->query('stop');
        $brand = EmailBrand::for($customer->tenant);

        return Inertia::render('public/EmailPreferences', [
            'company' => ['name' => $brand['name'], 'color' => $brand['color'], 'logo' => $brand['logo']],
            'firstName' => $customer->first_name ?: $customer->displayName(),
            'preferences' => EmailPreferences::for($customer),
            'types' => EmailPreferences::TYPES,
            'stop' => isset(EmailPreferences::TYPES[$stop]) ? $stop : null,
            'updateUrl' => URL::signedRoute('email-preferences.update', ['customer' => $customer->id]),
        ]);
    }

    public function update(UpdateEmailPreferencesRequest $request, Customer $customer): RedirectResponse
    {
        EmailPreferences::set($customer, $request->changes());
        // Back to the same page — still on "stop visit reports" if that's where they came in.
        $stop = (string) $request->input('stop');

        return redirect(EmailPreferences::pageUrl($customer->id, isset(EmailPreferences::TYPES[$stop]) ? $stop : null))->with('success', 'Saved.');
    }

    /** The inbox's built-in "Unsubscribe" button: turn that one kind of email off. */
    public function oneClick(Customer $customer, string $type): SymfonyResponse
    {
        abort_unless(isset(EmailPreferences::TYPES[$type]), 404);
        EmailPreferences::set($customer, [$type => false]);

        return response('Unsubscribed.');
    }

    /** Unsubscribe links in emails sent before these choices existed: open the page on news and offers. */
    public function legacy(Customer $customer): RedirectResponse
    {
        return redirect(EmailPreferences::pageUrl($customer->id, 'news'));
    }
}
