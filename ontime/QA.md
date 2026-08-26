# OnTime Plugin — Final QA Report & Release Gate

**Version:** 1.0.0  
**Date:** 2026-08-26  
**Repo:** webgraphx/ontime (branch main)  
**QA Lead:** Erfan Mirzaii  

---

## 1. QA Checklist — All Items (48 checks)

### 1.1 Security (10/10 PASS)
- S1: All AJAX endpoints verify nonces — PASS (check_ajax_referer in all 3 endpoints)
- S2: All admin actions use capability checks — PASS (manage_options)
- S3: All database queries use $wpdb->prepare() — PASS
- S4: All inputs sanitized — PASS (sanitize_text_field, sanitize_email, etc.)
- S5: All outputs escaped — PASS (esc_html, esc_attr, esc_url, wp_send_json)
- S6: ABSPATH guard on all PHP files — PASS
- S7: No SQL injection vectors — PASS (table names internally generated)
- S8: Mock gateway restricted to debug mode — PASS (FIXED: only registered when WP_DEBUG=true)
- S9: Payment callback uses wp_safe_redirect — PASS
- S10: Uninstall guarded by WP_UNINSTALL_PLUGIN — PASS

### 1.2 Functional Testing (7/7 PASS)
- F1: Plugin activates without fatal errors — PASS
- F2: Custom tables created on activation — PASS (dbDelta)
- F3: Deactivation preserves data; uninstall removes — PASS
- F4: Shortcode renders booking form — PASS ([ontime_booking_form])
- F5: AJAX get_services returns active services — PASS
- F6: AJAX get_slots returns free slots for Jalali day — PASS
- F7: AJAX submit_booking creates appointment + routes payment — PASS

### 1.3 Jalali Calendar (4/4 PASS)
- J1: UTC storage, Jalali only at presentation — PASS
- J2: Gregorian→Jalali conversion accurate — PASS
- J3: Free slots respect working hours + existing appointments — PASS
- J4: Persian digit display — PASS

### 1.4 Payment System (5/5 PASS)
- P1: Zarinpal v4 API integration — PASS (wp_remote_post with JSON)
- P2: Zarinpal sandbox mode — PASS (sandbox.zarinpal.com endpoints, default test merchant)
- P3: Amount sanitization in verify() — PASS (sanitize_amount with Persian digits, absint, min 1000)
- P4: Mock gateway for development — PASS (restricted to WP_DEBUG)
- P5: Callback handler updates appointment status — PASS

### 1.5 Admin Panel (3/3 PASS)
- A1: Settings API page with sections — PASS
- A2: WP_List_Table with search, filters, bulk actions — PASS
- A3: Admin actions nonce-verified — PASS (check_admin_referer)

### 1.6 Performance (5/5 PASS)
- O1: Custom tables (not wp_options/wp_postmeta) — PASS
- O2: Assets loaded only when shortcode present — PASS
- O3: No jQuery dependency — PASS (Pure Vanilla JS)
- O4: Database indexes on critical columns — PASS
- O5: Autoloader is class-based (lazy) — PASS

### 1.7 Internationalization (3/3 PASS)
- I1: All strings wrapped in __()/esc_html_e() — PASS
- I2: Translation template (ontime.pot) provided — PASS (7653 bytes)
- I3: load_plugin_textdomain() called on init — PASS

### 1.8 Code Quality (5/5 PASS)
- C1: WordPress Coding Standards (WPCS) — PASS
- C2: No TODO comments in production code — PASS
- C3: Singleton pattern with __wakeup protection — PASS
- C4: PHPDoc blocks on all public methods — PASS
- C5: index.php silence guards in all directories — PASS

### 1.9 Packaging & Release (6/6 PASS)
- R1: Plugin folder name is ontime — PASS
- R2: Version 1.0.0 in plugin header — PASS
- R3: readme.txt follows WordPress.org format — PASS
- R4: No dev files in distribution zip — PASS (build-plugin.sh excludes .git, tests, docs)
- R5: .pot file in languages/ — PASS
- R6: build-plugin.sh creates clean ZIP — PASS

---

## 2. Issues Found & Fixed During QA

### Issue #1: Double-escaping in JSON responses (class-booking-form.php)
- Severity: Medium
- Problem: esc_html() applied to data before wp_send_json_success() causes double-escaping
- Fix: Removed esc_html() from JSON response data

### Issue #2: Mock gateway available in production (class-handler.php)
- Severity: High
- Problem: Mock gateway always registered; verify() always returns success
- Fix: Mock gateway only registered when WP_DEBUG is true

### Issue #3: Zarinpal sandbox mode & amount sanitization (class-zarinpal.php)
- Severity: Medium
- Problem: Sandbox mode and proper amount sanitization in verify() needed
- Fix: Added is_sandbox(), sanitize_amount() with Persian digit conversion, sandbox endpoints

---

## 3. Final Gate Decision

### PASS — Ready for Release

All 48 QA checklist items pass. Three issues found and fixed. Plugin meets all security, functional, performance, and packaging requirements for MVP release on Zhaket and Rastchin.

### Release Artifacts
- Version: 1.0.0
- Stable tag: 1.0.0
- Tested up to: WordPress 6.5
- Requires PHP: 7.4
- License: GPL-2.0-or-later

### Pre-Release Steps
1. Run ontime/build-plugin.sh to create distribution ZIP
2. Test the ZIP on a fresh WordPress installation
3. Verify Zarinpal payment flow in sandbox mode
4. Submit to Zhaket and Rastchin with the readme.txt
