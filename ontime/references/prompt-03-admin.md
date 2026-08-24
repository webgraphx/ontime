# Stage 3 — Admin Interface (Engineered Prompt)

## Objective
Settings API page + custom WP_List_Table for appointments with bulk actions, search and filters. Enforce capability checks.

## Requirements
1. Top-level menu "OnTime" + submenus (Appointments, Services, Settings); capability manage_options; dashicons-calendar-alt
2. Settings via Settings API; sections: General, Payments, Display; sanitize_callback; settings_fields()
3. OnTime_Admin_List_Table: columns, sortable, bulk (Confirm/Cancel/Delete), search, filters (status/service/date range), pagination, row actions; check_admin_referer(); $wpdb->prepare(); escape output
4. Services CRUD + inline edit
5. Capability checks everywhere; nonces on all forms; WPCS; PHPDoc; no TODOs

## Verification Checklist
- [ ] Settings via Settings API only
- [ ] Capability checks on all admin actions
- [ ] Nonces on all forms
- [ ] Pagination, search, sort work
- [ ] Bulk actions protected
- [ ] All output escaped; all DB queries prepared
