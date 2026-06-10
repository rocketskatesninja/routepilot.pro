# RoutePilot — Engineering Charter

RoutePilot is a multi-tenant pool-service SaaS: **Laravel 12 + Inertia v2 + Vue 3 (TypeScript) + Tailwind**.
This is a fresh build that **harvests the proven domain engines** (chemistry, routing, AI) from the
legacy app while getting tenancy, billing, and security right from line one. Keep the surface area
small; prefer Eloquent-idiomatic, single-responsibility code over clever generality.

## Architecture

Four surfaces, one codebase:

1. **Back-office** (tenant_admin) — dark "ops" theme, customizable dashboards, online.
2. **Agent field PWA** — daylight theme, offline-resilient, glanceable.
3. **Customer portal** — lightweight, online-only.
4. **Public landing** — SSR, section-based builder.

## Backend conventions (Laravel 12)

- **Thin controllers → Form Requests → single-purpose Action classes.** Validation lives in a
  `FormRequest`; each use-case is one invokable Action (e.g. `App\Actions\RegisterTenant`,
  `CompleteVisit`, `SendInvoice`). Controllers wire request → action → response, nothing more.
- **Eloquent relationships + query scopes over raw SQL.** Small, one-responsibility files.
- **Money/Stripe + DB writes go in transactions** (`DB::transaction`) with explicit error handling.
- **Style:** Pint, `declare(strict_types=1)` in every PHP file, Larastan level 6 (relations carry
  `@return Type<Related, $this>` generics). Run `vendor/bin/pint && vendor/bin/phpstan analyse`.
- **Tests: Pest, from line one** — `./vendor/bin/pest`.

## Tenancy (non-negotiable)

- Shared schema + `tenant_id`. Tenant-owned models use `App\Models\Concerns\BelongsToTenant`
  (adds the global `TenantScope` + auto-fills `tenant_id` from `app('tenant_id')`).
- `App\Http\Middleware\ResolveTenant` binds the tenant: **session-based for staff** (from
  `$user->tenant_id`), **custom-domain / subdomain for public**.
- **`User` is NOT globally tenant-scoped** (cross-tenant binding hazard) — assert tenant ownership
  explicitly on any User binding.

## Security (priority)

- **Single role model:** `users.role` enum is the source of truth; Spatie `HasRoles` is for
  granular `manage_*` permissions ONLY — never a second role system.
- Privilege/identity fields (`tenant_id`, `role`, `is_active`, `google_id`, `email_verified_at`)
  are **not `$fillable`** — set them via `forceFill()` at controlled call sites.
- **OAuth:** verified Google email + linked `google_id` only; never log in on an email match alone.
- Secrets are `encrypted` at rest (e.g. `Integration.config` → `encrypted:array`).
- Policies/Gates on every sensitive action; audit-log billing/permission/delete/impersonation/export
  actions. Roadmap: 2FA (TOTP) for staff, rate-limiting + lockouts.
- **CI runs static analysis + composer/npm audit every push.**

## Frontend conventions

- **Vue 3 `<script setup>` + TypeScript** on Inertia; typed page props (`resources/js/types`).
- **Tailwind with the CSS-variable token system** (`resources/css/app.css`): `:root` = daylight
  (light + field theme), `.dark` = ops. Brand = sky `#0ea5e9` + orange `#f97316`, font Inter.
  Per-tenant brand color overlays both at runtime via `composables/useBrand.ts`.
- Default theme follows OS preference + per-user toggle (`composables/useAppearance.ts`).
- Custom components built on **radix-vue + cva + tailwind-merge** (shadcn-vue style); no heavy UI kit.
- Lint/format: `npm run lint`, `npm run format` (Prettier + ESLint, check-mode in CI).

## Where things live

- Tenancy primitives: `app/Models/Concerns/BelongsToTenant.php`, `app/Models/Scopes/TenantScope.php`.
- Shared Inertia props (auth/tenant/permissions/flash): `app/Http/Middleware/HandleInertiaRequests.php`.
- App shell + role-adaptive sidebar: `resources/js/components/AppSidebar.vue`, `layouts/`.
- The phased rebuild plan (engines port, surfaces, billing) lives outside the repo; Phase 1 here is
  the foundation. Engines (Chemistry / Routing / ClaudeService) land in Phase 2 with golden-vector
  parity tests in CI.
