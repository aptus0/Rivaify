# Rivaify Marketing Site — 15-Page Redesign Plan

## Scope
Public marketing site only (`rivaify.com`). Does not touch: Laravel backend logic, auth, `app.rivaify.com` (dashboard SPA), `{store}.rivaify.com` (storefront SPA), APIs, Cloudflare config.

## Architecture decision: Inertia.js
The site moves from a single-page React Router SPA to **Inertia.js** (`inertiajs/inertia-laravel` + `@inertiajs/react`), scoped only to the `rivaify.com` route group in `routes/web.php` via a group-local `HandleInertiaRequests` middleware — not the global `web` middleware stack, so the dashboard/storefront Blade routes are unaffected.

Each of the 15 routes is a real Laravel route (`Inertia::render('Marketing/PageName', ['seo' => [...]])`). Per-route SEO (`title`/`description`/`canonical`/schema) is rendered server-side into `resources/views/app.blade.php` from that `seo` prop — this gets correct, crawlable meta into the *first* HTML response without needing a full Inertia SSR (Node) process. Each page also sets Inertia's `<Head>` client-side for correctness across client-side navigations.

No changes to `resources/js/dashboard/` or `resources/js/storefront/`.

## Brand system: Rivaify Spectrum / Focus Spectrum
Same underlying technique as the previous single-page redesign's "Chromatic Aura" (crisp rotating conic-gradient ring + pointer-tracked blurred halo, pure CSS, `--pointer-x`/`--pointer-y` set only while hovered), renamed and repalette'd to this brief's spec: `RivaCard`/`FocusSpectrum` effect, colors Orange `#FF6B00` → Magenta `#FF3D81` → Purple `#7957FF` → Blue `#3182FF` → Cyan `#20C7C7`. New primitives added: `FocusCorners` (viewport-entry recognition brackets), `TracingBeam` (animated SVG path connecting modules), `MovingBorder`, `Spotlight`, `PointerGlow` (room-scale ambient cursor glow, desktop-only).

## Third-party brand logos
**Decision (confirmed with user):** no real Instagram/Stripe/PayTR/kargo logos are sourced or embedded yet — none of those integrations are actually built (Sprint 2 status: Catalog/Inventory only), and downloading third-party trademarked assets from arbitrary sources isn't something to do unilaterally. `BrandLogo.tsx` renders a neutral icon + name today; swapping in licensed SVGs later (once the user supplies them into `public/brands/`) requires no component API change. All such integrations are labeled `Planlanıyor`/`Yakında` — never "partner".

## Phased delivery (this matches the brief's own phase gates)
1. **Phase 1 — infra** (this pass): Inertia wiring, design tokens, effects system, Bento system, brand logo placeholder system, navigation/footer/mega menus, SEO plumbing.
2. **Phase 2 — Home** (this pass): full quality-benchmark homepage before any other page is built out, per the brief's own instruction.
3. **Phase 3+** (this pass, lighter treatment — see below): Platform, Online Store, Store Builder, Themes, Social Commerce, Payments, Shipping, Integrations, Checkout, Analytics, Developers, Solutions, Security, Pricing all get **real routes + correct SEO + an on-brand hero + at least one distinguishing section** now, so nothing 404s and the nav is fully honest — but they don't yet carry Home's full depth (bento grids, multiple animated storytelling sections, drag-and-drop demo, live checkout customizer, etc.). Deepening each page to full parity with the brief is follow-up work, page by page.

## Known constraints carried over from the previous session
- This working tree is shared with another actively-committing process (Commerce/dashboard backend work) — only ever `git add` this redesign's specific files, never `-A`.
- Docker rebuild (`docker compose build app nginx && up -d --force-recreate app nginx`) causes a brief real outage on the live site — ask before deploying.
- No pricing model exists yet — `/pricing` uses "Yakında açıklanacak" / "Erken Erişim" language, never invented numbers.

## Status
Phase 1 (infra) and Phase 2 (Home) are complete to the full brief. Phases 3-6 (the other 14 pages) are implemented at the "real but lighter" tier described above — verified (all 15 routes 200, unique SEO, no console errors, working client-side navigation) but not yet deepened page-by-page to full parity with the brief. Not committed, not deployed.
