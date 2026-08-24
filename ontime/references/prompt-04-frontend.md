# Stage 4 — Frontend Booking Form (Engineered Prompt)

## Objective
Mobile-first, step-by-step booking form via [ontime_booking_form] shortcode using pure Vanilla JS + CSS variables. All steps via secure AJAX.

## Requirements
1. Shortcode [ontime_booking_form] with attributes service_id, theme; conditional enqueue
2. Multi-step: Service → Date (Jalali grid) → Time slot (AJAX from Stage 2) → Customer info → Confirm/Payment
3. AJAX endpoints (wp_ajax_nopriv_ + wp_ajax_): ontime_get_services, ontime_get_slots, ontime_submit_booking; check_ajax_referer( 'ontime_nonce', 'nonce' ); wp_send_json_*; sanitize all input
4. CSS: variables, RTL default, mobile-first, no framework, < 10KB
5. JS: no jQuery, step nav + progress, validation, loading states, Persian error messages via wp_localize_script
6. i18n (ontime domain); escape output; WPCS; PHPDoc + JSDoc; no TODOs

## Verification Checklist
- [ ] Shortcode renders widget
- [ ] Assets conditionally enqueued
- [ ] Each AJAX endpoint checks nonce
- [ ] Input sanitized server-side
- [ ] Free slots correctly fetched
- [ ] Appointment created in pending status
- [ ] RTL mobile-first layout
- [ ] Persian error messages
