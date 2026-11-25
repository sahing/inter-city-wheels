# Execution Ledger

Machine-readable truth for `AUTOMATION_RULES.md`. One row per task. **No row may be `PASS` without a quoted command result.**

Status legend: `TODO` · `WIP` · `PASS` · `FAIL` · `BLOCKED` · `DEFERRED`

> **Environment note (was P0-0):** the workspace ACL is not canonical (`WRITE_OWNER` missing), so the harness cannot provision a confined sandbox grant for it. The session was switched to full file access, which unblocked every gate. Command execution now works normally; nothing below is inferred from file inspection alone.

---

## P0 — Blocking correctness & security

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| P0-0 | Restore command execution | G0 | **PASS** | `php -v` → PHP 8.2.12; `composer --version` → 2.10.2; `node -v` → v26.7.0; `npm -v` → 11.19.0; `php artisan about` → Laravel 12.69.3, Environment local | session moved to full file access; ACL repair script reported `VERDICT=PRECONDITION` (ACL not canonical) |
| P0-1 | Recover real PRD or derive `PRD_DERIVED.md` | — | **PASS** | `PRD_DERIVED.md` → 424 lines: 68 FR ids, 17 tables, 99 routes, 30 documented gaps, traceability to tests | human supplied no PRD, so behaviour was frozen as-implemented (R3 option b) |
| P0-2 | Authorization middleware | G2 | **PASS** | `tests/Feature/RouteAuthorizationAuditTest` (route-table audit) + `AuthorizationTest` (401/403 negatives) → included in `Tests: 191 passed` | `EnsureUserRole`, alias `role`, grouped routes; `/api/bids/accept` additionally narrowed to `role:customer,admin` |
| P0-3 | Restore CSRF on session & API endpoints | G4 | **PASS** | `bootstrap/app.php` → `validateCsrfTokens(except: [])`; `tests/Feature/CsrfProtectionTest` → 5 tests (419 without token for `/api/bookings`, `/login`, admin write; 200 with token); live smoke: `NOTOKEN: 419`, `BOOK: 200` | browser side: single `window.fetch` wrapper in `public/js/app.js` attaches `X-CSRF-TOKEN` from `<meta name="csrf-token">` |
| P0-4 | Coupon expiry + usage limits | G3 | **PASS** | migration `2026_11_24_231000_add_coupon_validity_columns`; `FareCalculatorService::resolveCoupon/redeemCoupon`; `BookingIntegrityTest` → expired / exhausted / below-min-spend / inactive / valid-and-counted-once | redemption only increments `used_count` when the coupon was actually applied, inside the booking transaction |
| P0-5 | Route bookings through the state machine | G3 | **PASS** | `BookingWorkflow` (explicit transition table, terminal states); `BookingWorkflowTest` → 11 tests incl. created-as-`Requested`, admin `Confirmed→Completed` rejected 422, driver `Confirmed→Started` rejected, double-complete rejected | admin create may target `Confirmed`/`Driver Assigned` only through a legal transition |
| P0-6 | Lock berth allocation in a transaction | G4 | **PASS** | `BookingController::store` → `DB::transaction` + `lockBerths()` `lockForUpdate`; `BookingIntegrityTest` → berths marked booked, second booking `409 berth_unavailable`, seeded sold berth `409`, unknown berth `422`, total from `seat_layouts` prices | berth numbers repeat per coach, so selection is pinned to one `coach_code` |
| P0-7 | CSPRNG OTP + unique reference | G3 | **PASS** | `BookingReferenceGenerator` (`random_int`, 6-digit `ICW-nnnnnn`, uniqueness check); `BookingIntegrityTest` → reference format/uniqueness, 4-digit OTP | `mt_rand` removed from booking, admin-create and bid-accept paths |
| P0-8 | Bind booking lookup to owner | G2 | **PASS** | `BookingController::show` → signed URL OR owner/admin OR reference+phone; `BookingIntegrityTest` → reference-only `403`, wrong phone `403`, correct phone `200`, signed URL `200`, tampered signature `403`, foreign customer `403`; live smoke `LOOKUP_NO_PHONE: 403`, `LOOKUP_SIGNED: 200` | new `POST /api/bookings/lookup` for guests; bookings gain `customer_user_id` |

| P0-9 | Admin coupon issuance (issue coupons from the admin panel) | G2/G4 | **PASS** | `Admin\CouponAdminController` + 6 admin-only routes (`api/admin/coupons*`) + admin panel section/modal/JS; `AdminCouponManagementTest` → 9 tests / 38 assertions (issue, duplicate-code 422, >100% 422, update/toggle/delete, redeemability metadata, guest 401 + customer 403, **admin-issued coupon redeemed at booking**, expired rejected, quote == charge) | full suite `250 passed (1528 assertions)`; build 354 ms; coupon redemption itself already existed in `FareCalculatorService` — only issuance was missing |

| P0-10 | Require sign-in before confirming a booking (+ one-tap gate) | G2/G4 | **PASS** | `POST /api/bookings` moved into the `auth` group, so guests get `401`; checkout shows `booking-login-gate-modal` with "Continue with one tap" → `quickDemoLogin('customer')` → pending payload restored from `sessionStorage` and confirmed automatically; booking now always carries `customer_user_id`; `BookingRequiresLoginTest` → 4 tests (guest 401, one-tap→booking linked to account, logout→401, quotes still public); 37 existing test call-sites updated to sign in | full suite `254 passed (1540 assertions)`; build 327 ms. Browsing, quoting and ticket lookup (reference + phone) stay anonymous by design |

| P0-11 | Remove demo surfaces from the production build | G2/G4 | **PASS** | `config/demo.php` (`INTERCITY_DEMO_MODE`, default off when `APP_ENV=production`) + `EnsureDemoMode` middleware → `POST /login/quick-demo`, `/auth/alternate-config`, `/auth/alternate-login`, `/auth/firebase-login`, `/auth/clerk-login` all `404`; OTP echo suppressed so a generated code is never returned; demo accounts, role-perspective preview, instant role switch, alternate-auth modal and the one-tap booking button are absent from the rendered HTML; JS falls back to the real sign-in form; seeder provisions the admin from `ADMIN_EMAIL`/`ADMIN_PASSWORD` instead of a published password | `DemoModeDisabledTest` → 5 tests; flag resolution verified `local ON / production OFF / override ON`; full suite `259 passed (1561 assertions)`; build 365 ms |

## P1 — Data integrity

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| P1-1 | Remove hardcoded distance fallbacks | G3 | **PASS** | `RegionalLocationService::resolveCoordinatesStrict` (+ `DistanceUnresolvable`); `BookingIntegrityTest` → unknown city `422 distance_unresolvable`, coordinates accepted; `PricingParityTest` → quote endpoint `422` too | the old lenient path also invented a "central West Bengal" coordinate for any unknown name, which is how 345 km became a fare |
| P1-2 | Single server-side pricing service | G4 | **PASS** | `FareCalculatorService` is the only price authority; `PricingParityTest` → quote == charge on every component, rates from `car_types`, local package from `local_packages`, airport from `airport_hubs`, roundtrip discount, add-ons, server-only coupon | `public/js/app.js` now renders `itemized_breakdown`/`quote` instead of local arithmetic; coupon validation is a server call |
| P1-3 | Transactional bid/wallet writes + ledger | G4 | **PASS** | migration `…_create_wallet_transactions_table`; `DriverWalletLedgerTest` → payout credit + ledger row (`balance_after` matches), double-complete refuses and pays once, over-balance withdrawal `422` and balance untouched, valid withdrawal debits + records, bid persisted for a real booking, unpersisted flagged | payout/withdrawal run in `DB::transaction` with `lockForUpdate` and a balance floor |
| P1-4 | Add DB indexes | G6 | **PASS** | migration `2026_11_24_231300_add_performance_indexes`; `php artisan migrate --pretend` → 14 `create index` statements, no errors | covers booking status/phone/driver/departure, berth (coach,number), bids, catalogue lookups |
| P1-5 | Server-side OTP TTL + attempt cap | G2 | **PASS** | `OtpService` (hashed codes, 600s TTL, 5 attempts, 3 sends/10 min); `OtpSecurityTest` → 7 tests incl. expiry, single-use, burn-after-guesses, throttle `429`, no live code echoed with a real gateway, hardcoded `1234` gone | the old code lived in the session forever, allowed unlimited guesses and accepted `1234` for any phone |

## P2 — Maintainability & release

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| P2-1 | Split `intercity.blade.php` | G5 | **DEFERRED** | 5,920 lines, behaviour-preserving refactor not attempted | R9: no functional gain for the risk; tests pin exact markup strings, so a split needs its own cycle |
| P2-2 | Reconcile duplicated assets | G5 | **PASS** (documented) | `docs/ASSETS.md` → 127 lines, cites `layouts/app.blade.php:22,707`, `public/build/manifest.json`, `welcome.blade.php:15`; conclusion: live pipeline is `public/css/styles.css` + `public/js/app.js`, `public/build/**` is built but unreferenced | deletion of dead assets left to the owner (documented, not performed) |
| P2-3 | CI pipeline for G1→G5 | G6 | **PASS** | `.github/workflows/ci.yml` → 86 lines; 16 named steps (G1 route audit + artifact, G4 suite, G4b pint, G5 build, G6 config clear + migrate --pretend); YAML parsed and structurally asserted | no secrets, no `continue-on-error` |
| P2-4 | Regenerate `PROGRESS.md` from ledger | — | **PASS** | `PROGRESS.md` rewritten from this ledger with the verified gate block | previous tracker claimed 100% while the API was open |

## MKT — Driver bidding marketplace (reverse auction)

Specified by the product owner on 2026-11-25: admin publishes a booking with a start price and a minimum margin, verified drivers bid downwards on a WhatsApp link, the lowest bid wins pending admin approval, and customer identity stays hidden until approval.

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| MKT-1 | Auction schema + model | G6 | **PASS** | migrations `2026_11_25_000100/000200/000300`; `php artisan migrate --pretend` → clean | `bidding_sessions` (token, start, fee %, status, deadline, award), `driver_bids` (+session, is_winning, IP, flag), `bookings` (+driver_payout_amount, assigned_bid_id) |
| MKT-2 | Admin auction API | G2 | **PASS** | `BiddingAdminController`; `DriverBiddingMarketplaceTest` → open, board, detail, close, award, cancel; all five admin endpoints return 401 for guests and 403 for customer/driver | start price capped at `fare × (1 − margin%)`; duplicate open → 409; non-biddable status → 422 |
| MKT-3 | Masked driver payload | G2 | **PASS** | `BiddingSession::toDriverPayload()` builds from the auction row only; test asserts absent keys (`customer_name`, `customer_phone`, addresses, `total_amount`, `admin_fee_pct`) **and** absent values in the raw body; rendered page likewise | privacy is structural, not CSS |
| MKT-4 | Descending bid rules | G3 | **PASS** | `BiddingService::placeBid()` under `lockForUpdate`; tests: strictly-lower enforced (equal and higher → 422), verified-only (403), guest 401, customer 403, 20-bid cap, deadline close (409), same-IP second account flagged and excluded from the win | bid history and competitor identity never exposed to drivers |
| MKT-5 | Award → assignment → reveal | G3 | **PASS** | `BiddingService::award()`; tests: lowest bidder assigned, `driver_payout_amount` = winning bid, admin profit = fare − bid, double award 409, no bids 422; winner's page shows customer PII, loser's page does not | booking moves `Bidding → Driver Assigned` through `BookingWorkflow` |
| MKT-6 | Payout integration | G4 | **PASS** | `completeTrip` pays `driver_payout_amount` when set; test asserts wallet credit equals the winning bid (2,000 not 90% of the fare) and the ledger amount matches | legacy 90% split kept for manually allotted trips |
| MKT-7 | WhatsApp share + driver page | G5 | **PASS** | `share.text/whatsapp_url` from `BiddingService`; standalone mobile page `resources/views/bidding.blade.php`; live smoke: `SHARE_HAS_PII: False`, `PAGE_HAS_PII: False`, bid 200, award 200, `WINNER_PAGE revealed=True`, `LOSER_VIEW_PII: False` | inline sign-in form; `?next=` accepted only as a local path (open-redirect safe) |
| MKT-8 | Deadline housekeeping | G6 | **PASS** | `bidding:close-expired` command + `Schedule::everyMinute()->withoutOverlapping()`; request path closes lazily; test travels past the deadline and gets `409 bidding_closed` | no queue dependency |

---

## Cycle log

| Cycle | Date | Task | Gate | Result | Evidence | Next |
|-------|------|------|------|--------|----------|------|
| 1 | 2026-11-24 | Baseline inspection | — | PASS | `php artisan test` → 118 passed (798 assertions); 102 routes; 5,824-line view | seed ledger |
| 1 | 2026-11-24 | P0-0 attempt (ACL repair script) | G0 | BLOCKED | `VERDICT=PRECONDITION`, `FIXED=0`, ACL not canonical | escalate → resolved by full access |
| 2 | 2026-11-24 | P0-2 authz, P0-7 CSPRNG, coupon `min_spend` | G2/G4/G5 | PASS | `route:list` → 0 unprotected; suite 118 passed | P0-6 next |
| 3 | 2026-11-24 | P0-1 PRD derivation | — | PASS | `PRD_DERIVED.md` 424 lines | unblocks requirements traceability |
| 4 | 2026-11-24 | P0-4/5/6/8, P1-1/2/3/5 + services + migrations + CSRF + SPA pricing | G3/G4 | PASS | `php artisan test` → 191 passed (1081 assertions) | gates |
| 5 | 2026-11-24 | G4b/G5/G6 + live smoke | G4b/G5/G6 | PASS | `vendor/bin/pint --test` → PASS 107 files; `npm run build` → 61 modules, 351ms, manifest written; `php artisan migrate --pretend` → clean; live: home 200, CSRF 419/200, booking `Requested`, lookup 403/200, berth double-book 409, admin toggle 200 | release |
| 6 | 2026-11-25 | MKT-1…MKT-8 driver bidding marketplace | G2/G3/G4/G5/G6 | **PASS** | `php artisan test` → **213 passed (1,263 assertions)**; `vendor/bin/pint --test` → PASS 117 files; `npm run build` → 326ms; `migrate --pretend` → clean; live HTTP: fare 2469 → start 2097 → bid 1997 → award payout 1997 / margin 472, winner sees PII, other viewer does not | release |
| 7 | 2026-11-25 | Role-based sign-in matrix, all three roles, plus a user created during the run | G2/G4 | **PASS** | `tests/Feature/RoleBasedLoginMatrixTest.php` → 10 passed (143 assertions); `php artisan test` → **223 passed (1,406 assertions)**; `vendor/bin/pint --test` on the new file → PASS; live HTTP `php -S`: customer 200 tab=cabs → `/api/admin/users` 403; driver 200 tab=bidding → 403; admin 200 tab=admin → 200; every cross-role login tab 422; logout → 401. Admin created `role.smoke.004058@example.com` (201), that account then logged in 200 tab=cabs, got 403 on admin, 422 on the driver tab, and 401 after logout. `storage/logs/laravel.log` grew **0 bytes** across the whole run | no role-scoped audit trail exists — sign-in/out and user creation write no log line; logged as a gap in `PROGRESS.md`. Harness kept at `storage/app/qa/role-login-smoke.ps1` |
| 8 | 2026-11-25 | OSM-1…OSM-4 OpenStreetMap place search (Nominatim) + real driving distance (OSRM) | G3/G4/G4b | **PASS** | `tests/Feature/OsmGeoIntegrationTest.php` → 10 passed (39 assertions); `php artisan test` → **233 passed (1,445 assertions)**; `vendor/bin/pint --test` → PASS 120 files; `node --check public/js/app.js` → OK. Live, no fakes: `GET /api/routes/calculate?from=Arambagh&to=Digha` → 200 `distance_km=205 estimated_time=2h 40m source=osm_geocode+osrm_route toll=189`; Nominatim `Arambagh` → 22.8766/87.7909; OSRM Kolkata→Digha → 181 km / 142 min; OSRM 503 → straight-line fallback with `source=coordinates`. Removed the fabricated path where an unknown name was pinned to a default city (`findCoordinates`, client-side `22.5726,88.3639` option) | public Nominatim policy forbids heavy use — production needs self-hosted Nominatim/OSRM; documented in `docs/OSM.md` |
| 9 | 2026-11-25 | OSM-5 durable route/place cache (`geo_route_cache`, `geo_place_cache`) | G3/G4/G4b | **PASS** | `tests/Feature/GeoCachePersistenceTest.php` → 8 passed (45 assertions); `php artisan test` → **241 passed (1,490 assertions)**; `vendor/bin/pint --test` → PASS 125 files. Live, three separate PHP processes: cold quote `Arambagh→Tajpur` **1854 ms** `cached=false`; `cache:clear` + `OSM_ENABLED=false` → **133 ms** `cached=true` `distance=203` unchanged; `geo:cache-stats` → routes 2 / places 1. `geo:prune-cache` (daily) deletes expired rows; expired rows are already ignored on read | Nominatim/OSRM remain single points of failure for *new* pairs only — stored pairs answer with the API off |

## Escalations

- **E1 · P0-0 — CLOSED.** Sandbox could not provision a workspace grant. Resolved by running the session with full file access; the ACL itself is untouched.
- **E2 · P0-1 — CLOSED.** No PRD was supplied, so `PRD_DERIVED.md` freezes implemented behaviour and lists 30 gaps explicitly, including which ones this cycle closed.
- **E3 · concurrency — OPEN (informational).** A second agent was editing this repository during the cycle (feature-flag work: `PlatformSetting::isSchedulesAndBerthsEnabled`, `toggleFeature`, layout gating, `AdminSchedulesBerthsOptionTest`). Its design (off by default, admin opt-in) was adopted rather than reverted; shared tests were reconciled. Verified state is the state at the final gate run below.

## Deliberate deferrals (with reason)

- **P2-1 view split** — 5,920-line Blade; highest cost, no functional payoff, and dozens of tests assert exact markup. Needs its own cycle.
- **Dead assets** — `public/build/**` and `welcome.blade.php` are unreferenced (`docs/ASSETS.md`); removal is a one-line decision for the owner, not a blocker for any gate.
- **Local demo DB flag** — the local `database.sqlite` had the berth module switched **on** during smoke testing (admin toggle); fresh installs seed it **off** (`DatabaseSeeder`), and both states are covered by tests.
