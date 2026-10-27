<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateServiceRequest;
use App\Http\Requests\StoreReportMessageRequest;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Support\EmailBrand;
use App\Support\ReportMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Message us" from a visit report email — no login; the link is signed and
 * expires. The message lands in the company's Requests inbox as a note about
 * that visit, and the office answers it there (the answer is emailed back).
 * A company can turn this off on its Company page.
 */
class ReportMessageController extends Controller
{
    public function show(ServiceVisit $visit): Response
    {
        [$customer, $tenant] = $this->owner($visit);
        $brand = EmailBrand::for($tenant);
        $timezone = $tenant->timezoneName();

        return Inertia::render('public/ReportMessage', [
            'company' => ['name' => $brand['name'], 'color' => $brand['color'], 'logo' => $brand['logo']],
            'firstName' => $customer->first_name ?: $customer->displayName(),
            'visit' => [
                'date' => $visit->completed_at?->copy()->setTimezone($timezone)->format('l, F j'),
                'pool' => $visit->pool?->name,
            ],
            'sendUrl' => URL::temporarySignedRoute('report-message.store', now()->addDay(), ['visit' => $visit->id]),
        ]);
    }

    public function store(StoreReportMessageRequest $request, ServiceVisit $visit, CreateServiceRequest $create): RedirectResponse
    {
        [$customer] = $this->owner($visit);

        $waiting = ServiceRequest::query()
            ->where('customer_id', $customer->id)->where('type', 'other')->where('status', 'pending')
            ->count();
        if ($waiting >= ReportMessages::MAX_WAITING) {
            throw ValidationException::withMessages([
                'message' => 'You already have messages waiting for an answer. The office will get back to you soon.',
            ]);
        }

        $create->handle([
            'type' => 'other',
            'pool_id' => $visit->getAttribute('pool_id'),
            'service_visit_id' => $visit->id,
            'message' => $request->validated('message'),
        ], $customer);

        return back()->with('success', 'Sent.');
    }

    /**
     * The visit's customer and company, with the company bound for this request (a
     * public page has no signed-in company). 404 when the company has these off.
     *
     * @return array{0: Customer, 1: Tenant}
     */
    private function owner(ServiceVisit $visit): array
    {
        $customer = $visit->pool?->customer;
        $tenant = $customer?->tenant;
        abort_if($customer === null || $tenant === null || ! $tenant->takesReportMessages(), 404);
        app()->instance('tenant_id', $tenant->id);

        return [$customer, $tenant];
    }
}
