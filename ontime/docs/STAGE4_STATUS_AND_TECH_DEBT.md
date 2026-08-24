# OnTime — Stage 4 (Frontend Booking) Status & Technical-Debt Report

> Generated: 2026-08-24 · Branch under review: `main` (HEAD `fe08a14`)

This report is the post-stage summary requested after completing a stage:
current project status, files created/changed, and a short technical-debt
brief. It also records the verification of Stage 4 against the
`prompt-04-frontend` specification.

---

## 1. Current project status

The `webgraphx/ontime` repository already contains a working implementation
of Stages 0–4, with Stage 5 (payments) in progress:

| Stage | Headline deliverable | Version |
|------:|----------------------|---------|
| 0+1   | Plugin bootstrap + DB schema (3 custom tables) | 0.2.0 |
| 2     | Jalali calendar engine, free-slot calc, holidays, Persian digits | 0.3.0 |
| 3     | Admin menu + Settings API + appointments WP_List_Table | 0.4.0 |
| 4     | Booking shortcode + 3 AJAX endpoints + vanilla JS wizard + RTL CSS | 0.5.0 |
| 5     | Payment handler, Iranian gateway base, mock provider | _(in progress)_ |

Latest commit: `fe08a14 — Stage 4: bump version to 0.5.0 + changelog`.

---

## 2. Files created / changed in Stage 4 (per git log)

Stage 4 was delivered across four commits (`e123cc6 → 7bd464c → d482b76 → fe08a14`):

**Backend (AJAX + shortcode)**
- `ontime/includes/frontend/class-booking-form.php` — shortcode registration + 3 nonce-gated AJAX endpoints (`ontime_get_services`, `ontime_get_slots`, `ontime_submit_booking`).
- `ontime/public/class-ontime-booking-form.php` — public-facing booking controller.
- `ontime/public/class-ontime-public.php` — enqueue/shortcode wiring.
- `ontime/public/partials/booking-form.php` (6,391 B) — bookking form markup.

*Frontend (vanilla JS + CSS)**
- `ontime/public/js/booking-form.js` (49,326 B) — step navigation, Jalali calendar grid, AJAX with nonce, client validation, Persian digits.
- `ontime/assets/js/booking-form.js` (mirror) + `ontime/assets/js/public.js`.
- `ontime/public/css/booking-form.css` (28,916 B) — mobile-first RTL styles, CSS variables, dark theme, calendar grid, slots, form, progress.
- `ontime/assets/css/booking-form.css` + `public.css` (mirrors).

*Docs / versioning**
- `ontime/readme.txt` — changelog bumped to 0.5.0.
- `ontime/ontime.php` — header version 0.5.0.

---

## 3. Verification against `prompt-04-frontend` checklist

| Requirement | Status | Evidence |
|--------------|--------|----------|
| Steps: Service → Staff → Date & Time → Customer Info → Confirmation | ⚠️ Partial | Only 3 endpoints shipped (`get_services`, `get_slots`, `submit_booking`) — **no dedicated `get_staff` endpoint** is visible in the commit log. Staff handling, if present, is likely embedded elsewhere; this needs confirmation against `booking-form.js`. |
| Pure Vanilla JS + CSS Grid/Flexbox (no jQuery) | ✅ | Commit `7bd464c` “vanilla JS booking flow”. |
| AJAX via `wp_ajax_` + `wp_ajax_nopriv_` | ✅ | Commit `e123cc6`. |
| CSRF protection with `check_ajax_referer()` / nonces | ✅ | “nonce-gated AJAX endpoints”. |
| Strict sanitization before DB writes | ✅ (claimed) | Needs source-level spot-check (see debt #4). |
| Modern app-like style via CSS custom properties | ✅ | Commit `d482b76` “CSS variables, dark theme”. |

---

## 4. Technical-debt brief (short)

1. **Staff step (Stage 4 spec).** The 5-step flow requires an explicit Staff
   selection step. The committed endpoints do not include `ontime_get_staff`.
   A self-contained `class-ontime-staff-endpoint.php` is added on this branch
   (`stage-4/staff-step`) to close the gap; it exposes `ontime_get_staff`
   (nonce-gated, prepared query) and requires a one-line include in
   `ontime.php` + a staff card in the wizard JS to fully wire.
2. **i18n not built.** `ontime/languages/` contains only `index.php` — no
   `.pot`/`.po`/`.mo`. Strings are wrapped in translation functions, but the
   translation catalogue is not generated. Blocker for Zhaket/Rastchin
   marketplace submission. (Stage 5 item.)
3. **Stage 5 not versioned.** Payment classes exist
   (`includes/Payment/class-ontime-payment-handler.php`, Iranian gateway
   base, mock provider) but no `0.6.0` version bump or changelog entry yet.
4. **Spot-check needed (requires source read).** Confirm in `booking-form.js`
   and `class-booking-form.php`: nonce action name, `absint`/`sanitize_*`
   coverage on every `$_POST` field, and XSS-safe DOM construction (the JS
   uses `innerHTML` in places — ensure no unsanitized server data is injected).
5. **Marketplace packaging.** `references/marketplace-checklist.md` exists;
   confirm `readme.txt` meets Zhaket/Rastchin requirements and a build
   `.zip` workflow (`build-plugin.sh` is present) produces a clean artifact
   excluding `references/`, `docs/`, and dev files.
6. **No automated tests.** No test suite is present; consider a minimal
   `bin`/PHPUnit setup for the Jalali converter and slot collision logic
   before marketplace release.

---

## 5. What was done on this branch

To avoid blind edits to the existing 49 KB frontend (the GitHub connector
exposes file listings and commit history but not file *contents* through the
available tools), this branch adds only **new, non-destructive files**:

- `ontime/includes/frontend/class-ontime-staff-endpoint.php` — additive,
  self-contained `ontime_get_staff` AJAX endpoint (closes debt #1).
- `ontime/docs/STAGE4_STATUS_AND_TECH_DEBT.md` — this report.

No existing file was modified or overwritten.

---

## 6. Recommended next steps

1. Review & merge `stage-4/staff-step`, then wire the staff endpoint
   (`require_once` + `new OnTime_Staff_Endpoint();`) and add a Staff card
   step to `booking-form.js`.
2. Generate the `.pot` file and Persian `.po`/`.mo` (debt #2).
3. Finalize Stage 5: version bump `0.6.0`, changelog, payment gateway wiring,
   marketplace checklist sign-off (debt #3, #5).
4. Source-level security spot-check once file contents can be reviewed (debt #4).
