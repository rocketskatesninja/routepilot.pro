# RoutePilot

Multi-tenant pool-service SaaS — **Laravel 12 · Inertia v2 · Vue 3 (TS) · Tailwind**.

This repository is the **Phase 1 foundation** of the fresh rebuild: clean schema, session-based
tenancy + custom domains, correct auth/OAuth, the RoutePilot design tokens (dark "ops" +
daylight), and quality gates (Pest, Pint, Larastan, CI security audits) from commit one. The
proven domain engines (chemistry/LSI dosing, routing optimizer, AI assistant) are ported in
Phase 2. See `CLAUDE.md` for the engineering charter.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev        # or: npm run build
php artisan serve
```

By default the app uses a SQLite database (`database/database.sqlite`); configure MySQL in `.env`
for a production-like setup.

## Quality gates

```bash
./vendor/bin/pest                 # tests
./vendor/bin/pint --test          # PHP code style (PSR-12 via Pint)
./vendor/bin/phpstan analyse      # static analysis (Larastan, level 6)
npm run lint && npm run format:check
```

CI (`.github/workflows`) runs the test + static-analysis job, a security job (`composer audit` +
`npm audit`), and the linter on every push and PR.

## Key concepts

- **Tenancy:** tenant-owned models use `App\Models\Concerns\BelongsToTenant`; `ResolveTenant`
  binds the tenant from the session (staff) or host (custom domain / subdomain).
- **Roles:** `users.role` is the single source of truth; Spatie permissions are layered for
  granular `manage_*` abilities only.
- **Auth:** email/password + Google OAuth (verified email + linked `google_id` only).
- **Theming:** CSS-variable tokens in `resources/css/app.css`; per-tenant brand overlay via
  `resources/js/composables/useBrand.ts`.
