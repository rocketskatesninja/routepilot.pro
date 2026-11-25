# RoutePilot — Engineering Charter

RoutePilot is a multi-tenant pool-service SaaS: **Laravel 13 + Inertia v3 (SSR) + Vue 3 (TypeScript) + Tailwind 4**.
Keep the surface area small; prefer Eloquent-idiomatic, single-responsibility code over clever generality.
When a rule, query or form block is needed a second time, share it instead of copying it.

## Architecture

Four surfaces, one codebase:

1. **Back-office** (tenant_admin, agent) — dark "ops" theme, customizable dashboard grid, list + docked detail pane.
2. **Agent field PWA** (`/field`) — daylight theme, works offline (IndexedDB queue, idempotent sync).
3. **Customer portal** (customer) — lightweight, online-only.
4. **Public landing** (`/t/{slug}` or a custom domain) — SSR, section-based builder.

Plus the platform console (super_admin): companies, platform billing, AI settings, impersonation.

## Backend conventions

- **Thin controllers → Form Requests → single-purpose Action classes** (`app/Actions`, one `handle()` each).
  Business rules that several actions share live in `app/Services` (BillingService, ChemistryService,
  SubscriptionMaterializer, …). New work follows this; some older controllers still hold logic inline.
- **Validation is shared, not copied.** Rules used by more than one request go in a trait under
  `app/Http/Requests/Concerns` (e.g. `VisitReportRules`: the field app and the office report editor must
  accept the same limits).
- **Eloquent relationships + query scopes over raw SQL.**
- **Money/Stripe + DB writes go in transactions** with explicit error handling.
- **Style:** Pint, `declare(strict_types=1)` in new PHP files, Larastan level 6.
- **Tests: Pest.** Test requests start with no company bound, as in production (`tests/TestCase.php`).

## Tenancy (non-negotiable)

- Shared schema + `tenant_id`. Tenant-owned models use `App\Models\Concerns\BelongsToTenant`
  (global `TenantScope` + auto-fills `tenant_id`).
- **Bind a company only through `Tenant::bind()`** (sets both `tenant` and `tenant_id`). Console loops use
  `Tenant::eachActive()`, which binds each company and restores the previous binding afterwards.
  Never call `app()->instance('tenant_id', …)` by hand: it leaves "today" on the wrong time zone.
- `ResolveTenant` binds per request: from the signed-in user for staff/customers, from the custom domain or
  `/t/{slug}` for public pages. It runs before route-model binding (see `bootstrap/app.php`); keep it there.
- **`User` is NOT tenant-scoped** — assert tenant ownership explicitly on any User binding or query.
- **Time zones:** datetimes are stored in UTC. A company's "today" is `Tenant::localToday()` /
  `$tenant->today()`; convert a stored moment with `$tenant->toLocal()`. Don't use `now()`/`today()` for
  calendar-day logic.

## Security (priority)

- **One role model:** `users.role` (super_admin, tenant_admin, agent, customer). Extra powers are flags
  (e.g. `agent_plus`), not a permissions package.
- **Authorization:** every route sits in a `role:` middleware group (`EnsureRole`) in `routes/web.php`;
  controllers add `authorizeAdmin/Staff/Super` or a Form Request `authorize()` for finer checks.
- Privilege/identity fields (`tenant_id`, `role`, `is_active`, `google_id`, `email_verified_at`) are not
  `$fillable` — set them with `forceFill()` at controlled call sites.
- **Sign-in:** password or Google (verified email + linked `google_id` only). Staff TOTP 2FA applies to
  both. Deactivated users are signed out on their next request. Weakening 2FA needs a current code;
  changing a staff sign-in email needs the password; neither works while impersonating.
- Secrets are encrypted at rest (`encrypted` casts, `Crypt` for platform AI keys).
- Both Stripe webhooks fail closed without their own secret.
- CSV exports go through `App\Support\Csv::cell()` (formula guard).
- Audit-log billing, permission, delete, impersonation and export actions (`AuditLog::record`).
- CI runs static analysis, the type check, and composer/npm audits on every push.

## Frontend conventions

- **Vue 3 `<script setup>` + TypeScript**; typed page props (`resources/js/types`). `vue-tsc` must pass.
- **Tailwind 4 with CSS-variable tokens** (`resources/css/app.css`): `:root` = daylight, `.dark` = ops.
  Per-tenant brand color overlays at runtime (`composables/useBrand.ts`).
- Components on **radix-vue + cva + tailwind-merge** (shadcn-vue style) in `components/ui`; icons from
  `@lucide/vue`. Delete kit pieces nothing imports.
- List pages: `ListTable` + `MasterDetail` + `?selected=` (see `lib/listQuery.ts`, `composables/useListSearch.ts`).
- Shared labels live in `lib/labels.ts`; money/date formatting in `lib/utils.ts`.
- The chemistry engine exists twice on purpose (PHP `ChemistryService` and `lib/chemistry.ts` for offline
  use); both are held to the same golden vectors (`lib/__fixtures__/chemistry-vectors.json`).

## Checks before pushing

`./ci-check.sh` mirrors CI: pint, prettier, eslint, vue-tsc, phpstan, build, vitest, pest, composer audit,
npm audit. `npm run test:click` runs the full browser click test against staging.

Deploy flow: staging (serv, `routepilot.local`) → owner tests → GitHub `main` → production (`deploy.sh` on punch).
