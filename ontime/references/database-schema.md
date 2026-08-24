# OnTime Database Schema (Exact Table Definitions)

All tables use prefix {wp_prefix}ontime_, charset utf8mb4, collation utf8mb4_unicode_ci.
All timestamps stored in UTC. Jalali conversion happens only at presentation layer.

## Table: {prefix}ontime_services
- id, name, description, duration (smallint, min), price (decimal Toman), is_active, created_at, updated_at
- PRIMARY KEY (id), KEY is_active

## Table: {prefix}ontime_appointments
- id, service_id, customer_name, customer_phone, customer_email, start_time, end_time, status (pending|confirmed|cancelled|completed), payment_status (unpaid|paid|refunded), transaction_id, notes, created_at, updated_at
- PRIMARY KEY (id), UNIQUE KEY uk_slot (service_id, start_time), KEY status, KEY start_time

## Table: {prefix}ontime_settings
- id, setting_key (unique), setting_value (longtext), autoload, created_at

## Default settings keys (seeded on activation)
| setting_key      | default value | description                          |
|------------------|---------------|--------------------------------------|
| timezone         | Asia/Tehran   | Display timezone                     |
| work_start       | 09:00         | Working hours start (HH:MM)          |
| work_end         | 18:00         | Working hours end (HH:MM)            |
| slot_length      | 30            | Slot length in minutes               |
| weekend_days     | 5             | PHP w weekday numbers (Fri=5)        |
| buffer_minutes   | 0             | Gap between appointments              |
| min_lead_hours   | 2             | Minimum hours before a slot bookable |
| max_future_days  | 30            | How far ahead slots are offered      |
| persian_digits   | 1             | Display Persian numerals (1/0)       |
| date_format      | j F Y         | Default Jalali date format           |
| payment_gateway  | mock          | Active gateway slug                   |
