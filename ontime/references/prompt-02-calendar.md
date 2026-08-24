# Stage 2 — Jalali Calendar Engine (Engineered Prompt)

## Objective
Implement OnTime_Calendar_Engine with UTC-safe calculations, free-slot generation (30-minute intervals), and Persian holiday exclusion.

## Requirements
1. Gregorian → Jalali conversion (pure PHP, no external dep, no Intl)
2. Jalali → UTC back-conversion
3. get_free_slots( $service_id, $j_year, $j_month, $j_day ) excluding booked + holidays + Fridays
4. Persian month/weekday names; format_jalali( $timestamp, $format ) with Persian digits
5. All comparisons in UTC; display converts to Jalali; no external extensions; PHPDoc; no TODOs

## Verification Checklist
- [ ] UTC → Jalali correct
- [ ] Jalali → UTC round-trips
- [ ] Free slots exclude booked slots
- [ ] Free slots exclude holidays
- [ ] Persian digits in output
- [ ] Friday excluded by default
