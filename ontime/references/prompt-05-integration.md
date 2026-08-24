# Stage 5 — Payments, i18n, QA & Packaging (Engineered Prompt)

## Objective
Modular payment handler (interface + Mock + Iranian gateway), full i18n, standards verification, marketplace-ready readme.txt.

## Requirements
1. OnTime_Payment_Gateway interface: request(), verify(), optional refund()
   - OnTime_Payment_Mock, OnTime_Payment_Zarinpal (wp_remote_post, callback via admin_init/rewrite, update appointment payment_status + transaction_id)
   - Gateway via Settings; merchant creds in settings; error handling
2. i18n: all strings __()/_e() with ontime; .pot in languages/; Persian .mo/.po; Persian digit helper
3. Security hardening: sanitize/escape/prepare/nonce/capability/ABSPATH final pass
4. Standards: php -l; WPCS; no TODOs/debug; remove dev files
5. readme.txt (WordPress.org format), Persian, Zhaket/Rastchin compatible
6. Packaging: clean ontime.zip → wp-content/plugins/ontime/; exclude .git, node_modules, references/

## Verification Checklist
- [ ] Payment interface + Mock + Zarinpal functional
- [ ] Callback updates appointment status
- [ ] All strings internationalized; .pot present
- [ ] PHP lint passes on all files
- [ ] No TODOs / debug code
- [ ] readme.txt valid
- [ ] Zip clean and well-structured
- [ ] Marketplace checklist satisfied
