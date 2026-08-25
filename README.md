# OnTime — WordPress Appointment Booking Plugin

Lightweight, secure, Jalali-aware online appointment booking system for WordPress,
engineered for release on Iranian marketplaces (Zhaket, Rastchin).

## Features

- **Full Jalali calendar** — all time math runs in UTC; conversion to Persian happens only at the presentation layer (no timezone drift).
- **Free-slot engine** — 30-minute slots computed from each staff member's working hours, existing appointments and Iranian public holidays.
- **Admin interface** — Settings API page + custom `WP_List_Table` for appointments with bulk actions, search and date/staff filters.
- **Mobile-first booking form** — step-by-step `[ontime_booking_form]` shortcode built with vanilla JS and CSS variables (no jQuery).
- **Payment abstraction** — pluggable provider interface, a Mock provider for development and a reference Zarinpal implementation with secure callback/IPN handling.
- **Security** — nonces on every form/AJAX endpoint, strict input sanitization, output escaping, and `$wpdb->prepare()` on every query.
- **i18n** — every user-facing string is wrapped with the `ontime` text domain.

## Structure

```
ontime/
├── ontime.php
├── uninstall.php
├── readme.txt
├── includes/
│   ├── class-ontime.php
│   ├── class-database.php
│   ├── class-calendar-engine.php
│   ├── admin/
│   │   ├── class-admin.php
│   │   └── class-list-table.php
│   ├── frontend/
│   │   └── class-booking-form.php
│   └── payments/
│       ├── class-payment-handler.php
│       ├── class-payment-mock.php
│       └── class-payment-zarinpal.php
├── assets/
│   ├── css/booking.css
│   └── js/booking.js
└── languages/
```

## Installation

1. Copy the `ontime` folder into `wp-content/plugins/`.
2. Activate **OnTime** from the Plugins screen (custom tables are created on activation).
3. Go to **OnTime → Settings** to configure the payment gateway and booking rules.
4. Place the `[ontime_booking_form]` shortcode on any page.

## Development

Use the **Mock** payment provider in settings to exercise the full booking + callback flow offline.

## License

GPL-2.0-or-later
