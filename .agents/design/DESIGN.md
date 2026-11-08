---
name: Intercity Mobility System
colors:
  surface: '#faf8ff'
  surface-dim: '#d2d9f4'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f3ff'
  surface-container: '#eaedff'
  surface-container-high: '#e2e7ff'
  surface-container-highest: '#dae2fd'
  on-surface: '#131b2e'
  on-surface-variant: '#434655'
  inverse-surface: '#283044'
  inverse-on-surface: '#eef0ff'
  outline: '#737686'
  outline-variant: '#c3c6d7'
  surface-tint: '#0053db'
  primary: '#004ac6'
  on-primary: '#ffffff'
  primary-container: '#2563eb'
  on-primary-container: '#eeefff'
  inverse-primary: '#b4c5ff'
  secondary: '#a73a00'
  on-secondary: '#ffffff'
  secondary-container: '#fd651e'
  on-secondary-container: '#571a00'
  tertiary: '#6a1edb'
  on-tertiary: '#ffffff'
  tertiary-container: '#8343f4'
  on-tertiary-container: '#f7edff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dbe1ff'
  primary-fixed-dim: '#b4c5ff'
  on-primary-fixed: '#00174b'
  on-primary-fixed-variant: '#003ea8'
  secondary-fixed: '#ffdbce'
  secondary-fixed-dim: '#ffb599'
  on-secondary-fixed: '#370e00'
  on-secondary-fixed-variant: '#7f2b00'
  tertiary-fixed: '#eaddff'
  tertiary-fixed-dim: '#d2bbff'
  on-tertiary-fixed: '#25005a'
  on-tertiary-fixed-variant: '#5a00c6'
  background: '#faf8ff'
  on-background: '#131b2e'
  surface-variant: '#dae2fd'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
  headline-xl-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 30px
    fontWeight: '700'
    lineHeight: 38px
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 22px
    fontWeight: '600'
    lineHeight: 28px
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-md: 1.5rem
  margin: 1rem
  margin-md: 2rem
  margin-lg: 3rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-xxl: 3rem
---

## Brand & Style

This design system delivers high-velocity, high-trust travel logistics across intercity cabs, scheduled bus reservations, and driver bidding flows. The visual character merges the clean utilitarian structure of airport flight boards with the frictionless, approachable clarity of top-tier consumer transit apps.

### Core Tenets
- **Clarity Under Pressure:** Travelers book on the move. Layouts eliminate visual noise to highlight live fare updates, departure windows, seat layouts, and driver credentials immediately.
- **Reliable Utility:** Visual cues prioritize transaction security, transparent platform pricing, and predictable booking states over ornamental graphics.
- **Ergonomic Speed:** Tappable targets adhere strictly to thumb-zone ergonomics for fast booking, single-hand seat selection, and rapid bidding counter-offers.

The design movement blends **Corporate Modern** with **Tactile Utility**: crisp slate structural backdrops, clean blue wayfinding elements, and subtle dimensional container cards that make complex multi-leg travel plans effortless to digest.

## Colors

The palette balances authoritative transit blue with urgent, high-visibility action colors to drive seamless decision-making across search, seat selection, and auction-style bidding.

### Role Distribution
- **Primary (`#2563EB`):** Platform backbone. Directs route paths, primary navigation headers, confirmed route timelines, interactive tabs, and selection indicators.
- **Secondary (`#EA580C`):** High-energy trigger accent. Reserved strictly for conversion engines: instant booking calls to action, active driver bidding alerts, countdown timers, and discount callouts.
- **Tertiary (`#7C3AED`):** Specialized travel indicator. Anchors demographic and comfort-specific transit states, including women-only seating quotas and sleeper-class designations.
- **Neutral (`#0F172A`):** Deep slate foundation for typographic authority, high-contrast borders, and deep text hierarchy.
- **Background Slate (`#F8FAFC`):** Soft, low-strain off-white canvas that keeps visual fatigue low during long search sessions while offering distinct separation from pure white elevated cards.

### Functional Status Tokens
- **Confirmed / Safe (`#16A34A`):** Ride confirmed, driver verified, ticket generated.
- **Bidding / Pending (`#F59E0B`):** Driver counter-offers pending, seat held on temporary reservation timer.
- **Alert / Cancelled (`#DC2626`):** Route delayed, bidding expired, seat unavailable.

## Typography

The typographic hierarchy pairs the friendly, structured geometry of **Plus Jakarta Sans** for prominent destinations, city pairs, and pricing displays with the neutral, hyper-legible utility of **Inter** for dense transactional information (timelines, vehicle specs, policies, and terminal details).

### Type Hierarchy Usage
- **Headlines (`headline-xl`, `headline-lg`):** Reserved for search portals, destination hero titles, and booking confirmation screens.
- **Card Headers (`headline-md`, `headline-sm`):** Bus operator names, outstation cab classifications (Sedan, SUV, Luxury), and driver bid values.
- **Body (`body-lg`, `body-md`):** Cancellation rules, route itineraries, and fare breakdowns.
- **Labels & Micro-Data (`label-lg`, `label-md`, `label-sm`):** Station codes, license plates, departure/arrival badges, and status pills. All labels use explicit uppercase styling when referencing transit codes (e.g., `DEL`, `BOM`, `PLATFORM 4`).

## Layout & Spacing

Layouts follow a structured responsive system calibrated for fast mobile booking while gracefully scaling to multi-pane split booking on desktop workstations.

### Breakpoints & Grid Composition
- **Mobile (< 768px):** Single column, 4-column base structure. Canvas margins fixed at `margin` (`1rem`). Sticky bottom bar anchors the total fare and dynamic booking CTA.
- **Tablet (768px - 1023px):** 8-column layout with `gutter-md` (`1.5rem`). Allows side-by-side search refinement and card list browsing.
- **Desktop (1024px+):** 12-column layout capped at 1280px max-width with `margin-lg` (`3rem`). Splits into a persistent 4-column route filter/summary sidebar alongside an 8-column travel results stream.

### Spacing Rhythm
- Compact internal gaps (`space-xs`, `space-sm`) group paired route data like departure time and terminal location.
- Standard component padding (`space-md`, `space-lg`) bounds itinerary cards and driver proposal panels.
- Generous segment transitions (`space-xl`, `space-xxl`) partition trip legs, passenger lists, and payment gateways.

## Elevation & Depth

Visual depth distinguishes interactive route selections from fixed reference data without cluttering mobile screens.

### Elevation Layers
- **Surface 0 (Canvas Base):** Hex `#F8FAFC`. Base view background across all screens.
- **Surface 1 (Resting Cards):** Hex `#FFFFFF` with a subtle outline (`1px solid #E2E8F0`) and diffused drop shadow: `0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.05)`. Used for bus listing cards, cab options, and pickup selectors.
- **Surface 2 (Active / Hover / Tap Focus):** Hex `#FFFFFF` with a vibrant focus edge (`1px solid #BFDBFE`) and elevated ambient shadow: `0 10px 15px -3px rgba(37, 99, 235, 0.08), 0 4px 6px -4px rgba(37, 99, 235, 0.04)`. Applied to active bidding cards and selected seats.
- **Surface 3 (Overlays & Bottom Sheets):** Hex `#FFFFFF` with deep suspension shadow: `0 20px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08)`. Applied to passenger detail sheets, seat decks, and driver bid counters.

## Shapes

The design system implements a consistent **Rounded (Level 2)** shape identity to communicate approachable comfort and contemporary transit software standards.

### Geometric Rules
- **Base Components (`rounded`, 0.5rem / 8px):** Primary form fields, dropdowns, seat grid units, and inline route tags.
- **Structural Cards (`rounded-lg`, 1rem / 16px):** Cab listing cards, driver bid panels, modal frames, and boarding step blocks.
- **Floating Modals & Sheets (`rounded-xl`, 1.5rem / 24px):** Bottom sheets for mobile seat selectors and journey confirmation envelopes.
- **Status Badges & Pill Actionables (`rounded-full`, 9999px):** Live indicator badges, passenger quotas, rating counters, and circular vehicle icon backplates.

## Components

### Buttons
- **Primary CTA:** Filled background `#EA580C`, white text, `label-lg`, `rounded` (0.5rem). Height 48px on mobile for optimal thumb reach. Hover shifts to `#C2410C`.
- **Secondary CTA:** Filled background `#2563EB`, white text, `label-lg`, `rounded` (0.5rem). Used for route searches, bid placement, and seat reservation triggers.
- **Outline / Filter:** Transparent background, `1px solid #CBD5E1`, text `#0F172A`, `label-md`. Active selection toggles background to `#EFF6FF` with `#2563EB` border and text.

### Badges & Status Pills
- **Confirmed State:** Background `#DCFCE7`, text `#15803D`, border `#BBF7D0`.
- **Driver Bidding / In Negotiation:** Background `#FEF3C7`, text `#B45309`, border `#FDE68A`.
- **Ladies Quota / Sleeper Class:** Background `#F3E8FF`, text `#6B21A8`, border `#E9D5FF`.
- Badges use `rounded-full` with 4px vertical and 10px horizontal padding, set in `label-sm`.

### Form Fields & Inputs
- **Search & Location Fields:** Height 52px, background `#FFFFFF`, border `1px solid #CBD5E1`, `rounded` (0.5rem). Focused state introduces a `#2563EB` ring with 3px opacity halo (`rgba(37, 99, 235, 0.15)`).
- **Date & Passenger Pickers:** Include leading transit icons (calendar, user seat) in neutral slate (`#64748B`), with high-contrast text (`#0F172A`).

### Cards
- **Trip Card:** Background `#FFFFFF`, border `1px solid #E2E8F0`, `rounded-lg` (1rem), internal padding `space-md` (1rem). Features a clean three-column timeline: Departure Time & City on left, Duration & Stop line in center, Arrival Time & Station on right. Price display is anchored in bold (`headline-sm`) in the bottom right corner directly above the primary booking button.
- **Driver Bidding Card:** Features driver photo (44px circle), star rating badge, vehicle type with plate snippet, live counter-bid amount in high-contrast orange (`#EA580C`), and side-by-side "Accept" and "Counter" action buttons.

### Specialized Component: Interactive Seat Deck
- Grid of seat modules representing bus upper/lower tiers or outstation cab seats.
- **Available Seat:** Background `#FFFFFF`, border `1.5px solid #94A3B8`, `rounded` (0.375rem).
- **Booked / Unavailable:** Background `#E2E8F0`, border `1px solid #CBD5E1`, diagonal hash overlay.
- **Selected Seat:** Background `#2563EB`, border `1.5px solid #1D4ED8`, text white.
- **Reserved for Women:** Background `#FAF5FF`, border `1.5px solid #A855F7`.