# Driver Bidding Marketplace

A reverse auction for one booking: the admin publishes a **start price** (the most they will pay a driver) and a **minimum margin percentage**, then sends a private link to verified drivers. Drivers bid **downwards**. The lowest valid bid wins once the admin approves it — and only then does the driver learn who the customer is.

Implementation: `app/Services/BiddingService.php`, `app/Models/BiddingSession.php`, `app/Http/Controllers/Admin/BiddingAdminController.php`, `app/Http/Controllers/Api/BiddingController.php`, `app/Http/Controllers/BiddingPageController.php`, `resources/views/bidding.blade.php`.

---

## 1. Lifecycle

```
Customer books (Requested)
        │
        ▼  admin: "Send to driver bidding" (gavel icon in Bookings)
   Bidding ── open auction, token link, deadline
        │        drivers bid ↓ (must undercut current lowest by ≥ ₹1)
        │
        ├── deadline passes ──► closed (lazy close + scheduled sweep)
        ├── admin closes ─────► closed
        ├── admin cancels ────► cancelled ──► booking back to Confirmed
        └── admin approves ───► awarded ────► booking Driver Assigned
                                                     │
                                                     ├─ driver sees full details
                                                     ├─ customer sees driver + vehicle (booking payload)
                                                     └─ payout fixed at the winning bid
```

Booking status uses the existing state machine (`App\Services\BookingWorkflow`), so the auction cannot bypass it:

| Step | Transition | Legal from |
|---|---|---|
| Open bidding | `* → Bidding` | `Requested`, `Confirmed` |
| Award | `Bidding → Driver Assigned` | `Bidding` |
| Cancel | `Bidding → Confirmed` | `Bidding` |

---

## 2. Money model

| Figure | Where it comes from |
|---|---|
| Customer fare | `bookings.total_amount` (priced by `FareCalculatorService`) |
| Minimum admin margin | `bidding_sessions.admin_fee_pct` (per session, default from `platform_settings.bidding_admin_fee_pct`, fallback 15%) |
| Max driver price | `floor(fare × (1 − margin%))` |
| Start price | Admin-entered, must be `> 0` and `≤ max driver price` |
| Winning bid | Lowest non-flagged bid at close |
| **Admin profit** | `fare − winning bid` |
| **Driver payout** | The winning bid (`bookings.driver_payout_amount`) |

Trip completion pays `driver_payout_amount` when present, otherwise the legacy 90% split for manually allotted trips (`DriverBiddingController::completeTrip`).

Worked example — fare ₹2,469, margin 15%:

```
max driver price = floor(2469 × 0.85) = 2098
admin sets start    = 2097
driver bids         = 1997
admin profit        = 2469 − 1997 = 472
driver payout       = 1997
```

---

## 3. What each party can see

| Information | Bidding driver | Winner (after approval) | Admin |
|---|---|---|---|
| Booking ID | ✅ | ✅ | ✅ |
| Pickup / drop **city** | ✅ | ✅ | ✅ |
| Distance (km) | ✅ | ✅ | ✅ |
| Departure date & time | ✅ | ✅ | ✅ |
| Start price | ✅ | ✅ | ✅ |
| Current lowest bid (amount only) | ✅ | ✅ | ✅ |
| Own bid history | ✅ | ✅ | ✅ |
| Customer name / mobile / email | ❌ | ✅ | ✅ |
| Exact pickup / drop address | ❌ | ✅ | ✅ |
| Customer fare | ❌ | ❌ (payout shown instead) | ✅ |
| Admin margin / profit | ❌ | ❌ | ✅ |
| Other drivers' identities & bids | ❌ | ❌ | ✅ |

**Enforced on the server.** `BiddingSession::toDriverPayload()` is built from the auction row only — it never joins the booking. There is no field to hide, no CSS class to inspect, and no "view source" trick: the JSON physically lacks the keys. `DriverBiddingMarketplaceTest::test_driver_payload_hides_customer_identity_contact_addresses_and_fare` asserts both the missing keys and the absent values.

The reveal is server-side too: `/bid/{token}` renders the full booking **only** when the signed-in driver is the winning bidder (`BiddingPageController`).

---

## 4. Bid rules

| Rule | Enforcement |
|---|---|
| Drivers must be registered and verified | `role:driver` middleware + `drivers.is_verified` |
| One driver cannot shill with a second account | A bid from the same IP as a *different* driver is stored but marked `flagged`; flagged bids are shown to the admin and excluded from the provisional win (an admin can still award one explicitly) |
| Every bid must undercut the current lowest | `amount ≤ current_lowest − ₹1` (first bid: `≤ start_price − ₹1`), re-checked inside a row lock |
| Bid amount is positive | `≥ ₹1`, numeric |
| Bidding window | `closes_at`; late bids get `409 bidding_closed` |
| Bid spam | 20 bids per driver per session (`429 bid_limit_reached`) |
| One auction at a time per booking | `409 bidding_already_open` |
| Timestamps | Every bid row is stamped; admin view shows `placed_at` |
| Link expiry | Token is meaningless once the session is `closed`/`awarded`/`cancelled` |
| Only approval assigns | `award()` is the single path to `Driver Assigned` |

Unverified, guest and customer attempts are refused: `401` (no session), `403` (`driver_not_eligible` / wrong role).

---

## 5. Endpoints

### Admin (`auth` + `role:admin`)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/admin/biddings?status=open` | Auction board (booking ref, route, start, lowest, profit, bid count) |
| `POST` | `/api/admin/bookings/{id}/bidding` | Open an auction: `start_amount`, `admin_fee_pct`, `closes_at`, `admin_notes` → returns `share.url`, `share.whatsapp_url`, `share.text` |
| `GET` | `/api/admin/biddings/{id}` | Full detail: booking in full, every bid with driver identity, vehicle, IP, timestamps |
| `POST` | `/api/admin/biddings/{id}/close` | Close now (winner = current lowest) |
| `POST` | `/api/admin/biddings/{id}/award` | Approve winner; optional `bid_id` to override the lowest |
| `POST` | `/api/admin/biddings/{id}/cancel` | Abandon; booking returns to `Confirmed` |

### Driver / public

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/bid/{token}` | public | Mobile bidding page (masked) |
| `GET` | `/api/driver/auctions` | `auth` + `role:driver` | Live auctions for the driver portal (masked, open only) |
| `GET` | `/api/bidding/{token}` | public | Masked JSON payload |
| `POST` | `/api/bidding/{token}/bids` | `auth` + `role:driver` | Place a bid `{amount}` |
| `GET` | `/api/bidding/{token}/mine` | `auth` + `role:driver` | Own bids + remaining allowance |

---

## 6. WhatsApp flow

1. Admin opens the auction → modal shows the link, a copy button and a **Share on WhatsApp** button.2. Message (auto-generated, no customer identity):

```
IntercityWheels bidding
Booking ICW-213822
Kolkata → Durgapur • 180 km • closes 25 Nov 2026, 18:00
Start price ₹2,097
Bid your lowest price — every bid must undercut the current lowest.
https://<host>/bid/<48-char-token>
```

3. Driver opens the link on mobile: sees the route, distance, **departure date & time**, price and deadline; signs in with the inline form if not already signed in (`?next=/bid/<token>` is validated as a local path — no open redirect).
   A signed-in driver also sees the same auction in the portal's **Live Bids & Auctions** tab (`GET /api/driver/auctions`): From/To city, KM, departure date & time, start price, current lowest and a link to this page. The tab is server-fed — the four hard-coded demo trips (fake passenger names and phone numbers) are gone.
4. Bid submitted → page reloads with the new lowest price locked in.
5. Admin approves → driver's page shows the customer's name, mobile, addresses, departure time and their payout.

---

## 7. Data

```
bidding_sessions
  id, booking_id → bookings, token (unique, 48 chars)
  pickup_city, drop_city, distance_km        -- snapshot; keeps PII out of the payload joint
  start_amount, admin_fee_pct
  status (open|closed|awarded|cancelled)
  closes_at, closed_at, awarded_bid_id → driver_bids, awarded_at, opened_by → users, admin_notes

driver_bids  (+ session_id, is_winning, ip_address, flagged, flag_reason)
bookings     (+ driver_payout_amount, assigned_bid_id → driver_bids)
```

`is_winning` is recomputed on every bid so exactly one row is the provisional winner; `award()` clears and re-sets it and stamps `awarded_bid_id`.

---

## 8. Scheduled housekeeping

`php artisan bidding:close-expired` closes auctions past their deadline; `routes/console.php` schedules it every minute with `withoutOverlapping()`. The request path also closes lazily, so a missing scheduler can never leave an expired auction accepting bids.

---

## 9. Tests

`tests/Feature/DriverBiddingMarketplaceTest.php` — 21 tests covering: opening (fee ceiling, duplicate open, non-biddable booking), masking (keys + values, both JSON and rendered page), bidding rules (descending, equal-bid rejection, unverified driver, guest/customer refusal, deadline close, manual close), awarding (assignment, payout, profit, double-award refusal, no-bids refusal), the reveal boundary (winner sees PII, loser does not), same-IP flagging, cancellation, and admin-only access for every admin endpoint.

`tests/Feature/DriverAuctionFeedTest.php` — 9 tests covering the portal feed: masked payload (keys + values), own-bid count and lowest flag per viewer, only-open filtering (closed, cancelled and expired excluded), driver-only access, the portal's auction tab, the winner's portal card (contact details + locked payout + bidding badge), the loser's portal, the bidding-page reveal, and departure date & time.

Live end-to-end proof (real HTTP sessions, `php -S`, one run through the whole chain):

```
BOOKING ICW-152049 fare=8580
OPEN_BIDDING 201 start=4000 maxDriver=7293
WHATSAPP wa.me  SHARE_HAS_PII=False
FEED 200 count=1  Pune -> Goa  KM=450  departure=2026-12-02T06:30  start=4000
FEED_MASKED phone_absent=True name_absent=True address_absent=True
PAGE 200 route+km+date-time, start price, masked
BID 200 amount=3900 lowest=3900 you_lowest=True
AWARD 200 status=Driver Assigned payout=3900
WINNER_PORTAL name=True phone=True payout_label=True payout_value=True bidding_badge=True
WINNER_PAGE revealed=True phone=True payout=True
SECOND_DRIVER feed count=0  LOSER_PORTAL name=False phone=False ref=False
LOSER_PAGE awarded_to_other=True phone=False
```

Earlier cycle (admin board + single-driver smoke) left intact for the record:

```
BOOKING: ICW-213822 fare=2469
OPEN: 201 start=2097 maxDriver=2098   SHARE_HAS_PII: False
PAGE: 200 showsRoute=True hasSignIn=True   PAGE_HAS_PII: False
BID: 200 amount=1997 lowest=1997 youLowest=True
BOARD: count=1 lowest=1997 profit=472 bids=1
AWARD: 200 status=Driver Assigned payout=1997 margin=472 driver=Ramesh Patil
WINNER_PAGE: revealed=True pii=True phone=True
LOSER_VIEW_PII: False
```
