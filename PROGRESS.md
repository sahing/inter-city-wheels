# IntercityWheels — Verified Progress

**This file is generated from `LEDGER.md` (rule P2-4).** It reports only what a command proved. `LEDGER.md` is the source of truth; if the two disagree, the ledger wins.

**Last verified:** 2026-11-24 · Laravel 12.69.3 · PHP 8.2.12 · Node v26.7.0

---

## Gate results (quoted)

| Gate | Command | Result |
|---|---|---|
| G0 preflight | `php -v` · `composer --version` · `node -v` · `npm -v` | PHP 8.2.12 · Composer 2.10.2 · Node v26.7.0 · npm 11.19.0 |
| G0c boot | `php artisan about` | Laravel 12.69.3, env `local`, boots clean |
| G1 route audit | `php artisan route:list --json > route-audit.json` | 104 routes emitted; `RouteAuthorizationAuditTest` proves 0 privileged routes without `auth` + `role` |
| G2 authz | `php artisan test --filter=Authorization` / `RouteAuthorizationAuditTest` | PASS, including 401 (guest) and 403 (wrong role) negatives |
| G3 logic | `php artisan test --filter=Pricing` / `Booking` / `Workflow` | PASS |
| G4 suite | `php artisan test` | **213 passed (1,263 assertions)** |
| G4b quality | `vendor/bin/pint --test` | **PASS — 117 files** |
| G5 build | `npm run build` | 61 modules → `public/build/manifest.json` + assets, built in 326 ms |
| G6 release | `php artisan config:clear && php artisan migrate --pretend` | config cleared; 14 index statements + 3 schema changes, no errors |

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

## How to verify this yourself

```bash
composer install && npm ci
php artisan migrate --force
php artisan test                 # 191 passed
vendor/bin/pint --test           # PASS
npm run build
php artisan serve                # then exercise /, /api/routes/calculate, checkout
```
