<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Pool;
use App\Support\EmailBrand;
use App\Support\ReportCopies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * "Stop these copies" from a copy of someone's visit report — no login. The
 * address it was sent to is part of the signed link. Opening the link only
 * shows the page; nothing changes until they tap the button, so link-checking
 * scanners can't stop anyone's copies.
 */
class ReportCopyController extends Controller
{
    public function show(Request $request, Pool $pool): Response
    {
        $email = (string) $request->query('email');
        $brand = EmailBrand::for($pool->customer?->tenant);

        return Inertia::render('public/ReportCopies', [
            'company' => ['name' => $brand['name'], 'color' => $brand['color'], 'logo' => $brand['logo']],
            'customer' => $pool->customer?->first_name ?: ($pool->customer?->displayName() ?? 'The customer'),
            'email' => $email,
            'stopped' => ! ReportCopies::goesTo($pool, $email),
            'stopUrl' => ReportCopies::stopUrl($pool, $email),
        ]);
    }

    public function stop(Request $request, Pool $pool): RedirectResponse
    {
        $email = (string) $request->query('email');
        ReportCopies::stop($pool, $email);

        return redirect(ReportCopies::pageUrl($pool, $email));
    }

    /** The inbox's built-in "Unsubscribe" button. */
    public function oneClick(Request $request, Pool $pool): SymfonyResponse
    {
        ReportCopies::stop($pool, (string) $request->query('email'));

        return response('Unsubscribed.');
    }
}
