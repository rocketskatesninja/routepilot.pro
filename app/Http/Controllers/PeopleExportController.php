<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\PersonListBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download the People list as a CSV spreadsheet or a PDF — exactly the list on
 * screen: same tab, search and sort, every page of it. Admins only, and
 * audited, since it's the company's whole client list.
 *
 * @phpstan-type Row array{name: string, type: string, email: string, phone: string, address: string, portal: string, last_visit: string, balance: float|null}
 */
class PeopleExportController extends Controller
{
    private const TITLES = ['all' => 'People', 'customers' => 'Customers', 'agents' => 'Agents', 'archived' => 'Archived customers'];

    public function __invoke(Request $request, PersonListBuilder $builder): Response
    {
        $this->authorizeAdmin($request);
        $user = $request->user();

        $format = $request->string('format')->toString() === 'pdf' ? 'pdf' : 'csv';
        $filters = PersonListBuilder::filters($request);
        $rows = $this->rows($builder, (int) $user->tenant_id, $filters);
        AuditLog::record($user, 'people.exported', null, ['format' => $format, ...$filters, 'rows' => $rows->count()]);

        $title = self::TITLES[$filters['type']];
        $file = str($title)->slug().'-'.Tenant::localToday()->toDateString().'.'.$format;

        return $format === 'pdf' ? $this->pdf($rows, $title, $filters, $file) : $this->csv($rows, $file);
    }

    /**
     * The list's rows with what the export shows: the screen's columns plus a
     * customer's address.
     *
     * @param  array{type: 'all'|'customers'|'agents'|'archived', search: string, sort: 'name'|'type'|'email'|'phone', dir: 'asc'|'desc'}  $filters
     * @return Collection<int, covariant Row>
     */
    private function rows(PersonListBuilder $builder, int $tenantId, array $filters): Collection
    {
        $rows = $builder->withDetails($builder->query($tenantId, $filters)->get());
        $addresses = Customer::withTrashed()
            ->whereKey($rows->where('person_type', 'customer')->pluck('id'))
            ->get(['id', 'address_line1', 'address_line2', 'city', 'state', 'zip'])
            ->mapWithKeys(fn (Customer $c): array => [$c->id => trim(implode(', ', array_filter([
                $c->address_line1, $c->getAttribute('address_line2'), $c->city, trim($c->state.' '.$c->getAttribute('zip')),
            ])))]);

        return $rows->map(fn (object $r): array => [
            'name' => trim($r->first_name.' '.$r->last_name),
            'type' => $r->person_type === 'agent' ? 'Agent' : 'Customer',
            'email' => (string) $r->email,
            'phone' => (string) $r->phone,
            'address' => $r->person_type === 'customer' ? (string) ($addresses[$r->id] ?? '') : '',
            'portal' => match ($r->portal_status) {
                'active' => 'Yes', 'revoked' => 'Revoked', 'none' => 'No', default => ''
            },
            'last_visit' => (string) $r->last_visit,
            'balance' => $r->balance === null ? null : (float) $r->balance,
        ])->values();
    }

    /** @param  Collection<int, covariant Row>  $rows */
    private function csv(Collection $rows, string $file): Response
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads names with accents right
            fputcsv($out, ['Name', 'Type', 'Email', 'Phone', 'Address', 'Portal', 'Last visit', 'Balance']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    ...array_map($this->cell(...), [$r['name'], $r['type'], $r['email'], $r['phone'], $r['address'], $r['portal'], $r['last_visit']]),
                    $r['balance'] === null ? '' : number_format((float) $r['balance'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Collection<int, covariant Row>  $rows
     * @param  array{type: string, search: string, sort: string, dir: string}  $filters
     */
    private function pdf(Collection $rows, string $title, array $filters, string $file): Response
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;
        $summary = implode(', ', array_filter([
            $rows->count().' '.match ($filters['type']) {
                'agents' => str('agent')->plural($rows->count()),
                'customers', 'archived' => str('customer')->plural($rows->count()),
                default => $rows->count() === 1 ? 'person' : 'people',
            },
            $filters['search'] !== '' ? 'matching “'.$filters['search'].'”' : null,
            'sorted by '.$filters['sort'].($filters['dir'] === 'desc' ? ', Z to A' : ''),
        ])).'. Exported '.Tenant::localToday()->format('D, M j, Y').'.';

        return Pdf::loadView('people.pdf', [
            'tenant' => $tenant instanceof Tenant ? $tenant : null,
            'title' => $title,
            'summary' => $summary,
            'rows' => $rows,
            'showAgents' => $filters['type'] === 'all' || $filters['type'] === 'agents',
        ])->setPaper('letter', 'landscape')->download($file);
    }

    /**
     * A spreadsheet-safe text cell: a value starting with = + - @ would run as a
     * formula in Excel (a name like "=HYPERLINK(…)"), so it's kept as text.
     */
    private function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
