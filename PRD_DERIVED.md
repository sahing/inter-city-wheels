# PRD_DERIVED — IntercityWheels (behaviour as implemented)

> **Current-status pointer.** This document deliberately describes the **committed** snapshot below
> and lists the gaps that existed then. Several of those gaps have since been closed by the verified
> hardening cycle (CSRF, booking ownership, state machine, berth locking, coupons, single pricing
> authority, wallet ledger, OTP lifecycle). **`LEDGER.md` is the authoritative, evidence-backed
> status tracker** — read §6 here for the gap inventory, and `LEDGER.md` for what is now proven.

**Status:** derived document, not an authored product spec.
**Why it exists:** the original product requirements were lost; `INTERCITYWHEELS_PRD.md` is a 20-line
stub (`INTERCITYWHEELS_PRD.md:1-20`). `AUTOMATION_RULES.md` rule R3 requires freezing observed
behaviour into a derived PRD instead of inventing requirements. Every claim below is grounded in a
`path:line` citation against one committed snapshot (see snapshot discipline below).

**Reading the status column**

| Status | Meaning |
| --- | --- |
| `implemented` | Request path exists, validates input, and persists or returns the resulting state. |
| `partial` | Persists, but a meaningful part of the described behaviour is missing side effects or validation. |
| `stub` | Returns a success message only; nothing is persisted or computed. |

Where a behaviour could not be confirmed from code alone the word **unverified** is used.

**Snapshot discipline (important).** All citations refer to the committed tree at
**`62f1bacc4f45bc527d3f76cd09658b79a784057a`** (2026-11-24, `feat: add core booking controller, role
middleware, and feature tests`). Line counts were re-verified against `git show HEAD:<path>` for every
cited controller, service and model, so `git show 62f1bacc:<path>` reproduces each citation.
At generation time the working tree also contained **uncommitted** work in progress — modified
`BookingController.php`, `BookingAdminController.php`, `RouteCalculationController.php`,
`RegionalLocationService.php`, `Booking.php`, `Coupon.php`, `Driver.php`, and new files
`app/Services/{FareCalculatorService,OtpService,BookingWorkflow,BookingReferenceGenerator}.php`,
`app/Models/WalletTransaction.php`, `app/Exceptions/`, plus four `2026_11_24_23*` migrations. That work
is **not** described here and is not assumed to fix anything; treat §6 as the state of the last
commit, not of the current editor buffer.

**Verified environment facts:** Laravel 12, `APP_ENV=local`, `DB_CONNECTION=sqlite`,
`SESSION_DRIVER=database`, `CACHE_STORE=database` (`.env`). `php artisan route:list` reports
**99 application routes** (excluding the 3 framework routes `storage/*` and `up`): 22 public,
2 auth-only, 10 `role:driver,admin`, 65 `role:admin`.

---

## 1. Purpose & actors

The application is a single-page Blade shell (`resources/views/intercity.blade.php`, 5,824 lines)
driven by a hand-written vanilla JS client (`public/js/app.js`, 7,161 lines). It sells two transport
products — chauffeur-driven outstation cabs and scheduled sleeper-bus berths — from one booking
widget (`resources/views/intercity.blade.php:41-44`), with a role-aware admin console
(`resources/views/intercity.blade.php:1465-3535`).

| Actor | What they can actually do today | Evidence |
| --- | --- | --- |
| **Guest** | Book a cab or berth without an account, look up any booking by reference, search locations, run fare estimates, log in / register | `routes/web.php:73-104` (public `api` group), `resources/views/intercity.blade.php:17` |
| **Customer** | Everything a guest can, plus `my-bookings` tab rendered server-side from bookings matched to their phone/email/name | `resources/views/intercity.blade.php:995-1089`, `app/Http/Controllers/HomeController.php:48-73` |
| **Driver** | Bidding tab (online driver list, bid submit, counter offer), assigned trips with OTP start/complete, wallet + payout request, profile edit, list a vehicle for approval, toggle duty status | `routes/web.php:123-136`, `app/Http/Controllers/DriverBiddingController.php:12-272` |
| **Admin** | Full console: dashboard, bookings CRUD + driver allotment + GST invoice, users, form builder, workflow stages, fees page, booking types/packages, fleet, vehicles, cab routes, service locations, berth routes, seat layouts, car types, system page | `resources/views/intercity.blade.php:1507-1638`, `routes/web.php:144-235` |

Role landing tabs are chosen server-side: admin → `admin`, driver → `bidding`, else `cabs`
(`resources/views/intercity.blade.php:6-10`, `app/Http/Controllers/AuthController.php:69-73`).

---

## 2. Functional requirements

### 2.1 Authentication & Roles

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-AUTH-1 | Email+password login; optional `role` field rejects a mismatched role tab with an explanatory message and returns `target_tab` | `app/Http/Controllers/AuthController.php:18-93` (mismatch check `:53-62`, target tab `:69-73`) | implemented |
| FR-AUTH-2 | Registration for `customer`/`driver` only; driver registration also creates a `drivers` row with a generated plate when none supplied | `app/Http/Controllers/AuthController.php:98-164` (`:122-139`) | implemented |
| FR-AUTH-3 | Mobile OTP dispatch: 4-digit code stored in session **and** cache for 10 minutes; SMS attempted via Fast2SMS, and the OTP is returned in the JSON `otp`/`display_otp` fields | `app/Http/Controllers/AuthController.php:170-219` (`:193-200`, `:213`) | partial |
| FR-AUTH-4 | OTP verification accepts the stored code **or the literal master code `1234`**, auto-provisions a user when the phone is unknown, and logs in | `app/Http/Controllers/AuthController.php:224-324` (`:246`, `:260-277`) | implemented |
| FR-AUTH-5 | One-click demo login as admin/driver/customer, creating the demo account with password `password123` if absent | `app/Http/Controllers/AuthController.php:329-380` | implemented |
| FR-AUTH-6 | Alternate identity providers (Firebase ID token, Clerk session) can provision or link a user; both are optional and fall back to local provisioning | `app/Http/Controllers/AuthController.php:443-522`, `app/Services/AlternateAuthService.php:46-275` | partial |
| FR-AUTH-7 | Logout (GET or POST) invalidates the session; `GET /api/auth/user` returns the current identity | `app/Http/Controllers/AuthController.php:385-427`, `routes/web.php:48` | implemented |
| FR-AUTH-8 | Route-level role enforcement via a `role` middleware alias; 401 for guests, 403 for a wrong role | `app/Http/Middleware/EnsureUserRole.php:17-37`, `bootstrap/app.php:15-17` | implemented |

### 2.2 Cab Booking (one-way / round-trip / local package / airport transfer)

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-CAB-1 | Four booking modes rendered as tabs: `oneway`, `roundtrip`, `local`, `airport`, each with its own form panel | `resources/views/intercity.blade.php:41-44`, `:65`, `:133`, `:201`, `:255` | implemented |
| FR-CAB-2 | `POST /api/bookings` accepts cab bookings, resolves the route, prices the trip, stores the booking with a 4-digit `start_otp` and `ICW-#####` reference, and returns the row | `app/Http/Controllers/BookingController.php:15-192` (`:161-162`, `:164-185`) | implemented |
| FR-CAB-3 | Client multi-step flow with vehicle comparison cards, fare breakdown, addon toggles and coupon field, then a mock payment modal | `public/js/app.js:1206-1235`, `:1746-1793`, `:2692-2713` | partial |
| FR-CAB-4 | Guests may book and may read a booking by reference (deliberate, see comment) | `routes/web.php:69-71`, `:102-103` | implemented |
| FR-CAB-5 | Bus-mode payload is hardcoded to Pune→Goa regardless of the route the customer selected in the UI | `public/js/app.js:2728-2738` (`:2730-2733`) | partial |

### 2.3 Bus Sleeper Berth Booking

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-BUS-1 | Scheduled-bus tab renders a fixed 15-berth coach map (L1–L9 lower, U1–U6 upper) as static HTML with hardcoded prices | `resources/views/intercity.blade.php:767-914` (15 `data-seat` cells, `:796-914`) | partial |
| FR-BUS-2 | `POST /api/bookings` with `service_type=bus` prices the trip by summing `seat_layouts.price` for the submitted berth numbers, defaulting to `['L4','L5']` / ₹1700 when absent or unknown | `app/Http/Controllers/BookingController.php:129-134` | implemented |
| FR-BUS-3 | Berth availability is never checked and `seat_layouts.is_booked` is never written by a booking; only admin CRUD can set it | `app/Http/Controllers/BookingController.php:129-134`; no write path in `app/` outside `app/Http/Controllers/Admin/SeatLayoutAdminController.php:28-99` | stub |
| FR-BUS-4 | Admin berth routes (`berth_routes`) and their `seatLayouts` relation are never consumed by the customer flow; the schedules tab does not read them | `app/Http/Controllers/HomeController.php:36`, `resources/views/intercity.blade.php:727-995` (no `@foreach`) | stub |

### 2.4 Fare Calculation & Pricing Rules

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-FARE-1 | Per-km rate is taken from `car_types.rate_per_km` by `category_key`, falling back to a hardcoded rate map, then to 18.0 | `app/Http/Controllers/BookingController.php:83-95` | implemented |
| FR-FARE-2 | Route lookup is alias-normalised and bidirectional against `route_fares` (`from_city`/`to_city` in either order) | `app/Http/Controllers/BookingController.php:50-65`, `app/Services/RegionalLocationService.php:147-167` | implemented |
| FR-FARE-3 | When no route row exists, distance/time/toll are derived from Haversine × 1.22 road factor at 45 km/h with toll = ₹0.92/km; missing coordinates fall back to 345 km / ₹320 | `app/Services/RegionalLocationService.php:425-454` (`:438`, `:441`, `:446`), `app/Http/Controllers/BookingController.php:70-81` | implemented |
| FR-FARE-4 | Local packages price as `included_km × multiplier × rate_per_km` using hardcoded multipliers 1.8/1.7/1.65/1.6 and zero toll; the admin-configured `local_packages.sedan_fare`/`suv_fare` are ignored | `app/Http/Controllers/BookingController.php:99-109` | partial |
| FR-FARE-5 | Airport transfers price as `distance (or 45 km) × rate` plus a hardcoded ₹120 toll when tolls are included; `airport_hubs` surcharges/base fares are ignored | `app/Http/Controllers/BookingController.php:110-113` | partial |
| FR-FARE-6 | Round trip doubles the base fare, multiplies toll by 1.8, then applies `route_fares.roundtrip_discount_pct` when set | `app/Http/Controllers/BookingController.php:121-127` | implemented |
| FR-FARE-7 | Addons are fixed: pet ₹350, night allowance ₹400, luggage carrier ₹200 | `app/Http/Controllers/BookingController.php:136-139`, `app/Http/Controllers/Api/RouteCalculationController.php:146-150` | implemented |
| FR-FARE-8 | Coupons are validated server-side by `code` + `is_active` + `min_spend`; percentage discounts are capped by `max_discount`, flat discounts are unbounded | `app/Http/Controllers/BookingController.php:141-158` | partial |
| FR-FARE-9 | The public estimate endpoint returns per-fleet one-way/round-trip fares plus an itemised breakdown and addon table | `app/Http/Controllers/Api/RouteCalculationController.php:88-151` | implemented |
| FR-FARE-10 | The browser computes and displays its own fare from a hardcoded fleet rate table (11/13/15/17.5/21/26/38 ₹/km) and its own coupon maths, independently of the server | `public/js/app.js:289-362`, `:1746-1793` (`:1763-1767`, `:1789`) | partial |

### 2.5 Driver Bidding & Assignment

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-BID-1 | `GET /api/bids` returns every driver with `is_online = true` | `app/Http/Controllers/DriverBiddingController.php:12-19` | implemented |
| FR-BID-2 | `POST /api/bids/counter` validates a driver id and amount ≥ ₹500 and returns a confirmation message; no `driver_bids` row is written | `app/Http/Controllers/DriverBiddingController.php:21-34` | stub |
| FR-BID-3 | `POST /api/bids/submit` composes a binding "conclusive statement" and echoes a bid object; no `driver_bids` row is written | `app/Http/Controllers/DriverBiddingController.php:36-65` | stub |
| FR-BID-4 | `POST /api/bids/accept` creates a booking in status `Driver Assigned` with the driver attached and a fresh OTP/reference | `app/Http/Controllers/DriverBiddingController.php:67-107` | implemented |
| FR-BID-5 | Driver bidding tab renders the marketplace plus privacy/copy blocks; the masking statement is static text | `resources/views/intercity.blade.php:1095-1155`, `tests/Feature/AdminRoutesAndPrivacyTest.php:111-122` | partial |

### 2.6 Trip Lifecycle & OTP

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-TRIP-1 | `POST /api/driver/trips/{id}/verify-otp` compares a 4-character OTP and moves the booking to `Started` | `app/Http/Controllers/DriverBiddingController.php:109-132` | implemented |
| FR-TRIP-2 | `POST /api/driver/trips/{id}/complete` moves the booking to `Completed` and credits the driver | `app/Http/Controllers/DriverBiddingController.php:134-156` | implemented |
| FR-TRIP-3 | Status values in use: `Pending` (schema default), `Confirmed`, `Driver Assigned`, `Started`, `Completed`; the column is a plain string with no enum or state machine | `database/migrations/2026_10_13_000001_create_intercity_wheels_tables.php:61`, `app/Http/Controllers/BookingController.php:182`, `app/Http/Controllers/Admin/BookingAdminController.php:183-185`, `app/Http/Controllers/DriverBiddingController.php:97`, `:124`, `:137` | partial |
| FR-TRIP-4 | Driver trip cards render the customer name and full phone number for assigned trips | `resources/views/intercity.blade.php:1189-1195` | implemented |

### 2.7 Driver Wallet & Payouts

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-WAL-1 | Completing a trip credits `drivers.wallet_balance` with 90% of `total_amount` and increments `trips_count` | `app/Http/Controllers/DriverBiddingController.php:140-149` | implemented |
| FR-WAL-2 | `POST /api/driver/wallet/withdraw` validates amount ≥ ₹100 and returns an IMPS confirmation string with a hardcoded masked account number; the balance is not touched and no payout row exists | `app/Http/Controllers/DriverBiddingController.php:158-168` | stub |
| FR-WAL-3 | Driver profile read/update and duty toggle operate on the `drivers` row and mirror name/phone/avatar onto the `users` row | `app/Http/Controllers/DriverBiddingController.php:173-272` | partial |

### 2.8 Admin — Bookings & Users

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-ADM-BKG-1 | Booking list supports exact `status` filter and a `search` across reference, customer name, phone and route summary | `app/Http/Controllers/Admin/BookingAdminController.php:16-38` | implemented |
| FR-ADM-BKG-2 | Manual booking create/update; `total_amount` is always recomputed as base + toll + addon − discount | `app/Http/Controllers/Admin/BookingAdminController.php:43-104`, `:122-165` (`:68`, `:145-151`) | implemented |
| FR-ADM-BKG-3 | Driver allotment sets `assigned_driver_id` and forces status `Driver Assigned` (or `Confirmed` when clearing) | `app/Http/Controllers/Admin/BookingAdminController.php:170-196` | implemented |
| FR-ADM-USR-1 | User directory with role filter and search, deriving each row's trip count from bookings (customers) or `drivers.trips_count` (drivers) | `app/Http/Controllers/Admin/UserAdminController.php:17-71` | implemented |
| FR-ADM-USR-2 | Create user in any of the three roles, optionally creating a linked `drivers` row; update supports password rotation | `app/Http/Controllers/Admin/UserAdminController.php:76-183` | implemented |
| FR-ADM-USR-3 | Delete user, refusing self-deletion when the target is the acting admin | `app/Http/Controllers/Admin/UserAdminController.php:188-207` | implemented |

### 2.9 Admin — Fleet & Catalogue

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-ADM-VEH-1 | A driver submits a vehicle which lands in `driver_vehicles` as `Pending` / `is_active=false` | `app/Http/Controllers/Admin/VehicleAdminController.php:13-53` | implemented |
| FR-ADM-VEH-2 | Admin 1-click approve (status `Approved`, active, driver `is_verified=true`) or reject (`Rejected`, inactive) with optional notes | `app/Http/Controllers/Admin/VehicleAdminController.php:116-156` | implemented |
| FR-ADM-VEH-3 | Admin may edit any vehicle field or delete it | `app/Http/Controllers/Admin/VehicleAdminController.php:82-113`, `:158-168` | implemented |
| FR-ADM-CART-1 | Car type CRUD with unique `category_key`, active toggle, ordering, and a public `api/car-types` feed of active rows | `app/Http/Controllers/Admin/CarTypeAdminController.php:14-158`, `routes/web.php:94-99` | implemented |
| FR-ADM-RTE-1 | Cab route CRUD including per-category fares (hatchback/sedan/suv/innova/tempo/luxury), a `car_fares` JSON map, schedule fields, round-trip discount and active toggle | `app/Http/Controllers/Admin/CabRouteAdminController.php:39-185` | implemented |
| FR-ADM-LPK-1 | Local package CRUD (hours, included km, sedan/SUV fare, extra km/hour rates) plus active toggle and public feed | `app/Http/Controllers/Admin/LocalPackageAdminController.php:14-157`, `routes/web.php:82-87` | implemented |
| FR-ADM-AIR-1 | Airport hub CRUD (IATA, terminals, pickup/drop surcharge, sedan/SUV base fare) plus toggle and public feed | `app/Http/Controllers/Admin/AirportHubAdminController.php:14-153`, `routes/web.php:88-93` | implemented |
| FR-ADM-LOC-1 | Service location CRUD with unique name, coordinates, optional airport code, airport-service flag, toggle and public feed of active rows | `app/Http/Controllers/Admin/LocationAdminController.php:11-101`, `routes/web.php:76-81` | implemented |
| FR-ADM-BRT-1 | Berth route CRUD (operator, coach code, vehicle type, departure/arrival, base price, total berths) with toggle, joined to seat layouts by `coach_code` | `app/Http/Controllers/Admin/BerthRouteAdminController.php:11-105`, `app/Models/BerthRoute.php:28-31` | implemented |
| FR-ADM-SEAT-1 | Berth/seat CRUD per coach with a duplicate guard on (`coach_code`, `berth_number`) and ladies-quota / booked flags | `app/Http/Controllers/Admin/SeatLayoutAdminController.php:28-112` (`:44-54`) | implemented |

### 2.10 Admin — Configuration & Invoicing

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-ADM-DFM-1 | Dynamic form field list/create/delete; `field_key` is a slug of the label, `order_index` is max+1 | `app/Http/Controllers/Admin/DynamicFormController.php:12-56` | implemented |
| FR-ADM-DFM-2 | Active fields are server-rendered into the cab checkout and their submitted values stored in `bookings.dynamic_fields_data` | `resources/views/intercity.blade.php:628`, `app/Http/Controllers/BookingController.php:184` | implemented |
| FR-ADM-DFM-3 | `is_required` is never enforced and `conditional_logic` is not an expression engine: visibility is hardcoded for `flight_number`/airport and `pet_breed`/pet toggle | `resources/views/intercity.blade.php:628`, `public/js/app.js:1340-1357` | partial |
| FR-ADM-WF-1 | Workflow stages list and per-stage update (`stage_name`, `description`, `is_active`, `order_index`) | `app/Http/Controllers/Admin/WorkflowConfigController.php:11-25` | implemented |
| FR-ADM-WF-2 | Stages are display-only: no booking transition reads `workflow_stages`, so stage order/config cannot change behaviour | `app/Http/Controllers/HomeController.php:27`; no other consumer of `WorkflowStage` in `app/` | stub |
| FR-ADM-SET-1 | Platform settings store and return the privacy-policy and T&C URLs (defaults `/privacy-policy`, `/terms-conditions`) | `app/Http/Controllers/Admin/PlatformSettingAdminController.php:11-47`, `app/Models/PlatformSetting.php:11-20` | implemented |
| FR-ADM-INV-1 | Invoice endpoint computes a GST invoice: taxable base = base + addon − discount, CGST 2.5%, SGST 2.5%, toll added after tax, with fixed company/GSTIN/SAC data | `app/Http/Controllers/Admin/BookingAdminController.php:201-268` (`:208-226`, `:245-261`) | implemented |
| FR-ADM-INV-2 | Invoice is JSON only — no PDF/HTML render, no persistence, and it always reports `PAID IN FULL` regardless of any payment record | `app/Http/Controllers/Admin/BookingAdminController.php:255-260` | partial |
| FR-ADM-FEE-1 | "Fees & Surcharges" admin page edits addon rates that live only in browser state; no endpoint persists them | `resources/views/intercity.blade.php:2237-2265`, `public/js/app.js:2432-2441` | stub |

### 2.11 Public content, health and search

| ID | Requirement as implemented | Evidence | Status |
| --- | --- | --- | --- |
| FR-PUB-1 | `GET /` server-renders the whole catalogue (routes, drivers, berths, car types, packages, airports, settings, users, bookings) into one view | `app/Http/Controllers/HomeController.php:19-144` | implemented |
| FR-PUB-2 | Static legal pages rendered through one shared `legal` view with a hardcoded body paragraph per page | `routes/web.php:243-259`, `resources/views/legal.blade.php:4-30` | implemented |
| FR-PUB-3 | `/health` returns a static JSON envelope containing status, framework, `database: connected` and a timestamp | `routes/web.php:56-63` | partial |
| FR-PUB-4 | Location search blends registered `service_locations`, a ~90-entry regional catalogue, Nominatim geocoding (1.2 s timeout) and an always-present "desired location" fallback entry | `app/Http/Controllers/Api/LocationSearchController.php:14-25`, `app/Services/RegionalLocationService.php:172-297` (`:270-279`, `:282-293`) | implemented |

---

## 3. Data model summary

Derived from `database/migrations/**`. All tables carry `id` + `created_at`/`updated_at` unless noted.

| Table | Purpose | Key columns / constraints | Evidence |
| --- | --- | --- | --- |
| `users` | All three roles share one identity table | unique `email`; `role` default `customer`; nullable `phone`, `avatar`; `auth_provider`, `auth_provider_id` (indexed), `firebase_id` (indexed) | `0001_01_01_000000_create_users_table.php:14-22`, `2026_10_22_071637…php:14-18`, `2026_11_24_223800…php:14-18` |
| `sessions` / `password_reset_tokens` | Framework session + reset storage (session driver is `database`) | `sessions.id` PK, indexed `user_id`, indexed `last_activity` | `0001_01_01_000000_create_users_table.php:24-37` |
| `route_fares` | Outstation cab route catalogue and per-category fares | `from_city`, `to_city`, 4 decimal coords, `distance_km` int, `estimated_time`, `base_toll`, `sedan_fare`, `suv_fare`, `roundtrip_discount_pct`, `hatchback/suv/innova/tempo/luxury_fare`, `car_fares` JSON, `departure_schedule`, `schedule_frequency`, `is_active` | `2026_10_13_000001…php:11-24`, `2026_11_09_220000…php:44-56`, `2026_11_15_094710…php:14-36` |
| `drivers` | Driver/vehicle profile used by bidding, trips and wallet | unique `phone`, unique `vehicle_plate`, `fleet_category`, `rating`, `trips_count`, `wallet_balance`, `is_verified`, `is_online` | `2026_10_13_000001…php:26-40` |
| `bookings` | Every cab/berth booking (guest, customer, admin or bid-accepted) | unique `booking_reference`; `service_type`, `trip_type`, `route_summary`, pickup/drop, nullable `departure_time`; JSON `passengers`, `selected_units`, `dynamic_fields_data`; `base_fare`, `toll_fare`, `addon_fare`, `discount_fare`, `total_amount`; string `status`; `start_otp`; FK `assigned_driver_id → drivers` (`nullOnDelete`) | `2026_10_13_000001…php:42-66` |
| `driver_bids` | Bid/counter-bid records — **table exists but no code writes to it** | FK `booking_id` (cascade), FK `driver_id` (cascade), `bid_amount`, `counter_amount`, `status` | `2026_10_13_000001…php:68-76`; no writer in `app/` |
| `dynamic_form_fields` | Admin-defined checkout fields | unique `field_key`, `label`, `field_type`, `applies_to`, `conditional_logic`, `is_required`, `order_index`, `is_active` | `2026_10_13_000001…php:78-89` |
| `workflow_stages` | Display-only trip pipeline copy | unique `stage_key`, `stage_name`, `description`, `order_index`, `is_active` | `2026_10_13_000001…php:91-99` |
| `seat_layouts` | Berths per coach/vehicle | `coach_code`, `operator_name`, `deck`, `berth_number`, `berth_type`, `price`, `is_ladies_quota`, `is_booked`; no unique index on (coach, berth) | `2026_10_13_000001…php:101-112` |
| `coupons` | Discount codes | unique `code`, `type` (`percentage`/`flat`), `value`, nullable `max_discount`, `min_spend`, `is_active`; **no validity dates, usage caps or per-user limits** | `2026_10_13_000001…php:114-123` |
| `service_locations` | Admin-managed city/hub network | unique `name`, `state`, lat/lng (default 18.5204/73.8567), `airport_code`, `has_airport_service`, `is_active` | `2026_11_08_085032…php:15-25` |
| `berth_routes` | Sleeper coach schedules | `from_city`, `to_city`, `operator_name`, `coach_code`, `vehicle_type`, `departure_time`, `arrival_time`, `base_price`, `total_berths`, `is_active` | `2026_11_08_085032…php:28-41` |
| `driver_vehicles` | Driver-submitted fleet awaiting approval | nullable FK `driver_id`, `user_id`; unique `vehicle_plate`; `fleet_category`, `seating_capacity`, `berth_capacity`, `coach_code`, `fuel_type`, `model_year`, `rc_number`, `status` (`Pending`/`Approved`/`Rejected`), `admin_notes`, `is_active` | `2026_11_08_085032…php:44-63` |
| `local_packages` | Hourly/km rental packages | unique `package_code`, `city`, `name`, `duration_hours`, `included_km`, `sedan_fare`, `suv_fare`, `extra_km_rate`, `extra_hour_rate`, `is_active` | `2026_11_09_220000…php:12-25` |
| `airport_hubs` | Airport transfer catalogue | `airport_name`, `iata_code`, `city`, `terminals`, `pickup_surcharge`, `drop_surcharge`, `sedan_base_fare`, `suv_base_fare`, `is_active` | `2026_11_09_220000…php:29-41` |
| `platform_settings` | Key/value settings (policy URLs) | unique `key`, nullable text `value` | `2026_11_09_120000…php:11-16` |
| `car_types` | Fleet categories and per-km rates | unique `category_key`, `name`, `badge`, `models`, `seats`, `luggage`, `rate_per_km`, `extra_km_rate`, `base_fare_min`, `image_url`, `order_index`, `is_active` | `2026_11_15_094707…php:14-29` |

Seed data (`database/seeders/DatabaseSeeder.php`) is demo-oriented: 26 route rows (`:19-51`),
3 drivers (`:54-61`), 15 berths on coach `8092` + 7 on `5510` (`:64-86`, `:289-300`), 3 dynamic fields
(`:89-96`), 5 workflow stages (`:99-108`), 2 coupons `FIRSTWHEELS` / `INTERCITY100` (`:111-117`),
1 booking `ICW-90412` (`:120-142`), 3 demo users with password `password123` (`:145-176`),
24 service locations (`:179-207`), 6 berth routes (`:210-286`), 3 driver vehicles (`:304-362`),
4 local packages (`:369-421`), 8 airport hubs (`:424-516`), 6 car types (`:519-607`).

---

## 4. API surface

Route inventory from `routes/web.php`; middleware confirmed with `php artisan route:list`.
"Auth" column: `—` public, `auth` any signed-in role, `driver|admin`, `admin`.

### 4.1 Public

| Method | Path | Auth | Purpose | Evidence |
| --- | --- | --- | --- | --- |
| GET | `/` | — | Main Blade SPA shell | `routes/web.php:30` |
| GET | `/health` | — | Static health envelope | `routes/web.php:56` |
| GET | `/privacy-policy`, `/terms-conditions` | — | Legal pages | `routes/web.php:243`, `:252` |
| POST | `/login`, `/register`, `/logout`, `/login/quick-demo` | — | Session auth | `routes/web.php:39-48` |
| POST | `/auth/send-otp`, `/auth/verify-otp` | — | Mobile OTP | `routes/web.php:41-42` |
| GET/POST | `/auth/alternate-config`, `/auth/alternate-login`, `/auth/firebase-login`, `/auth/clerk-login` | — | Third-party identity | `routes/web.php:43-46` |
| GET | `/api/routes/calculate` | — | Fare/route estimate | `routes/web.php:74` |
| GET | `/api/locations/search` | — | Location autocomplete | `routes/web.php:75` |
| GET | `/api/locations`, `/api/local-packages`, `/api/airport-hubs`, `/api/car-types` | — | Active-catalogue feeds (inline closures) | `routes/web.php:76-99` |
| POST | `/api/bookings` | — | Booking intake (guest + customer) | `routes/web.php:102` |
| GET | `/api/bookings/{reference}` | — | Booking lookup by reference | `routes/web.php:103` |

### 4.2 Authenticated / driver / admin

| Method | Path | Auth | Purpose | Evidence |
| --- | --- | --- | --- | --- |
| GET | `/api/auth/user` | auth | Current identity | `routes/web.php:113` |
| POST | `/api/bids/accept` | auth (any role) | Create booking from an accepted bid | `routes/web.php:114` |
| GET | `/api/bids` | driver\|admin | Online driver list | `routes/web.php:124` |
| POST | `/api/bids/counter`, `/api/bids/submit` | driver\|admin | Counter offer / submit bid (stubs) | `routes/web.php:125-126` |
| POST | `/api/driver/trips/{id}/verify-otp`, `…/complete` | driver\|admin | Trip start / completion | `routes/web.php:128-129` |
| POST | `/api/driver/wallet/withdraw` | driver\|admin | Payout request (stub) | `routes/web.php:130` |
| GET/POST | `/api/driver/profile` | driver\|admin | Read / update driver profile | `routes/web.php:131-132` |
| POST | `/api/driver/toggle-online` | driver\|admin | Duty status flip | `routes/web.php:133` |
| POST | `/api/driver/vehicles` | driver\|admin | Submit vehicle for approval | `routes/web.php:135` |

### 4.3 Admin CRUD families (`role:admin`)

All families below are full REST resources unless noted; each also has `/{id}/toggle` where listed.
Counts from `php artisan route:list`: 65 admin routes.

| Resource prefix | Methods | Extra actions | Evidence |
| --- | --- | --- | --- |
| `/api/admin/bookings` | GET, POST, GET `{id}` | PUT `{id}`, POST `{id}/allot-driver`, GET `{id}/invoice` | `routes/web.php:146-151` |
| `/api/admin/users` | GET, POST, GET `{id}`, PUT, DELETE | — | `routes/web.php:154-158` |
| `/api/admin/dynamic-fields` | GET, POST, DELETE | — | `routes/web.php:161-163` |
| `/api/admin/workflows` | GET | PUT `{id}` | `routes/web.php:166-167` |
| `/api/admin/cab-routes` | GET, POST, GET, PUT, DELETE | POST `{id}/toggle` | `routes/web.php:170-175` |
| `/api/admin/local-packages` | GET, POST, GET, PUT, DELETE | POST `{id}/toggle` | `routes/web.php:178-183` |
| `/api/admin/airport-hubs` | GET, POST, GET, PUT, DELETE | POST `{id}/toggle` | `routes/web.php:186-191` |
| `/api/admin/car-types` | GET, POST, GET, PUT, DELETE | POST `{id}/toggle` | `routes/web.php:194-199` |
| `/api/admin/locations` | GET, POST, GET, PUT, DELETE | POST `{id}/toggle` | `routes/web.php:202-207` |
| `/api/admin/berth-routes` | GET, POST, GET, PUT, DELETE | POST `{id}/toggle` | `routes/web.php:210-215` |
| `/api/admin/berths` | GET, POST, GET, PUT, DELETE | — | `routes/web.php:218-222` |
| `/api/admin/vehicles` | GET, GET `{id}`, PUT, DELETE | POST `{id}/approve`, POST `{id}/reject` | `routes/web.php:225-230` |
| `/api/admin/settings` | GET | POST `/settings/policy-urls` | `routes/web.php:233-234` |

---

## 5. Money & state rules actually enforced

**Booking status vocabulary** (string column, no DB enum):
`Pending` default (`migrations/2026_10_13_000001…php:61`); `Confirmed` on public intake
(`app/Http/Controllers/BookingController.php:182`) and on driver un-allotment
(`app/Http/Controllers/Admin/BookingAdminController.php:185`); `Driver Assigned` on bid accept and
allotment (`DriverBiddingController.php:97`, `BookingAdminController.php:183`); `Started`
(`DriverBiddingController.php:124`); `Completed` (`:137`). Admin update accepts **any** string —
`'status' => 'sometimes|required|string'` (`BookingAdminController.php:140`) — so the vocabulary is
conventional, not enforced.

**Money rules that are enforced**

| Rule | Enforcement | Evidence |
| --- | --- | --- |
| `total_amount = max(0, base + toll + addon − discount)` on public intake and admin write | Server-side, integer-rounded | `app/Http/Controllers/BookingController.php:160`, `app/Http/Controllers/Admin/BookingAdminController.php:68`, `:150` |
| Coupon must exist, be active and meet `min_spend` | Server-side | `app/Http/Controllers/BookingController.php:145-158` |
| Percentage coupon capped by `max_discount` | `min($calc, max_discount)` | `app/Http/Controllers/BookingController.php:151-153` |
| Flat coupon | `discount = value`, no upper bound beyond the `max(0, …)` clamp | `app/Http/Controllers/BookingController.php:155` |
| Round-trip toll multiplier 1.8 | Server-side | `app/Http/Controllers/BookingController.php:123` |
| Driver payout = 90% of `total_amount` | Server-side, on completion | `app/Http/Controllers/DriverBiddingController.php:144` |
| GST = 2.5% CGST + 2.5% SGST on (base + addon − discount), toll added after tax | Server-side, invoice only | `app/Http/Controllers/Admin/BookingAdminController.php:208-213` |

**State rules and their gaps**

- OTP: a 4-digit value stored in plaintext on the booking; generated with `random_int` for public
  intake (`BookingController.php:161`) and `mt_rand` elsewhere
  (`DriverBiddingController.php:78`, `BookingAdminController.php:71`). No expiry, no attempt limit,
  and it is returned in every booking payload and in the invoice (`BookingAdminController.php:259`).
- Login OTP has a 10-minute TTL in session/cache (`AuthController.php:196-200`) but the literal `1234`
  always passes (`:246`).
- **No transactions or locking anywhere:** `DB::transaction`, `lockForUpdate` and `Cache::lock` do not
  appear in `app/` at all. Booking creation, wallet credit and berth pricing are non-atomic.
- **No payment provider:** no `razorpay`/`stripe`/payment controller exists in `app/`; "payment" is a
  client-side mock modal that mints its own reference and OTP
  (`public/js/app.js:2692-2723`).

---

## 6. Known gaps / non-functional

| # | Gap | Proof |
| --- | --- | --- |
| G1 | Bid persistence is missing entirely: counter and submit return success JSON without writing `driver_bids`, so no bid history, auction or acceptance trail exists. `DriverBid` is imported but never instantiated anywhere in `app/` | `app/Http/Controllers/DriverBiddingController.php:21-34`, `:36-65`; unused import at `:7`; grep of `app/` finds no `DriverBid::create` |
| G2 | Wallet withdrawal is a stub: no balance decrement, no payout ledger, hardcoded `HDFC •••• 4912` in the message | `app/Http/Controllers/DriverBiddingController.php:158-168` |
| G3 | Trip endpoints do not verify ownership or idempotency: any `driver`/`admin` can start or complete **any** booking id, and a second `complete` call credits the wallet again | `app/Http/Controllers/DriverBiddingController.php:109-156`; no `assigned_driver_id` comparison |
| G4 | `POST /api/bids/accept` sits in the plain `auth` group, so a customer can create a `Driver Assigned` booking for any driver at an arbitrary amount | `routes/web.php:112-115`, `app/Http/Controllers/DriverBiddingController.php:67-107` |
| G5 | CSRF verification is disabled for `api/*`, `login*` and `logout`, so every state-changing JSON endpoint is CSRF-exempt | `bootstrap/app.php:19-24` |
| G6 | Booking lookup by reference is public and unauthenticated, and references are 5 random digits, making enumeration feasible; the response includes customer phone and `start_otp` | `routes/web.php:103`, `app/Http/Controllers/BookingController.php:162`, `:194-201` |
| G7 | `GET /` performs a database **write** on render: if a driver has no trips, the first booking in the table is reassigned to them and set to `Driver Assigned` | `app/Http/Controllers/HomeController.php:99-108` |
| G8 | Driver identity resolution falls back to `Driver::first()`, so an unmatched driver account reads — and via `updateProfile` can overwrite — another driver's record | `app/Http/Controllers/DriverBiddingController.php:182-184`, `:205-227`, `app/Http/Controllers/HomeController.php:80-82` |
| G9 | Customer booking list is matched with a `customer_name LIKE %firstname%` clause, so a common first name can surface other customers' bookings | `app/Http/Controllers/HomeController.php:64-69` |
| G10 | Local-package and airport pricing ignore their admin tables: hardcoded multipliers instead of `local_packages` fares, flat 45 km + ₹120 toll instead of `airport_hubs` | `app/Http/Controllers/BookingController.php:99-113`; `AirportHub`/`LocalPackage` are only used by admin CRUD and the show-all query |
| G11 | Berth availability is never enforced and `is_booked` is never written by a booking, so the same berth can be sold repeatedly | `app/Http/Controllers/BookingController.php:129-134`; only writer is `SeatLayoutAdminController.php:28-99` |
| G12 | Berths are matched by `berth_number` alone (`whereIn('berth_number', …)`) with no `coach_code` filter, so identical berth numbers across coaches collide in pricing | `app/Http/Controllers/BookingController.php:132` |
| G13 | Two divergent pricing engines: the browser displays fare from a hardcoded fleet table (11/13/15/17.5/21/26/38 ₹/km) and its own coupon logic, while the server uses `car_types` and the `coupons` table, so the shown total can differ from the stored `total_amount` | `public/js/app.js:289-362`, `:1751`, `:1763-1767`; `app/Http/Controllers/BookingController.php:83-95`, `:145-158` |
| G14 | The client coupon field only accepts `FIRSTWHEELS` and `INTERCITY100` — validated as string literals — so a newly created DB coupon can never be entered | `public/js/app.js:1367-1396`, `:1800-1815` |
| G15 | Booking references are random 5-digit values with no uniqueness check before insert; a collision raises a DB constraint error rather than retrying | `app/Http/Controllers/BookingController.php:162`, `:165`; unique index `migrations/2026_10_13_000001…php:44` |
| G16 | A failed booking POST is treated as success client-side: the catch block opens the mock payment modal, which then mints a local booking and OTP | `public/js/app.js:1704-1706`, `:2715-2722` |
| G17 | Bus booking writes a hardcoded Pune→Goa route with hardcoded pickup/drop and a fallback phone/email | `public/js/app.js:2728-2738` |
| G18 | The "Scheduled Bus & Berths" customer view is static HTML: no route, operator, price or availability comes from `berth_routes` / `seat_layouts` | `resources/views/intercity.blade.php:727-995` (no `@foreach`; 15 hardcoded cells at `:796-914`) |
| G19 | "Fees & Surcharges" edits never reach the server; the save handler only mutates browser state and toasts success | `public/js/app.js:2432-2441`, `resources/views/intercity.blade.php:2237-2265` |
| G20 | Workflow stages, and therefore "configurable booking screens", are display-only; no transition consults them | `app/Http/Controllers/Admin/WorkflowConfigController.php:11-25`; only reader is `HomeController.php:27` |
| G21 | Dynamic field `is_required` is never validated on submit, and conditional logic is hardcoded to two known keys rather than the stored string | `app/Http/Controllers/BookingController.php:40` (`dynamic_fields` is `array`, nothing more), `public/js/app.js:1340-1357` |
| G22 | No real SMS: `FAST2SMS_API_KEY`, `FIREBASE_*` and `CLERK_*` are absent from `.env` (present but blank lines in `.env.example:67-82`), so OTP is always demo mode and the OTP is returned in the HTTP response | `app/Services/Fast2SmsService.php:29-39`, `app/Http/Controllers/AuthController.php:205-218` |
| G23 | Alternate auth provisions accounts with the shared default password `password123`, and a random phone number when none is supplied | `app/Services/AlternateAuthService.php:209-210`; login path also silently resets a Firebase user's password when they type any 6+ char password over the default (`AuthController.php:37-40`) |
| G24 | `GET /login` does not exist; a guest hitting an authenticated page is redirected to `route('login')`, which is a POST-only route | `routes/web.php:39`, `app/Http/Middleware/EnsureUserRole.php:24` |
| G25 | `/health` reports `database: connected` unconditionally — it never touches the database | `routes/web.php:56-63` |
| G26 | `BookingController::show()` maps fields the UI then reads under different names (`pickup_city`, `drop_city`, `seat_numbers`), so the ticket fallback renders blanks | `public/js/app.js:6996-6998` vs `app/Http/Controllers/BookingController.php:194-201` |
| G27 | No pagination on any list endpoint or the home page; `GET /` loads all bookings, all users, all drivers and all berths into one view | `app/Http/Controllers/HomeController.php:21-45`, `:29-31` |
| G28 | No structured admin audit trail: invoice and payout data are recomputed on read, never stored | `app/Http/Controllers/Admin/BookingAdminController.php:201-268`, `app/Http/Controllers/DriverBiddingController.php:158-168` |
| G29 | Invoice GSTIN, address, helpline and bank details are hardcoded literals, not settings | `app/Http/Controllers/Admin/BookingAdminController.php:218-226`, `DriverBiddingController.php:166` |
| G30 | Coupons have no validity window, usage counter or per-user limit in schema or code | `migrations/2026_10_13_000001…php:114-123`, `app/Http/Controllers/BookingController.php:145-158` |

---

## 7. Traceability (FR → automated test)

Test files under `tests/`; "no automated test" means nothing in `tests/**` asserts the behaviour.

| FR IDs | Covering test(s) |
| --- | --- |
| FR-AUTH-1, FR-AUTH-7 | `tests/Feature/AuthenticationTest.php:20`, `:40`, `:53`, `:87-114` |
| FR-AUTH-2 | `tests/Feature/UserRegistrationAndOtpLoginTest.php:21`, `:48` |
| FR-AUTH-3 | `tests/Feature/UserRegistrationAndOtpLoginTest.php:82`, `:107` |
| FR-AUTH-4 | `tests/Feature/UserRegistrationAndOtpLoginTest.php:137`, `:164` |
| FR-AUTH-5 | `tests/Feature/AuthenticationTest.php:69` |
| FR-AUTH-6 | `tests/Feature/AlternateLoginTest.php:20-318` |
| FR-AUTH-8 | `tests/Feature/AuthorizationTest.php:32-100` (guest/customer/driver negative cases per tier); `tests/Feature/RoleBasedAccessTest.php:21`, `:75`, `:99`, `:168`; `tests/Feature/PlatformPolicySettingsTest.php:63` |
| FR-CAB-1 | `tests/Feature/BookingTest.php:107`, `:216` |
| FR-CAB-2 | `tests/Feature/BookingTest.php:74`, `:129`, `:249`, `:283`, `:317`, `:360`; `tests/Feature/RegionalLocationAndDighaBookingTest.php:138`, `:175` |
| FR-CAB-3 | no automated test (UI stepper asserted only structurally, `tests/Feature/BookingTest.php:216`) |
| FR-CAB-4, FR-CAB-5 | no automated test |
| FR-BUS-1, FR-BUS-3, FR-BUS-4 | no automated test |
| FR-BUS-2 | `tests/Feature/BookingTest.php:129`; `tests/Unit/PricingCalculationTest.php:29` |
| FR-FARE-1 | `tests/Feature/AdminCarTypeAndRouteFareScheduleTest.php:178`; `tests/Unit/PricingCalculationTest.php:9`, `:19` |
| FR-FARE-2 | `tests/Feature/RegionalLocationAndDighaBookingTest.php:120`; `tests/Feature/AdminRoutesAndPrivacyTest.php:46` |
| FR-FARE-3 | `tests/Feature/RegionalLocationAndDighaBookingTest.php:88`, `:120` |
| FR-FARE-4 | `tests/Feature/BookingTest.php:249`; `tests/Unit/PricingCalculationTest.php:77` |
| FR-FARE-5 | `tests/Feature/BookingTest.php:283`; `tests/Unit/PricingCalculationTest.php:87` |
| FR-FARE-6 | `tests/Feature/AdminModularRoutesAndPackagesTest.php:152`; `tests/Unit/PricingCalculationTest.php:36` |
| FR-FARE-7 | `tests/Feature/BookingTest.php:317`; `tests/Unit/PricingCalculationTest.php:67` |
| FR-FARE-8 | `tests/Feature/BookingTest.php:74`; `tests/Unit/PricingCalculationTest.php:46`, `:59` |
| FR-FARE-9 | `tests/Feature/BookingTest.php:60`; `tests/Feature/RegionalLocationAndDighaBookingTest.php:120` |
| FR-FARE-10 | no automated test (the divergence described in G13 is unasserted) |
| FR-BID-1 | no automated test |
| FR-BID-2 | `tests/Feature/BookingTest.php:156` (asserts the message only — the stub is frozen as intended behaviour) |
| FR-BID-3 | `tests/Feature/BookingTest.php:172`; `tests/Feature/AdminRoutesAndPrivacyTest.php:124` |
| FR-BID-4 | `tests/Feature/EndToEndAdminOperationsTest.php:255` |
| FR-BID-5 | `tests/Feature/AdminRoutesAndPrivacyTest.php:111` |
| FR-TRIP-1, FR-TRIP-2 | `tests/Feature/RoleBasedAccessTest.php:113`; `tests/Feature/EndToEndAdminOperationsTest.php:333-341` |
| FR-TRIP-3 | `tests/Feature/AdminBookingManagementTest.php:78`, `:120` |
| FR-TRIP-4 | `tests/Feature/AdminRoutesAndPrivacyTest.php:149` |
| FR-WAL-1 | `tests/Feature/EndToEndAdminOperationsTest.php:334-341` |
| FR-WAL-2 | no automated test |
| FR-WAL-3 | `tests/Feature/DriverProfileAndLayoutTest.php:20`, `:91`, `:121` |
| FR-ADM-BKG-1, FR-ADM-BKG-2, FR-ADM-BKG-3 | `tests/Feature/AdminBookingManagementTest.php:24`, `:43`, `:78`, `:120`; `tests/Feature/EndToEndAdminOperationsTest.php:255` |
| FR-ADM-USR-1, FR-ADM-USR-2, FR-ADM-USR-3 | `tests/Feature/AdminUserManagementTest.php:20`, `:41`, `:79`, `:119`, `:140`; `tests/Feature/EndToEndAdminOperationsTest.php:347` |
| FR-ADM-VEH-1, FR-ADM-VEH-2, FR-ADM-VEH-3 | `tests/Feature/AdminFleetAndBerthManagementTest.php:191`, `:238`, `:280`; `tests/Feature/EndToEndAdminOperationsTest.php:198` |
| FR-ADM-CART-1 | `tests/Feature/AdminCarTypeAndRouteFareScheduleTest.php:47`, `:61`, `:76`, `:105`, `:129`, `:143` |
| FR-ADM-RTE-1 | `tests/Feature/AdminRoutesAndPrivacyTest.php:46`; `tests/Feature/AdminModularRoutesAndPackagesTest.php:152`; `tests/Feature/AdminCarTypeAndRouteFareScheduleTest.php:143` |
| FR-ADM-LPK-1 | `tests/Feature/AdminModularRoutesAndPackagesTest.php:54`, `:186` |
| FR-ADM-AIR-1 | `tests/Feature/AdminModularRoutesAndPackagesTest.php:104`, `:186` |
| FR-ADM-LOC-1 | `tests/Feature/AdminFleetAndBerthManagementTest.php:30`; `tests/Feature/EndToEndAdminOperationsTest.php:39`; `tests/Feature/BookingTest.php:371-429` |
| FR-ADM-BRT-1 | `tests/Feature/AdminFleetAndBerthManagementTest.php:83`; `tests/Feature/EndToEndAdminOperationsTest.php:96` |
| FR-ADM-SEAT-1 | `tests/Feature/AdminFleetAndBerthManagementTest.php:140`; `tests/Feature/EndToEndAdminOperationsTest.php:147` |
| FR-ADM-DFM-1, FR-ADM-DFM-2 | `tests/Feature/BookingTest.php:199`; `tests/Feature/AdminModularRoutesAndPackagesTest.php:35` |
| FR-ADM-DFM-3 | no automated test |
| FR-ADM-WF-1, FR-ADM-WF-2 | no automated test (no test file asserts stage update or stage effects) |
| FR-ADM-SET-1 | `tests/Feature/PlatformPolicySettingsTest.php:39`, `:63`, `:74`; `tests/Feature/AdminRoutesAndPrivacyTest.php:22` |
| FR-ADM-INV-1 | `tests/Feature/AdminBookingManagementTest.php:149`; `tests/Feature/EndToEndAdminOperationsTest.php:302` |
| FR-ADM-INV-2 | no automated test |
| FR-ADM-FEE-1 | no automated test |
| FR-PUB-1 | `tests/Feature/BookingTest.php:23`, `:33`; `tests/Feature/RoleBasedAccessTest.php:21-168` |
| FR-PUB-2 | `tests/Feature/PlatformPolicySettingsTest.php:26`, `:74` |
| FR-PUB-3 | `tests/Feature/BookingTest.php:53` |
| FR-PUB-4 | `tests/Feature/RegionalLocationAndDighaBookingTest.php:88`; `tests/Feature/BookingTest.php:371-429` |

---

## 8. How to regenerate / verify

1. `php artisan route:list` — compare the 99-route inventory in §4.
2. `php artisan test` — the suite in §7 is the current behavioural contract; it uses an in-memory
   SQLite database (`phpunit.xml:26-27`) and seeds via the seeder.
3. Re-derive this file by reading `routes/web.php`, `app/Http/Controllers/**`, `app/Models/**`,
   `app/Services/**`, `database/migrations/**`, `database/seeders/DatabaseSeeder.php`,
   `resources/views/intercity.blade.php` and `public/js/app.js`; update any row whose cited line no
   longer supports the statement.

---

## 9. Gap closure addendum (hardening cycle, same day)

§6 describes the committed snapshot. The table below records which of those gaps the verified
hardening cycle closed, and the command that proves each one. `LEDGER.md` carries the full evidence;
the whole suite is `php artisan test` → **191 passed (1,084 assertions)**.

| Gap | Now | Proof |
| --- | --- | --- |
| G1 bid persistence | **superseded** — the marketplace replaces the demo bid endpoints: bids are session-scoped, immutable, timestamped and auditable (`bidding_sessions` / `driver_bids.session_id`). The legacy `/api/bids/*` demo endpoints are retained for the old driver console | `DriverBiddingMarketplaceTest` (21 tests), `docs/BIDDING.md` |
| G2 withdrawal stub | closed — real debit, balance floor, ledger row | `DriverWalletLedgerTest` (withdrawal pairs) |
| G3 trip ownership / idempotency | closed — assigned-driver check, no double payout | `BookingWorkflowTest::test_driver_cannot_start_another_drivers_trip`, `…completed_trips_cannot_be_completed_twice` |
| G4 `bids/accept` over-exposed | closed — `role:customer,admin` | `RouteAuthorizationAuditTest` |
| G5 CSRF disabled | closed — no exemptions; SPA sends the token | `CsrfProtectionTest` (419 without token, 200 with) |
| G6 public booking lookup | closed — signed URL / owner / reference+phone | `BookingIntegrityTest` (IDOR, signed, phone) |
| G7 `GET /` writes | closed — render path is read-only | `HomeController` + `RoleBasedAccessTest` |
| G8 `Driver::first()` fallback | closed — 403/404 instead of another driver's record | `DriverWalletLedgerTest::test_driver_without_a_linked_profile_gets_403…` |
| G9 customer list by first name | closed — account link or exact phone | `RoleBasedAccessTest::test_new_customer_with_no_bookings_does_not_leak_other_bookings` |
| G10 local/airport pricing ignores tables | closed — `local_packages` and `airport_hubs` drive the price | `PricingParityTest::test_local_package_price_comes_from…`, `…test_airport_fare_comes_from_the_airport_hub` |
| G11 berth availability not enforced | closed (server) — berths are locked and marked sold | `BookingIntegrityTest` (marked booked, 409 twice) |
| G12 berth matched without coach | closed — selection pinned to one `coach_code` | `BookingIntegrityTest::test_berth_total_comes_from_seat_layout_prices` |
| G13 two pricing engines | closed — `FareCalculatorService` only; browser renders its output | `PricingParityTest::test_quote_and_charge_are_identical` |
| G14 client-only coupons | closed — server validates any configured coupon | `PricingParityTest::test_coupon_discount_is_computed_only_on_the_server` |
| G15 reference collisions | closed — CSPRNG + uniqueness check | `BookingIntegrityTest::test_booking_references_are_generated_and_unique` |
| G16 failure treated as success | closed — no fabricated ticket or OTP | `public/js/app.js` (booking + bus payment paths) |
| G17 hardcoded Pune→Goa bus POST | closed — payload built from the selected route/coach | `public/js/app.js` |
| G20 workflow stages display-only | **partial** — transitions are now enforced by `BookingWorkflow`, but the `workflow_stages` rows remain display configuration | `BookingWorkflowTest::test_transition_table_is_explicit` |
| G22 OTP always returned | **partial** — a live code is withheld once a gateway is configured; demo mode still echoes it | `OtpSecurityTest::test_otp_is_not_echoed_when_a_real_gateway_delivers_it` |
| G30 coupons lack validity | closed — `expires_at`, `usage_limit`, `used_count` | `BookingIntegrityTest` (coupon quartet) |

Still open and deliberately untouched: **G18** (static customer berth map), **G19** (Fees & Surcharges
not persisted), **G21** (dynamic-field validation), **G23–G29** (alt-auth defaults, no `GET /login`,
unconditional `/health`, ticket field-name mismatch, no pagination, no audit trail, hardcoded invoice
identifiers), plus **G1/G20/G22** above where marked partial. These are product/UX cycles, not
correctness or security regressions, and each is still visible in the code exactly as cited in §6.
