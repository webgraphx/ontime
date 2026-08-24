# Marketplace Release Checklist (Zhaket / Rastchin)

## Code Quality
- [ ] PHP 7.4+ compatible, no deprecated functions
- [ ] WordPress 5.8+ compatible
- [ ] WPCS followed; php -l passes on every PHP file
- [ ] No TODO / FIXME / debug code
- [ ] All functions prefixed (ontime_ / OnTime_)

## Security
- [ ] ABSPATH check in every PHP file
- [ ] All inputs sanitized; all outputs escaped
- [ ] All DB queries prepared
- [ ] Nonces on all forms and AJAX
- [ ] Capability checks on all admin actions
- [ ] No hardcoded credentials
- [ ] index.php silence files in all directories

## i18n
- [ ] Text domain ontime on all strings
- [ ] .pot file in languages/; Persian .mo/.po included
- [ ] Domain Path in header matches

## Database
- [ ] Custom tables via dbDelta(); idempotent activation
- [ ] uninstall.php removes tables and options
- [ ] utf8mb4 charset; all timestamps UTC

## Assets
- [ ] CSS/JS conditionally enqueued; no external CDN
- [ ] Versioned asset URLs; RTL support; mobile responsive

## Packaging
- [ ] readme.txt (WordPress.org format); standard headers
- [ ] Clean zip (no .git, no dev files, no references/)
- [ ] Zip extracts to wp-content/plugins/ontime/
- [ ] License GPL-2.0+; version consistent

## Marketplace-Specific (Zhaket / Rastchin)
- [ ] Persian description in readme
- [ ] Pricing in Toman documented; support contact included
- [ ] Changelog in Persian; no links to competitors
