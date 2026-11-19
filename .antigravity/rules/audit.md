@IntercityWheels_Master_Prompt.md (or reference your PRD filename here)

Act as a Principal Full-Stack Architect and QA Security Auditor. Your task is to perform a rigorous, evidence-based Production Readiness Audit of the current **IntercityWheels.com** codebase against the attached Master Development Prompt (`.md`).

### Audit Rules & Constraints
1. **Zero Assumptions:** Do not trust comments, filenames, or UI labels. Inspect the actual database schemas, API routes, middleware, frontend state handlers, and environment configurations.
2. **Stub & Mock Detection:** Explicitly flag any hardcoded dummy data, `TODO` placeholders, bypassed validations, unverified webhooks, or static UI screens that lack working backend persistence.
3. **File-Level Evidence:** For every finding, cite the exact file path and line numbers (e.g., `src/api/bookings/route.ts:42`).

---

### Audit Checklist (Inspect & Verify All 8 Modules)

1. **Database Architecture & Migrations**
   - Verify that all entities required by the PRD (users, drivers, vehicles, dynamic forms, workflows, location fares, seat layouts, wallets, bids, OTP logs) exist in the schema/migrations with proper foreign keys, indexes, and constraints.
   - Check for race-condition vulnerabilities (e.g., missing database transactions/locks on seat reservations, wallet deductions, or driver bid acceptance).

2. **Authentication, RBAC & OTP Security**
   - Audit role-based access control across **Customer**, **Driver**, and **Admin** routes/endpoints. Ensure a Customer or unauthenticated user cannot access Admin configuration or Driver payout endpoints.
   - Verify that OTP generation, expiration, rate-limiting, and ride-start/ride-end OTP verification are enforced server-side (not just validated on the client).

3. **Dynamic Form Builder & Conditional Fields**
   - Check if admin-configured dynamic forms and conditional field rules actually persist to the database, dynamically render on the customer booking screens, and enforce server-side payload validation upon submission.

4. **Booking Workflows, Stages & Approval Rules**
   - Trace the state machine for bookings (pending, bidding, approved, driver assigned, active, completed, cancelled). Verify that invalid state transitions are blocked on the backend and that admin approval rules trigger properly.

5. **Pricing Engine, Bidding, Wallets & Payments**
   - Audit the fare calculation logic: location-wise base fares, tolls, pet charges, night-stay charges, and coupon validation (expiry, usage limits, discount caps).
   - Check driver bidding logic, commission/wallet balance checks, and payment gateway webhook signature verification (preventing spoofed payment confirmations).

6. **OpenStreetMap & Route Distance Calculation**
   - Verify whether distance and route calculations use a real OSRM/Nominatim/routing service with proper error handling, caching, and fallback logic—or if straight-line/hardcoded distances are being used.

7. **Seat Layouts, Mapping & Reserved-Seat Policies**
   - Inspect uploaded seat layout parsing/storage, visual seat mapping synchronization with backend inventory, and enforcement of reserved-seat rules (e.g., gender-specific or admin-blocked seats) under concurrent booking attempts.

8. **Deployment, Environment & Error Handling**
   - Check `.env.example` vs. actual `process.env` usage for missing secrets, exposed API keys in frontend bundles, unhandled promise rejections, CORS configuration, and build/type-check errors (`npm run build` / `tsc`).

---

### Required Output Format
Produce a structured **Production Readiness Report** with the following sections:
1. **Executive Verdict & Readiness Score (0–100%):** State clearly whether the app is `READY FOR PRODUCTION`, `STAGING ONLY`, or `NOT READY (CRITICAL GAPS)`.
2. **Module-by-Module Status Matrix:** A Markdown table showing each of the 8 modules, Completion %, Status (`Working`, `Partial/Mocked`, `Missing`), and Key Gaps.
3. **Critical Blockers (Must Fix Before Launch):** Security flaws, payment/wallet vulnerabilities, broken state transitions, or stubbed core logic with exact file paths.
4. **Actionable Fix Plan:** Prioritized, step-by-step remediation tasks grouped into Phase 1 (Security & Data Integrity), Phase 2 (Core Business Logic), and Phase 3 (Deployment Hardening). Do NOT modify any code until I review this audit and approve the fix plan.