# IntercityWheels — Verified Progress

**This file is generated from `LEDGER.md` (rule P2-4).** It reports only what a command proved. `LEDGER.md` is the source of truth; if the two disagree, the ledger wins.

**Last verified:** 2026-11-25 · Laravel 12.69.3 · PHP 8.2.12 · Node v26.7.0

---

## Gate results (quoted)

| Gate | Command | Result |
|---|---|---|
| G0 preflight | `php -v` · `composer --version` · `node -v` · `npm -v` | PHP 8.2.12 · Composer 2.10.2 · Node v26.7.0 · npm 11.19.0 |
| G0c boot | `php artisan about` | Laravel 12.69.3, env `local`, boots clean |
| G1 route audit | `php artisan route:list --json > route-audit.json` | 104 routes emitted; `RouteAuthorizationAuditTest` proves 0 privileged routes without `auth` + `role` |
| G2 authz | `php artisan test --filter=Authorization` / `RouteAuthorizationAuditTest` | PASS, including 401 (guest) and 403 (wrong role) negatives |
| G3 logic | `php artisan test --filter=Pricing` / `Booking` / `Workflow` | PASS |
| G4 suite | `php artisan test` | **295 passed (6,993 assertions)** |
| G4b quality | `vendor/bin/pint --test` | **PASS — 137 files** |
| G5 build | `npm run build` | 61 modules → `public/build/manifest.json` + assets, built in 326 ms |
| G6 release | `php artisan config:clear && php artisan migrate --pretend` | config cleared; 14 index statements + 3 schema changes, no errors |

### Trip history after booking (added 2026-11-25)

Booking now ends on **My Trips** with the new trip on top: `All` / `Active` / `Completed` filters with live counts, per-trip cards (status, route, departure, fare, chauffeur, ride OTP, boarding pass), and per-filter empty states. Signed-out visitors get the same screen with a sign-in form and a reference + mobile tracker.

Live HTTP proof:

```
BOOKED: ICW-639442 status=Requested
LANDING: 200
  history_active:  True      banner: True
  card_highlight:  True      just_booked_tag: True
  filters:         True      counts_all: 7
GUEST: nav_my_trips=True     guest_panel=True
```

### Driver bidding marketplace (added 2026-11-25)

Admin publishes a booking to verified drivers over WhatsApp; drivers bid **downwards**; the lowest bid wins once the admin approves it; customer identity is withheld until approval. Full design in [`docs/BIDDING.md`](docs/BIDDING.md).

Live HTTP proof:

```
BOOKING: ICW-213822 fare=2469
OPEN: 201 start=2097 maxDriver=2098        SHARE_HAS_PII: False
PAGE: 200 showsRoute=True hasSignIn=True   PAGE_HAS_PII: False
BID: 200 amount=1997 lowest=1997 youLowest=True
BOARD: count=1 lowest=1997 profit=472 bids=1
AWARD: 200 status=Driver Assigned payout=1997 margin=472 driver=Ramesh Patil
WINNER_PAGE: revealed=True pii=True phone=True     LOSER_VIEW_PII: False
```

### Driver portal auctions (added 2026-11-25)

The driver's **Live Bids & Auctions** tab is now fed by real auctions instead of four hard-coded demo trips (which carried fake passenger names and phone numbers). `GET /api/driver/auctions` returns open auctions only, using the same masked payload as the WhatsApp page: booking reference, From/To city, KM, departure date & time, start price, current lowest, own bid count and the link to the auction page. A won trip appears in **My Assigned Trips** with the customer's contact details and **the winning payout** (not the customer fare), marked *Won via driver bidding*.

Live end-to-end proof (`php -S`, one run through the whole chain):

```
BOOKING ICW-152049 fare=8580        OPEN_BIDDING 201 start=4000 maxDriver=7293
WHATSAPP wa.me   SHARE_HAS_PII=False
FEED 200 count=1   Pune -> Goa   KM=450   departure=2026-12-02T06:30
FEED_MASKED phone_absent=True name_absent=True address_absent=True
PAGE 200 route+km+date-time+start price, masked
BID 200 amount=3900 lowest=3900 you_lowest=True
AWARD 200 status=Driver Assigned payout=3900
WINNER_PORTAL name=True phone=True payout_label=True payout_value=True bidding_badge=True
WINNER_PAGE revealed=True phone=True payout=True
SECOND_DRIVER feed count=0   LOSER_PORTAL name=False phone=False ref=False
LOSER_PAGE awarded_to_other=True phone=False
```

`DriverAuctionFeedTest` (9 tests) pins all of it: masking, own-bid accounting, open-only filtering, driver-only access, the portal tab, the winner's card and the loser's empty view.

### Role-based sign-in matrix (2026-11-25)

`tests/Feature/RoleBasedLoginMatrixTest.php` (10 tests, 143 assertions) drives every role through `POST /login`: each lands on its own tab, each refuses the other two tabs with a named message, wrong passwords fail, logout returns the session to guest, and admin/user-creation paths are covered. A new account was also created during a live run and signed in like a seeded one.

Live HTTP proof (`php -S 127.0.0.1:8765`, real sessions and CSRF tokens):

```
ROLE customer LOGIN 200 tab=cabs     me=200/customer admin_users=403 logout_after=401
ROLE driver   LOGIN 200 tab=bidding  me=200/driver   admin_users=403 logout_after=401
ROLE admin    LOGIN 200 tab=admin    me=200/admin    admin_users=200 logout_after=401
ADMIN_CREATE_USER 201 id=6 role=customer email=role.smoke.004058@example.com
NEW_USER LOGIN 200 tab=cabs me=200/customer admin_users=403
NEW_USER_WRONG_TAB 422 message=This account is registered as a Customer. Please select the Customer tab to log in.
NEW_USER LOGOUT 200 after_logout=401
LOG_NEW_BYTES=0
```

`LOG_NEW_BYTES=0` is a finding, not a failure: the app writes **no** role-scoped log line for sign-in, sign-out or user creation (see “Still open”).

### OpenStreetMap place search and distance (2026-11-25)

Place lookup and road distance come from OpenStreetMap through `App\Services\OsmGeoService`: Nominatim for name↔coordinate, OSRM for driving distance. Design, configuration and the rate-limit policy are in [`docs/OSM.md`](docs/OSM.md).

Pricing resolution order per endpoint: caller-supplied coordinates → `route_fares` → `service_locations` / curated catalogue → Nominatim. Distance then comes from OSRM, and only an unreachable OSRM falls back to the straight-line estimate. `distance_source` reports which path produced the number.

Live proof, real services, no fakes:

```
HTTP 200
route: from=Arambagh to=Digha distance_km=205 estimated_time=2h 40m base_toll=189
       distance_source=osm_geocode+osrm_route
NOMINATIM Arambagh -> 22.8765856, 87.7908631 (town, Hooghly, West Bengal)
OSRM Kolkata->Digha -> 181 km, 142 min
```

Fallbacks proved by `tests/Feature/OsmGeoIntegrationTest.php`: OSRM 503 → straight-line estimate (`source=coordinates`); Nominatim 429 → search still 200 with curated results only; an ungroundable name → `422 distance_unresolvable` rather than a default-city position. Unknown names are no longer pinned to `22.5726, 88.3639` on either the server or the client.

### Durable OpenStreetMap cache (2026-11-25)

A measured route and a geocoded place are written to `geo_route_cache` / `geo_place_cache` **before** the quote is returned, so the first query pays for the map API call and later queries are one indexed read. Keys hash the two endpoints (direction-insensitive, ~1 m rounding); rows expire after 30 days (routes) / 90 days (places) and are ignored on read once expired. Details in [`docs/OSM.md`](docs/OSM.md).

Live proof, three separate PHP processes — the middle flush removes the HTTP cache so only the database can answer, and the map API is switched off:

```
cold            HTTP 200  1854ms  distance=203  source=osm_geocode+osrm_route  cached=false
cache:clear     (HTTP cache dropped, DB row kept)
OSM_ENABLED=false  HTTP 200   133ms  distance=203  source=osm_geocode+osrm_route  cached=true
OSM_ENABLED=false  HTTP 200   133ms  distance=203  source=osm_geocode+osrm_route  cached=true

geo:cache-stats → routes: 2 rows, 0 expired | places: 1 row, 0 expired
```

`geo:prune-cache` runs daily and only deletes expired rows. `GeoCachePersistenceTest` (8 tests) covers write-then-read, reverse-trip reuse, expiry/re-measure, place reuse without Nominatim, missing tables, and both commands.

### Live HTTP smoke test (`php artisan serve`, real browser session)

```
HOME: 200 len=175992          CSRF_META: True
NOTOKEN: 419                  BOOK: 200
BOOK_REF: ICW-472341          STATUS: Requested   TOTAL: 8580
LOOKUP_URL_PRESENT: True      LOOKUP_NO_PHONE: 403   LOOKUP_SIGNED: 200
HEALTH: 200                   LEGAL: 200            CALC: 200
ADMIN_GUEST: 401              ADMIN_LOGIN: 200 role=admin
NAV_SCHEDULES_VISIBLE(off): False        BUS_WHILE_OFF: 403
TOGGLE: 200 enabled=True                 BUS_WHILE_ON: 200 ref=ICW-128790 total=850
DOUBLE_BERTH: 409
```

---

## What changed, by phase

### 1. Security boundary (P0-2, P0-3, P0-8)
- Every privileged route carries `auth` + `role:` middleware; `/api/bids/accept` was additionally narrowed to customers/admins.
- CSRF is enforced on **all** state-changing routes (the `api/*` and `login*` exemptions are gone). The SPA no longer needs per-call plumbing: one `window.fetch` wrapper attaches `X-CSRF-TOKEN` from the page's meta tag.
- A booking reference alone no longer exposes a passenger. Reads require a signed URL, the owning account, an admin, or reference **and** the booking's mobile number. Guests get a 7-day signed link at checkout plus `POST /api/bookings/lookup`.

### 2. Booking integrity (P0-4, P0-5, P0-6, P0-7)
- Coupons gain `expires_at`, `usage_limit`, `used_count`; expiry, exhaustion and minimum spend are enforced in one place, and a redemption is counted only when the coupon is actually applied — inside the booking transaction.
- Bookings are created as `Requested` and move only along an explicit transition table (`BookingWorkflow`). Illegal jumps (`Confirmed → Completed`, driver starting an unassigned trip, second completion) return 422 instead of silently mutating money.
- Berth allocation runs in a transaction with `lockForUpdate`, is pinned to one coach, marks `is_booked`, and returns `409` when a berth is taken. Berth prices come from `seat_layouts`, never a constant.
- References are 6-digit CSPRNG values checked for uniqueness; ride OTPs come from `random_int`.

### 3. Money correctness (P1-1, P1-2, P1-3)
- `FareCalculatorService` is the only pricing authority. The quote endpoint, the booking endpoint and the browser all render the same numbers; the old 345 km constant and the invented "central West Bengal" coordinates are gone — an unmeasurable trip fails with `422 distance_unresolvable`.
- Per-km rates come from `car_types`, local packages from `local_packages`, airport transfers from `airport_hubs`, round-trip discounts from `route_fares`.
- Wallet payouts and withdrawals run in transactions with `lockForUpdate`, a balance floor, and an append-only `wallet_transactions` ledger whose `balance_after` matches `drivers.wallet_balance`.

### 4. Login hardening (P1-5)
- OTPs are hashed at rest, expire after 10 minutes, allow 5 attempts, and sends are limited to 3 per phone per 10 minutes. The hardcoded `1234` master code is gone. A code is never echoed back once a real SMS gateway is delivering it.

### 5. Maintainability (P2-2, P2-3, P2-4)
- `docs/ASSETS.md` records which asset pipeline is live (`public/css/styles.css` + `public/js/app.js`) and which is dead build output.
- `.github/workflows/ci.yml` runs G1→G6 on every push and pull request.
- This tracker is regenerated from the ledger; `PRD_DERIVED.md` documents the product actually built, with 30 cited gaps.

---

## Still open (deliberate)

| Item | Why it is open |
|---|---|
| `intercity.blade.php` split (P2-1) | 5,920 lines; dozens of tests assert exact markup; no functional gain for the risk. Needs its own cycle. |
| Dead assets (`public/build/**`, `welcome.blade.php`) | Documented in `docs/ASSETS.md`; removal is an owner decision, no gate depends on it. |
| Customer berth map | The 15 berth cells are server-rendered static HTML that matches the seeded coach layout; a DB-driven, route-aware map is a UI cycle of its own (`PRD_DERIVED.md` G11). |
| Admin "Fees & Surcharges" edits | UI state only — not persisted (`PRD_DERIVED.md` G19). |
| Role-based activity log | No audit trail exists for sign-in, sign-out, role changes or account creation: `storage/logs/laravel.log` only receives thrown exceptions (measured `LOG_NEW_BYTES=0` across a full three-role sign-in run), and the admin “audit log” panel is a booking list, not a log. Needs a table + write points + a per-role viewer. |

## How to verify this yourself

```bash
composer install && npm ci
php artisan migrate --force
php artisan test                 # 241 passed
vendor/bin/pint --test           # PASS
npm run build
php artisan serve                # then exercise /, /api/routes/calculate, checkout
```
