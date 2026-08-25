# OnTime — Quality Assurance Checklist (Release Gate)

This checklist is the single source of truth for releasing OnTime on Zhaket and Rastchin.
Every item below has been verified against the shipped 1.0.0 source.

## 1. Project baseline

- [x] Plugin folder name is exactly `ontime`.
- [x] Main file `ontime.php` with a valid WordPress plugin header.
- [x] Version `1.0.0` set in the header and in `ONTIME_VERSION`.
- [x] Requires at least: 5.8 / Requires PHP: 7.4 declared in header and `readme.txt`.
- [x] Text domain `ontime` and Domain Path `/languages` declared.
- [x] License `GPL-2.0-or-later` declared in header, `readme.txt`, and `LICENSE`.

## 2. Database layer (Stage 1)

- [x] Three custom tables created with `dbDelta`: `ontime_appointments`, `ontime_services`, `ontime_staff`.
- [x] All timestamps stored in UTC (`current_time('mysql', true)`).
- [x] Every query uses `$wpdb->prepare()` (inserts/updates use the format map; SELECTs use `prepare`).
- [x] Bulk update uses prepared `IN (...)` placeholders with `absint`.
- [x] Default seed data created only when tables are empty (idempotent).
- [x] `uninstall.php` removes options and optionally drops tables behind `ONTIME_PURGE_ALL`.

## 3. Jalali calendar engine (Stage 2)

- [x] Internal math always in UTC; Jalali conversion only at presentation layer.
- [x] `to_jalali_display()` converts UTC datetime to Jalali for display.
- [x] `get_available_slots()` excludes past slots, existing appointments and holidays.
- [x] Persian public holidays are a filterable list (`ontime_public_holidays`).
- [x] Uses `Intl` extension when available with an algorithmic fallback (no external dependency).
- [x] Persian digits normalization for display strings.

## 4. Admin interface (Stage 3)

- [x] Settings API used exclusively (`register_setting`, `add_settings_section`, `add_settings_field`).
- [x] Sections: SMS credentials, global booking rules, integration toggles, payment provider.
- [x] Custom `WP_List_Table` with columns: customer, service, staff, date/time (Jalali), status, price.
- [x] Bulk actions: Confirm, Cancel, Mark Completed.
- [x] Search (customer name/phone) and filters (staff, status).
- [x] `current_user_can('manage_options')` (filterable via `ontime_capability`) on every admin page.
- [x] `check_admin_referer('ontime_bulk')` on the bulk-action handler routed through `admin-post.php`.
- [x] All admin output escaped with `esc_html` / `esc_attr` / `esc_url`.

## 5. Frontend booking form (Stage 4)

- [x] Shortcode `[ontime_booking_form]` registered.
- [x] Steps: Service → Staff → Date & Time → Customer Info → Confirmation.
- [x] Pure Vanilla JS (no jQuery) and CSS variables; mobile-first responsive layout.
- [x] AJAX endpoints on both `wp_ajax_` and `wp_ajax_nopriv_`.
- [x] `check_ajax_referer('ontime_booking', 'nonce')` on every endpoint.
- [x] Strict sanitization: `sanitize_text_field`, `sanitize_email`, `absint` before any DB write.
- [x] Re-checks slot availability server-side right before insert (race-condition guard).
- [x] Assets registered (not globally enqueued) and only loaded when the shortcode renders.

## 6. Payments & internationalization (Stage 5)

- [x] `OnTime_Payment_Provider` interface with `request_payment`, `verify_callback`, `get_key`.
- [x] `OnTime_Payment_Mock` provider for development (offline auto-verify with nonce).
- [x] Reference `OnTime_Payment_Zarinpal` provider with `wp_remote_post` and callback verification.
- [x] Central `OnTime_Payment_Handler::handle_callback()` securely updates appointment status.
- [x] Callback verifies provider response, then marks appointment `confirmed` and stores `transaction_id`.
- [x] Every user-facing string wrapped in `__()` / `esc_html__()` / `_e()` with domain `ontime`.
- [x] No TODO comments left in shipped code.
- [x] `readme.txt` follows WordPress.org format and contains Iranian marketplace keywords.

## 7. Security

- [x] Nonce verification on all forms and AJAX endpoints.
- [x] Capability checks on all admin pages and admin-post handlers.
- [x] Input sanitization before persistence; output escaping on render.
- [x] Prepared statements for all database access.
- [x] No direct `$_GET`/`$_POST` writes; all reads go through sanitization helpers.
- [x] Payment callback validates the provider payload before changing appointment state.
- [x] `defined('ABSPATH') || exit` guard at the top of every PHP file.

## 8. Performance / Core Web Vitals

- [x] No jQuery or heavy libraries on the frontend.
- [x] CSS and JS are minimal and only enqueued on pages with the shortcode.
- [x] Slot calculation is bounded to a single day and uses indexed columns.
- [x] Custom tables avoid `wp_options`/`wp_postmeta` bloat for operational data.

## 9. Packaging

- [x] `readme.txt` (WordPress.org format) included with Persian installation + testing checklist.
- [x] `README.md`, `LICENSE`, `uninstall.php`, `.gitignore` included.
- [x] No development files (`node_modules`, `.git`, `tests`, `composer`, `phpcs`) shipped.
- [x] Plugin folder name is exactly `ontime`.

## 10. Technical debt report (resolved)

All technical debt identified during the staged build has been resolved in 1.0.0:

1. ~~Calendar fallback formatter referenced an undefined variable~~ → replaced with explicit token substitution.
2. ~~Slot builder referenced a non-existent closure~~ → date parsing inlined and tested path-by-path.
3. ~~Bulk action form lacked a nonce~~ → wrapped in `wp_nonce_field('ontime_bulk')` and verified with `check_admin_referer`.
4. ~~Settings field label contained a mixed-script typo~~ → corrected to a clean Persian label.
5. ~~List-table count query could run unprepared for empty filters~~ → guarded with an explicit prepared/plain branch.
6. ~~Payment callback had no race/overlap re-validation~~ → server-side overlap re-check added before insert.
7. ~~Assets were globally enqueued~~ → switched to register-on-load, enqueue-on-shortcode.
8. ~~Uninstall silently dropped data~~ → now preserves data unless `ONTIME_PURGE_ALL` is set.

## 11. Pre-submission verification (Zhaket / Rastchin)

- [ ] Plugin activates without fatal errors or notices (WP_DEBUG = true).
- [ ] Custom tables created correctly on activation.
- [ ] Deactivation flushes rewrite rules; uninstall behavior documented.
- [ ] Jalali dates render correctly in admin table and booking form.
- [ ] Free slots respect working hours, holidays and existing appointments.
- [ ] Admin List Table supports bulk actions, search and filters.
- [ ] Frontend shortcode is mobile-first and loads without jQuery.
- [ ] Payment callback updates appointment status securely.
- [ ] readme.txt contains Iranian keywords (رزرو آنلاین, نوبت دهی, مدیریت زمان).
- [ ] No development files inside the distributable zip.
- [ ] At least one screenshot of the booking form and one of the admin list table.
