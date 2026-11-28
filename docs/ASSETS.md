# Asset Pipeline Audit (task P2-2)

**Type:** read-only audit — no asset file was created, modified, or deleted.
**Date:** 2026-11-24
**Scope:** reconcile the two asset pipelines: hand-written `public/css|js` (loaded by Blade via `asset()`) versus the Vite 7 + Tailwind 4 pipeline in `resources/css|js` + `public/build`.
**Contract:** `AUTOMATION_RULES.md:169` (P2-2 "Unused/duplicated assets"), gate G5 `AUTOMATION_RULES.md:110`.

---

## 1. What the running site actually serves

`GET /` is routed to `HomeController@index` (`routes/web.php:30`), which returns `view('intercity')`
(`app/Http/Controllers/HomeController.php:118`). That view extends the shared layout
(`resources/views/intercity.blade.php:1`, 5,824 lines). `legal.blade.php` also extends the same layout
(`resources/views/legal.blade.php:1`; routes at `routes/web.php:243` and `routes/web.php:252`).

Every page therefore loads exactly these assets from `resources/views/layouts/app.blade.php`:

| Tag | Line | Resolves to | Kind |
|---|---|---|---|
| `<link rel="stylesheet" href="{{ asset('css/styles.css') }}">` | `layouts/app.blade.php:22` | `public/css/styles.css` (97,528 B, 4,574 lines) | Live, hand-written |
| `<script src="{{ asset('js/app.js') }}">` | `layouts/app.blade.php:707` | `public/js/app.js` (312,499 B, 7,161 lines) | Live, hand-written |
| `<img src="{{ asset('assets/logo.jpg') }}">` | `layouts/app.blade.php:58`, `:269` | `public/assets/logo.jpg` (71,057 B) | Live, static |
| Inline `<script>` seeding `window.CURRENT_USER` etc. | `layouts/app.blade.php:697-702` | Blade → global bridge consumed by `public/js/app.js` | Live, inline |
| Google Fonts + Material Symbols | `layouts/app.blade.php:11-16` | fonts.googleapis.com | Live, third-party |
| Leaflet CSS / JS | `layouts/app.blade.php:19`, `:705` | unpkg.com | Live, third-party |

Neither served view contains `@vite`, `build/assets`, or `manifest` references. The only `@vite` in the
repository is `resources/views/welcome.blade.php:15`.

### The Vite side, as configured

- `vite.config.js:8` — entries `resources/css/app.css` and `resources/js/app.js`; Tailwind plugin at `:11`.
- `resources/css/app.css:1-6` — `@import 'tailwindcss'` plus `@source` globs over `resources/**/*.blade.php`
  and `resources/**/*.js`; theme font override at `:8-11`. Total 390 B.
- `resources/js/app.js:1` — `import './bootstrap';` (22 B).
- `resources/js/bootstrap.js:1-4` — assigns `axios` to `window.axios` and sets `X-Requested-With` (127 B).
- `package.json:5-8` — `build: vite build`, `dev: vite`; devDependencies at `:9-16`.
- `public/build/manifest.json:2-16` — two built entries: `assets/app-D9h1O49F.css` (43,460 B) and
  `assets/app-Bwn9szyo.js` (59,219 B, contains the bundled axios).

## 2. Is `public/build/` referenced by any view?

**No reachable view references it.**

- `grep -r "@vite\|build/assets\|manifest" resources/` yields only `resources/views/welcome.blade.php:14-15`.
- `welcome.blade.php` is the stock Laravel landing page (Tailwind utility classes at `:22-45`, inline
  Tailwind v4.0.7 fallback at `:18`). **No route or controller returns it**: the root route is
  `HomeController@index` (`routes/web.php:30`), and `grep "view('welcome')" app/` finds zero matches.
  It is therefore dead code, reachable only if someone adds a route for it.
- `/public/build` is git-ignored (`.gitignore:16`), so the built files are local/generated artifacts,
  not source. `git ls-files public/build` returns nothing.

Consequence: the Vite bundle is built (and will be built in CI by gate G5) but **never delivered**.

The build output is also Tailwind-foundation-only for this site: `app-D9h1O49F.css` contains 198 class
selectors including `.flex{`, `.grid{`, `.hidden{`, `.container`, `.z-10` — utilities that come from
`welcome.blade.php`. It contains none of the design-system classes the live site actually uses
(`.btn`, `.form-control`, `.tab-pane` are absent), and a spot-check for standalone `flex`, `grid`,
`hidden`, `items-center`, `justify-between`, `gap-2`, `p-4`, `text-sm` classes in
`intercity.blade.php` returns 0. Likewise `app-Bwn9szyo.js` is axios + the `X-Requested-With` header,
while `public/js/app.js` contains no `axios` reference and uses `fetch(` directly (72 matching lines).
So even if the bundle were wired in, it would add ~100 KB of unreferenced payload.

## 3. Live vs dead vs duplicated

| Artifact | Evidence | Status |
|---|---|---|
| `public/css/styles.css` | `layouts/app.blade.php:22`; hand-written design system (`styles.css:1-25`) | **Live** |
| `public/js/app.js` | `layouts/app.blade.php:707`; no `import`/`export`/`require` (hand-written, 7,161 lines) | **Live** |
| `public/assets/logo.jpg` | `layouts/app.blade.php:58`, `:269` | **Live** |
| `public/build/manifest.json` + `public/build/assets/*` | only `welcome.blade.php:15`; gitignored (`.gitignore:16`) | **Dead** (build output) |
| `resources/views/welcome.blade.php` | no route/controller; 277 lines, stock Laravel page | **Dead** |
| `resources/css/app.css` | build input only (`vite.config.js:8`) | Dead at runtime, required by G5 |
| `resources/js/app.js`, `resources/js/bootstrap.js` | build inputs only (`vite.config.js:8`); axios unused by live page | Dead at runtime, required by G5 |
| `vite.config.js`, `package.json` devDeps | G5 / G0b | Build-only |
| CDN assets (fonts, Material Symbols, Leaflet) | `layouts/app.blade.php:11-19`, `:705` | **Live**, third-party |

Duplication is structural, not file-level: there are two independent "application JS" notions
(7,161-line hand-written `public/js/app.js` vs a 22-byte Vite entry that only imports axios) and two CSS
notions (97 KB hand-written `styles.css` vs Tailwind preflight). They do not overlap in served markup.

## 4. Recommendation

| Path | Disposition | Risk / notes |
|---|---|---|
| `public/css/styles.css` | **Keep** | Live stylesheet; 4,574 lines, no Tailwind directives, referenced only from `layouts/app.blade.php:22`. |
| `public/js/app.js` | **Keep** | Live behaviour; 7,161 lines of hand-written code consuming inline globals from `layouts/app.blade.php:697-702`. |
| `public/assets/logo.jpg` | **Keep** | Referenced twice in the layout. |
| `public/build/**` | **Delete-later** | Zero runtime risk: no view references it, it is git-ignored, and `npm run build` regenerates it. Do not hand-edit. |
| `resources/views/welcome.blade.php` | **Delete-later** | Removes the only `@vite` call and the source of the 198 unused Tailwind utilities (shrinks the built CSS). No runtime change. |
| `resources/css/app.css`, `resources/js/app.js`, `resources/js/bootstrap.js` | **Keep** | Required as G5 build inputs; deleting them breaks `vite.config.js:8` and the P2-3 CI gate. |
| Vite pipeline as a whole | **Do not stop-building yet** | `npm run build` is a mandated gate (`AUTOMATION_RULES.md:110`) and is wired into CI under P2-3. "Stop-building" only becomes safe after G5 is amended, which is a contract change, not an asset cleanup. |
| `?v={{ time() }}` on both live tags | **Stop (pattern)** | `layouts/app.blade.php:22` and `:707` bust caches on every request, forcing revalidation of ~410 KB of static files; recommend a build/version token instead. No file change implied by this audit. |

Nothing above authorises deleting a file in this change; the table is a follow-up backlog for P2-2.

## 5. Why migrating the hand-written assets is a separate, risky project

Moving `public/js/app.js` (7,161 lines, 312,499 B) or `public/css/styles.css` (97,528 B, 4,574 lines)
into the Vite/Tailwind pipeline is not a cleanup — it is a rewrite with behaviour risk:

- `public/js/app.js` has no module boundaries and depends on Blade-injected globals
  (`layouts/app.blade.php:697-702`), so an ES-module migration changes load order and global scope.
- All ~120 routes drive that single file through DOM ids and inline `onclick=` handlers in
  `intercity.blade.php` (5,824 lines), so a bundler error becomes a site-wide outage.
- `styles.css` is a bespoke design system (`styles.css:1-25` defines the palette); Tailwind would have
  to reproduce it or coexist, and there is no visual regression suite.
- `AUTOMATION_RULES.md` R4 requires a failing test before any behaviour change, and R7 forbids mixing
  a schema/controller change with a view redesign — an asset migration deserves its own task and gate.

**Verdict:** the site is served entirely by the hand-written `public/*` pipeline; the Vite/Tailwind
pipeline is built but unused. Reconcile by deleting the dead `welcome` view and leaving `public/build`
as an ignored build artifact — do not migrate the 7,161-line JS or 97 KB CSS as part of P2-2.

---

### Reproduce (read-only)

```bash
grep -rn "@vite\|build/assets\|manifest" resources/          # only welcome.blade.php:14-15
grep -rn "view('welcome')" app/ routes/                      # no matches
grep -n "asset('css/styles.css')\|asset('js/app.js')" resources/views/layouts/app.blade.php
cat public/build/manifest.json
git ls-files public/build                                    # empty (gitignored, .gitignore:16)
wc -c -l public/js/app.js public/css/styles.css
```
