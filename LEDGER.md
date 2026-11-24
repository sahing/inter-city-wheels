# Execution Ledger

Machine-readable truth for `AUTOMATION_RULES.md`. One row per task. **No row may be `PASS` without a quoted command result.**

Status legend: `TODO` · `WIP` · `PASS` · `FAIL` · `BLOCKED` · `DEFERRED`

> **Execution note:** the harness cannot provision a sandbox grant for this workspace (ACL not canonical), so every command must run via a one-shot `danger-full-access` approval. File reads/writes work normally. This costs one approval per command batch — batch aggressively.

---

## P0 — Blocking correctness & security

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| P0-0 | Restore command execution | G0 | BLOCKED | `VERDICT=PRECONDITION`; ACL not canonical | works around via per-command approval; human fix still preferred |
| P0-1 | Recover real PRD or derive `PRD_DERIVED.md` | — | BLOCKED | `INTERCITYWHEELS_PRD.md` = 20-line stub | **human decision required** |
| P0-2 | Authorization middleware | G2 | **PASS** | `route:list --json` → zero privileged routes w/o middleware; `php artisan test` → **118 passed (798 assertions)**; `AuthorizationTest` 9 tests incl. 401/403 negatives | `EnsureUserRole`, alias `role`, routes grouped |
| P0-3 | Restore CSRF on session endpoints | G4 | DEFERRED | `bootstrap/app.php` still excludes `api/*` | **must pair with frontend token plumbing** — re-enabling now breaks SPA `fetch` calls; needs `X-CSRF-TOKEN` header work first |
| P0-4 | Coupon expiry + usage limits | G3 | WIP | `min_spend` now enforced; **`expires_at`/`usage_limit`/`used_count` columns do not exist** | needs migration, not just logic |
| P0-5 | Route bookings through state machine | G3 | DEFERRED | `BookingController.php` still creates `status => 'Confirmed'` | `BookingTest:97` asserts `'Confirmed'`; requires coordinated change + test updates |
| P0-6 | Lock berth allocation in a transaction | G4 | TODO | `BookingController.php` berth lookup, no txn/lock | concurrency test needed |
| P0-7 | CSPRNG OTP + unique reference | G3 | **PASS** | `random_int()` for both; `booking_reference` already `->unique()` in migration | DB uniqueness was already present — earlier severity was overstated |
| P0-8 | Bind booking lookup to owner | G2 | TODO | `BookingController::show()` public | guest booking UX depends on it; needs signed URL design |

## P1 — Data integrity

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| P1-1 | Remove hardcoded 345 km fallback | G3 | TODO | `BookingController.php` distance fallback | fail loudly instead |
| P1-2 | Single server-side pricing service | G4 | TODO | pricing duplicated in 5,824-line view | **expensive; defer past P0** |
| P1-3 | Transactional bid/wallet writes + ledger | G4 | TODO | `DriverBiddingController` | balance floor |
| P1-4 | Add indexes | G6 | TODO | migrations | `booking_reference`/`coupon.code` already unique-indexed |
| P1-5 | Server-side OTP TTL + attempt cap | G2 | TODO | `AuthController` | rate limit |

## P2 — Maintainability & release

| ID | Task | Gate | Status | Evidence | Notes |
|----|------|------|--------|----------|-------|
| P2-1 | Split `intercity.blade.php` (5,824 lines) | G5 | DEFERRED | behaviour-preserving refactor | **highest cost, zero functional gain — do last or never** |
| P2-2 | Reconcile duplicated assets | G5 | TODO | `public/{css,js}` vs `resources/{css,js}` | |
| P2-3 | CI pipeline for G1→G5 | G6 | TODO | none present | only worth it once P0-0 is fixed |
| P2-4 | Regenerate `PROGRESS.md` from ledger | — | TODO | `PROGRESS.md` overstates completion | |

---

## Cycle log

| Cycle | Date | Task | Gate | Result | Evidence | Next |
|-------|------|------|------|--------|----------|------|
| 1 | 2026-11-24 | Baseline inspection | — | PASS | 14 test files / 109 tests; 5,824-line view; 0 authz | seed ledger |
| 1 | 2026-11-24 | P0-0 attempt (ACL repair) | G0 | BLOCKED | `VERDICT=PRECONDITION`, `FIXED=0` | escalate |
| 2 | 2026-11-24 | P0-2 authz + P0-7 CSPRNG + coupon `min_spend` | G2/G4/G5 | **PASS** | `route:list` → 0 unprotected; `php artisan test` → 118 passed / 798 assertions / 0 fail; `npm run build` → 61 modules, 346ms | P0-6 next |

## Escalations

- **E1 · P0-0** — Command execution requires per-command approval. Options: (a) repair workspace ACL, (b) run session in full access.
- **E2 · P0-1** — Requirements source missing. Options: (a) human supplies PRD, (b) authorize `PRD_DERIVED.md`.

## Deliberate deferrals (with reason)

- **P0-3 CSRF** — re-enabling without frontend `X-CSRF-TOKEN` plumbing silently breaks every SPA write. Not a safe standalone change.
- **P0-5 state machine** — existing tests assert `'Confirmed'` on creation; changing the initial status is a business decision plus a test rewrite.
- **P2-1 view split** — 5,824 lines, no functional payoff, highest token/time cost in the backlog.
