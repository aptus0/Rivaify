# Rivaify

Laravel 13 + React 19 multi-tenant e-commerce platform. This document covers
**Rivaify Core Sprint 01**: Identity, Multi-Tenancy, Merchant/Store
management, and the onboarding → verification → approval flow. Product,
order, and inventory management (Commerce Core) is Sprint 02 and hasn't
started — see `Modules/` for what actually exists today.

## Architecture

**Modular monolith**, using [nwidart/laravel-modules](https://nwidart.com/laravel-modules).
Each business domain is a module under `Modules/<Name>/app/{Models,Actions,
Events,Listeners,Enums,DTOs,Http/Controllers,Policies}`, with its own
migrations and routes:

- `Modules/Identity` — user registration events/listeners (auth itself is
  Laravel Fortify, not this module — see below)
- `Modules/Merchant` — the business entity that owns stores; business
  profile, tax profile
- `Modules/Store` — the tenant boundary; stores, store memberships,
  onboarding state
- `Modules/Verification` — verification requests/documents, admin review

Cross-cutting infrastructure lives in `app/Core/`, not inside a module:

- `app/Core/Tenancy` — `CurrentStore`, `BelongsToStore` trait, `StoreScope`,
  `EnsureStoreContext` middleware. **This is the most important code in the
  project** — see below.
- `app/Core/Shared` — `HasUlid` trait, `ActivityLog` model/service
- `app/Core/Security` — `EnsureIsRivaifyAdmin` middleware

### Tenant isolation

Every store-scoped model (`StoreUser`, `StoreDomain`, `VerificationRequest`,
…) uses the `BelongsToStore` trait, which registers a global Eloquent scope
(`StoreScope`) constraining every query to `store_id = CurrentStore::id()`.
`CurrentStore` is a request-scoped singleton bound by the `store.context`
middleware from the session.

The consequence: **`Order::all()`-style code cannot leak another tenant's
rows**, and a query that somehow runs *without* a bound store throws
`StoreContextMissingException` rather than silently returning nothing (looks
like an empty state) or everything (a data leak). Rivaify Admin's
necessarily cross-tenant queries opt out explicitly and auditably via
`Model::withoutGlobalScope(StoreScope::class)` — see
`Modules/Verification/app/Http/Controllers/Admin/VerificationReviewController.php`.

`tests/Feature/TenantIsolationTest.php` is the regression test for this —
if you touch `app/Core/Tenancy/`, run it first.

### Auth

Registration/login/logout/email verification/password reset are handled by
**Laravel Fortify** in headless mode (`config/fortify.php`: `'views' =>
false`), paired with **Sanctum** for cookie-based SPA auth
(`$middleware->statefulApi()` in `bootstrap/app.php`). The React dashboard
never sends passwords to a custom endpoint — it calls Fortify's stock
`/register`, `/login`, `/logout` routes directly.

`RIVAIFY_ADMIN` access (approving/rejecting verification requests) is a
single `users.is_rivaify_admin` boolean, gated by the `rivaify.admin`
middleware — not a full roles/permissions system (deliberately out of scope
for Sprint 01, see `Modules/Store/app/Enums/StoreUserRole.php` for the
store-level role list that *is* implemented).

### The onboarding flow

```
Register → Verify email → Create store → Business info → Tax info
  → Upload documents → Submit for review → PENDING
  → Rivaify Admin approves → Store ACTIVE → Merchant Dashboard
```

Driven by `Modules/Store/app/Enums/OnboardingStatus.php` (a state machine,
not booleans) and orchestrated by Actions in each module
(`Modules\Store\Actions\CreateStore`,
`Modules\Merchant\Actions\SubmitBusinessProfile`/`SubmitTaxProfile`,
`Modules\Verification\Actions\SubmitVerificationRequest`/
`ApproveVerificationRequest`/`RejectVerificationRequest`), each firing a
domain event (`MerchantCreated`, `StoreCreated`, `VerificationSubmitted`,
`MerchantApproved`, `VerificationRejected`) with listeners registered
explicitly in each module's `EventServiceProvider` (auto-discovery is
turned off — `protected static $shouldDiscoverEvents = false` — so the
event → listener wiring stays greppable).

### Frontend

`resources/js/main.tsx` is the existing **rivaify.com landing page** — do
not mix dashboard code into it. The merchant dashboard SPA lives in
`resources/js/dashboard/`, feature-organized per the target structure:

```
dashboard/
  app/{router,layouts,providers}
  components/{ui,common}
  features/{auth,onboarding,store,verification,dashboard}
  lib/            # api.ts — Sanctum-cookie fetch wrapper
  types/
  utils/
```

Served on its own host, `app.rivaify.com` (brief §11), via
`Route::domain('app.rivaify.com')` in `routes/web.php` +
`createBrowserRouter(..., { basename: '/' })` — no path prefix. Requires a
DNS/hosts entry (`127.0.0.1 app.rivaify.com`) locally; visiting by bare IP
or `localhost` falls through to the marketing site instead, since that
route has no domain constraint.

`react-router-dom` is pinned to **7.18.2** exactly (not a `^` range): every
7.x from 7.12.0 up carries an RSC-mode CSRF advisory, and every 7.x below
7.18.1 carries a much worse pile (XSS, RCE via deserialization, DoS) fixed
only in 7.18.1+. We don't use RSC mode (plain `createBrowserRouter`,
client-rendered), so 7.18.2 is the correct choice despite `npm audit` still
flagging the RSC advisory — verify this reasoning still holds before
bumping the version.

## Local development

```
docker compose up -d
```

Because the `app` container's image bakes code in at build time (no bind
mount in `compose.yaml`), any Laravel command needs a bind mount and a
matching uid to avoid root-owned files:

```
docker compose run --rm --user 1000:1000 -e HOME=/tmp \
  -v "$(pwd):/var/www/html" app php artisan <command>
```

(`HOME=/tmp` is needed because Tinker/Psysh tries to write a config dir
under `$HOME`, which doesn't exist for an arbitrary `--user` uid.)

Frontend: Node/npm run directly on the host (no container needed) —
`npm install && npm run dev`, or `npm run build` for production assets.

## Testing

```
docker compose run --rm --user 1000:1000 -e HOME=/tmp \
  -e APP_ENV=testing -e SESSION_DRIVER=array -e CACHE_STORE=array \
  -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array \
  -v "$(pwd):/var/www/html" app php artisan test
```

**Why the `-e` flags are required, not optional:** `compose.yaml`'s
`env_file: .env` injects `APP_ENV=production` (and friends) as real
container-level environment variables *before PHP starts*. `phpunit.xml`'s
`<env force="true">` only reaches `getenv()`/`$_ENV` — it does **not**
override `$_SERVER`, which is what's already populated by the time PHP
boots, and Laravel's environment detection reads from there. Without the
`docker compose run -e` overrides, tests silently run as `APP_ENV=production`
(real CSRF enforcement, real Redis queue/cache/session) instead of what
`phpunit.xml` claims — this produced a very confusing 419 on a registration
test before it was diagnosed. `pdo_sqlite` isn't installed in the app image
either, so tests hit the real Postgres connection via `DatabaseTransactions`
(not `RefreshDatabase` — schema is never touched, no `pdo_sqlite` needed).

**Testing two authenticated personas in one test:** don't. Simulating a
merchant and then a Rivaify Admin in the *same* PHPUnit method — sharing one
cookie jar — leaves a stale session-guard artifact and produces spurious
401s that don't reflect anything a real user would hit (two real people are
never sharing one browser's cookies). `tests/Feature/OnboardingFlowTest.php`
splits merchant-side steps and admin-review steps into separate test
methods for exactly this reason. Single-persona authenticated requests use
`Laravel\Sanctum\Sanctum::actingAs()`; routes that start a session need
`->withHeader('Referer', 'https://app.rivaify.com')` since
`EnsureFrontendRequestsAreStateful` only starts one for requests it
recognizes as coming from the SPA.

11 Feature tests pass at last check (tenant isolation + full onboarding +
admin approve/reject + non-admin rejection), plus the framework's stock
examples.

## What's not built yet

- Product/order/inventory (Commerce Core, Sprint 02)
- Cloudflare R2 document storage is wired (`config/filesystems.php`'s `r2`
  disk, `UploadVerificationDocument` action) but untested against a real
  bucket — `R2_*` env vars are empty placeholders
- Granular per-permission checks (`products.view`, `orders.update`, …) —
  only role-based (`StoreUserRole`) authorization exists
- Rivaify Admin's own dashboard UI (the API exists —
  `/api/admin/verification-requests` — no frontend consumes it yet)
- `admin.rivaify.com` / `api.rivaify.com` subdomain split — everything
  currently runs on one host with path-based separation for the dashboard
