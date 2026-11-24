# IntercityWheels — Coordinator Automation Rule Set

**Version:** 1.0 · **Created:** 2026-11-24 · **Owner:** Project Coordinator (automated agent)
**Supersedes:** ad-hoc autonomy in `.antigravity/rules/generalrules.md` (kept, but this file governs sequencing and gates)

This is an **executable operating contract**. An agent (or human) reading this should be able to pick the next task, do it, prove it works, and advance the project without asking for permission between steps — while never claiming completion it has not verified.

---

## 0. Verified baseline (do not re-litigate)

Established by direct file inspection on 2026-11-24. Treat as facts until re-proven.

| Fact | Evidence |
|---|---|
| Laravel 12 / PHP 8.2, Vite 7 + Tailwind 4, SQLite | `composer.json`, `package.json` |
| 109 test methods across 14 files | `tests/**` |
| Entire UI is one **5,824-line** Blade file | `resources/views/intercity.blade.php` |
| No `routes/api.php`; all ~120 endpoints live in `routes/web.php` (206 lines) | `routes/web.php` |
| **Zero authorization middleware on any route** | `routes/web.php`, `bootstrap/app.php` |
| CSRF disabled for `api/*`, `login*`, `logout` | `bootstrap/app.php:14-19` |
| `User::isAdmin()/isDriver()/isCustomer()` defined but called from **nowhere** in routes | `app/Models/User.php:56-69` |
| Every API endpoint publicly reachable | inference from above |
| `INTERCITYWHEELS_PRD.md` is a **20-line stub**, not a PRD | `INTERCITYWHEELS_PRD.md` |
| `PROGRESS.md` marks 100% complete | `PROGRESS.md` |

**Consequence:** `PROGRESS.md` is not a trustworthy status source. Rule **R1** exists because of this.

---

## 1. Operating contract

### R1 — Evidence beats assertion
No task is "done" because code was written, a comment says so, or `PROGRESS.md` was ticked. It is done only when a **command in §4 passes and its output is quoted** in the tracker. `PROGRESS.md` is *output*, never *input*.

### R2 — Never trust a pre-marked checkbox
`PROGRESS.md` currently asserts 5 phases complete. Several are false (§0). Before continuing any phase, re-verify its claims against code. If a claim is false, **uncheck it and record the gap**.

### R3 — The real PRD must be recovered first
`.antigravity/rules/generalrules.md:3` designates `INTERCITYWHEELS_PRD.md` as source of truth, but that file is a stub. Requirements cannot be validated against nothing.
**Blocker task P0-1:** either the human supplies the full PRD, or we freeze behavior as-implemented and generate `PRD_DERIVED.md` from routes + migrations + tests. Do not invent requirements silently.

### R4 — Test-first for every defect
For each bug: write a **failing** test that reproduces it, then fix, then prove it passes. A fix without a red→green test is rejected at the gate.

### R5 — Authorization is asserted, not assumed
Any test touching an admin/driver endpoint must include **at least one negative case** proving a wrong-role or guest actor is rejected. `actingAs()` alone is insufficient — it masks missing middleware (this is exactly how the current suite reports 0 failures while the API is open).

### R6 — Money, seats, and OTP are transactional
Any write that touches `total_amount`, `wallet_balance`, berth allocation, bid acceptance, or OTP verification must run inside `DB::transaction()` with row locking (`lockForUpdate()`). Non-cryptographic randomness (`mt_rand`) is banned for OTP and reference generation.

### R7 — One concern per change
No commit mixes a schema change, a controller change, and a view redesign. Keeps gates attributable and rollbacks cheap.

### R8 — Bounded autonomy
Continue through tasks without asking. **Stop and escalate only** when: a gate fails twice on the same task, a requirement is ambiguous (R3), destructive/irreversible action is implied, or secrets are needed. Otherwise proceed.

### R9 — Never widen scope to hit "done"
If a phase cannot close honestly, record the residual gap and move to the next phase. Do not re-label incomplete work complete.

### R10 — Track everything in one ledger
Use the tracker format in §6. One row per task. No prose status.

---

## 2. Roles

The coordinator runs these as sequential hats (or as parallel subagents where independent).

| Role | Owns | Gate |
|---|---|---|
| **Coordinator** | Ledger, sequencing, escalation, §2 dispatch | G0, G6 |
| **Security Auditor** | R5 compliance, authz matrix, secrecy | G1, G2 |
| **Backend Engineer** | Controllers, services, migrations, transactions | G3 |
| **Test Engineer** | Test suite, negative cases, factories | G4 |
| **QA / Release** | Build, full suite, manual smoke, docs | G5, G6 |

> **Skill mapping note:** the loaded skill catalog (`office`, `pinokio`, `gepeto`, `dsh-doc`, `reverse-engineer-anything`, `diagnose-windows-sandbox-acl`) contains **no Laravel/web-app skill**. `reverse-engineer-anything` applies to compiled/binary targets and Electron bundles, not a source repository — do not reach for it here. Correct approach: use the roles above, and load `diagnose-windows-sandbox-acl` only if shell execution is blocked (see P0-0).

---

## 3. State machine

```
G0 Preflight ─► P0 Triage ─► G1 Authz Design ─► G2 Authz Build ─► G3 Logic Fix
      ▲                                                              │
      └──────────────── rollback on gate failure ◄───────────────────┘
                                                                     ▼
                                              G4 Test Proof ─► G5 Build ─► G6 Release
```

A task may not advance past a gate whose command fails (§4). On failure: fix in place; on **second** consecutive failure, halt and escalate per R8.

---

## 4. Gates — exact commands

Run from repo root. All must exit `0`.

| Gate | Purpose | Command | Pass criterion |
|---|---|---|---|
| **G0** | Preflight | `php -v` · `composer --version` · `node -v` · `npm -v` | all present |
| **G0b** | Deps installed | `composer install --no-interaction` then `npm ci` | exit 0 |
| **G0c** | Boot | `php artisan about` (or `php artisan route:list`) | exit 0, app boots |
| **G1** | Authz matrix reviewed | `php artisan route:list --json > route-audit.json` | every `admin`/`driver` route has middleware per §5 |
| **G2** | Authz enforced | `php artisan test --filter=Authorization` | all pass, incl. negative cases |
| **G3** | Logic | `php artisan test --filter=Pricing` / `--filter=Booking` / `--filter=Bid` | all pass |
| **G4** | Full suite | `composer test` | 0 failures, 0 errors |
| **G4b** | Static quality | `vendor/bin/pint --test` | exit 0 |
| **G5** | Build | `npm run build` | exit 0, manifest emitted |
| **G6** | Release | `php artisan config:clear && php artisan migrate --pretend` | migration dry-run clean |

`composer test` already clears config then runs `php artisan test` (`composer.json:47-50`).

> **Known blocker (P0-0):** in the current harness session, *shell spawning* is denied by Windows file-permission provisioning (`grantWrite` failed; ACL not canonical). Read-only file tools work. Until resolved, gates cannot be executed — **the project must not be declared verified.** Resolution is human-owned (sandbox ACL repair or session full access).

---

## 5. Required authorization matrix (the missing middleware)

Target end-state. Implement in `bootstrap/app.php` (aliases) + route groups, not per-controller ifs.

| Prefix | Guest | `customer` | `driver` | `admin` |
|---|---|---|---|---|
| `/`, `/api/routes/*`, `/api/locations*`, `/api/car-types`, `/api/local-packages`, `/api/airport-hubs` | ✅ | ✅ | ✅ | ✅ |
| `/api/bookings` (create) | ✅ (guest booking allowed) | ✅ | ✅ | ✅ |
| `/api/bookings/{ref}` | ⚠️ **must be owner-scoped or signed** | own | own | any |
| `/api/bids*`, `/api/driver/**` | ❌ 401 | ❌ 403 | ✅ | ✅ |
| `/api/admin/**` | ❌ 401 | ❌ 403 | ❌ 403 | ✅ |
| `/api/auth/user` | ❌ 401 | ✅ | ✅ | ✅ |

Acceptance: `php artisan route:list --json` shows a `role:`/`auth:` middleware on **every** row in the ❌ cells, and G2 proves 401/403 with tests.

---

## 6. Priority backlog (derived from verified defects)

Work top-down. Each row is one task, one commit, one gate.

### P0 — Blocking correctness & security

| ID | Defect | Evidence | Fix | Prove with |
|---|---|---|---|---|
| **P0-0** | Shell execution blocked by sandbox ACL | `grantWrite` fail, ACL not canonical | human: repair ACL or full access | G0 |
| **P0-1** | Source-of-truth PRD is a stub | `INTERCITYWHEELS_PRD.md` (20 lines) | obtain PRD **or** generate `PRD_DERIVED.md` from routes/migrations/tests | file exists + reviewed |
| **P0-2** | No authz on any route | `routes/web.php` (all), `bootstrap/app.php` | middleware aliases `role:admin`, `role:driver`, `auth`; wrap route groups per §5 | `AuthorizationTest` negative cases (R5) |
| **P0-3** | CSRF disabled for `api/*` | `bootstrap/app.php:14-19` | restrict exclusion to non-browser endpoints; keep session APIs protected | CSRF test |
| **P0-4** | Coupons ignore expiry & usage limit | `BookingController.php:144` | filter `expires_at`/`usage_limit`/`used_count`; increment inside txn | coupon expiry/limit tests (red→green) |
| **P0-5** | Booking skips state machine | `BookingController.php:177` (`status => 'Confirmed'`) | create as `Requested`, then transition legally | state-transition test rejects illegal jumps |
| **P0-6** | Berth double-booking race | `BookingController.php:131-133` (no lock/txn) | `DB::transaction` + `lockForUpdate` on berth rows | concurrent-booking test |
| **P0-7** | Weak OTP + colliding reference | `BookingController.php:156-157` (`mt_rand`) | `random_int`/`Str::random`, unique index + retry on `booking_reference` | uniqueness + entropy test |
| **P0-8** | Public booking lookup leaks PII (IDOR) | `BookingController.php:189-196` | require auth + owner match, or signed URL | IDOR test returns 403 for foreign ref |

### P1 — Data integrity & audit items

| ID | Defect | Evidence | Fix |
|---|---|---|---|
| **P1-1** | Hardcoded distance fallback | `BookingController.php:78-79` (`345`) | explicit failure or cached geocode; never invent distance |
| **P1-2** | Fare logic duplicated in view/JS | `intercity.blade.php` (5.8k lines) | single server-side pricing service; UI displays only |
| **P1-3** | Wallet/bid writes unaudited | `DriverBiddingController.php` | txn + balance floor + ledger rows |
| **P1-4** | No DB indexes on hot lookups | migrations | add indexes: `bookings.booking_reference`, `coupons.code`, `seat_layouts.berth_number` |
| **P1-5** | OTP lacks expiry/rate-limit enforcement | `AuthController.php` | server-side TTL + attempt cap |

### P2 — Maintainability & release

| ID | Item | Fix |
|---|---|---|
| **P2-1** | 5,824-line single Blade view | split into `layouts/`, `partials/`, feature sections; keep behavior identical |
| **P2-2** | Unused/duplicated assets | reconcile `public/css|js` vs `resources/css|js` |
| **P2-3** | No CI | add workflow running G1→G5 |
| **P2-4** | Tracker drift | regenerate `PROGRESS.md` from ledger (§7) |

---

## 7. Tracker format (single source of truth)

Append one row per task attempt to `LEDGER.md`:

```
| ID | Date | Task | Gate | Result | Evidence (cmd + excerpt) | Next |
|----|------|------|------|--------|--------------------------|------|
| P0-2 | 2026-11-24 | Admin authz middleware | G2 | FAIL | `php artisan test --filter=Authorization` → 3 failed | retry |
```

Rules: `Result ∈ {PASS, FAIL, BLOCKED}`. `FAIL` twice on the same ID ⇒ `BLOCKED` + escalate (R8). Never write `PASS` without a quoted command result.

---

## 8. Continuation protocol (how to "continue the next step")

Executed at the start of every work cycle:

1. **Read** `LEDGER.md`; take the first row that is not `PASS`.
2. If none, take the **first `P0` row that is not `PASS`**; else first `P1`; else `P2`.
3. If all `PASS` for P0–P1, run **§4 gates G4→G6** end-to-end.
4. If the chosen task is `BLOCKED`, skip to the next unblocked row and log the skip.
5. Execute: write failing test (R4) → implement → run the task's gate → append ledger row.
6. `PASS` ⇒ loop to step 1. `FAIL` ⇒ fix; second `FAIL` ⇒ `BLOCKED`, escalate, continue elsewhere.

**Definition of Done (whole project):** P0 and P1 rows all `PASS`; G4, G4b, G5, G6 green; `route-audit.json` shows zero unprotected privileged routes; `PRD_DERIVED.md` (or real PRD) reviewed; `LEDGER.md` has no `BLOCKED` rows without an escalation note.

---

## 9. Escalation triggers (R8)

Stop and report when:
- the same gate fails twice on one ID;
- a requirement must be guessed because the PRD is missing (P0-1);
- a fix requires credentials, payments, or destructive migration;
- the harness cannot execute commands (P0-0) — report exactly which gate is unrunnable.

Escalation format: `ID · gate · command · output · hypothesis · proposed options (≤3)`. No essays.

---

## 10. Immediate next actions

1. **Human:** clear P0-0 so gates can run (see §4 note).
2. **Coordinator:** create `LEDGER.md`, seed it with every P0/P1 row as `BLOCKED` pending P0-0.
3. **Next engineering task after unblock:** **P0-2** (authz middleware) + its `AuthorizationTest` negative cases — highest risk, smallest blast radius, unblocks P0-3/P0-8.
4. **Human decision required:** P0-1 — supply the real PRD, or authorize deriving it from the code.
