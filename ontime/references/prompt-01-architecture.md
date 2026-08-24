# Stage 1 — Database Schema & Core Architecture (Engineered Prompt)

## Objective
Design and implement the three custom tables and the OOP foundation (Singleton + Autoloader + Database class).

## Requirements
1. Three custom tables (services, appointments, settings) — see database-schema.md
2. OnTime_Database Singleton with create_tables() via dbDelta, seed_defaults(), get_table()
3. All queries via $wpdb->prepare(); charset utf8mb4
4. UTC storage; Jalali only at presentation layer
5. WPCS, PHPDoc, no TODOs

## Verification Checklist
- [ ] Three tables created on activation
- [ ] dbDelta idempotent
- [ ] All queries use prepare()
- [ ] Default service + settings seeded
- [ ] utf8mb4 charset applied
