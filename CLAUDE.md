# SMS Medical website (smsmedical.net)

Public website for Southwest Medical Solutions, Inc. ("SMS Medical"), hosted on Hawk Host (cPanel account `smsmedin`).

## How changes go live
- `site/` is copied into `/home/smsmedin/public_html` by a cron job (`deploy/deploy.sh`, server copy at `~/sms-website-deploy/deploy.sh`) about a minute after a push to `main`.
- Deploy only overwrites; it never deletes server files. public_html also holds many unrelated legacy folders. Never touch those.
- Before copying, the server checks that index.html/index.php exist, that index.html ends in `</html>`, and that every PHP file passes `php -l`. A failing commit is skipped ("blocked") and the old site stays up.
- Check the result at https://smsmedical.net/deploy-status.json (state, commit, time, message). It should show your commit within ~2 minutes.
- Roll back by reverting the commit and pushing. Overwritten files are also backed up on the server in `~/sms-website-deploy/backups/`.
- Staging (new.smsmedical.net) is NOT auto-deployed.

## Site structure
- `site/index.html` is the whole single-page site (hash router; views like #faq, #pgx, #careers). Edit it directly; this file is the live version.
- `site/index.php` serves index.html for every clean URL with per-page title, description, canonical; unknown paths return 404. Add new pages to `$PAGES` and to `sitemap.xml`.
- `site/api/inquiry.php` (account, inquiry, pickup, careers forms) and `site/api/supply-order.php` send mail via Resend. The key is read from `/home/smsmedin/.resend_key`, outside the repo. Never commit keys.
- The main domain runs PHP 7.3 (alt-php73). Keep PHP 7.3 compatible.

## Testing before pushing
Serve `site/` locally (`python3 -m http.server` shows index.html; the clean URLs need PHP) and check with Playwright at 360, 390, 834, 1366 and 1440 widths: no horizontal scroll, no JS errors, nav dropdowns, search, test-directory filter, forms render.

## Content rules (from Sami Baig, CEO)
- Legal name "Southwest Medical Solutions, Inc."; goes by SMS Medical; no "d/b/a" wording.
- Audience is mainly B2B: long-term care (skilled, assisted, independent living), home health, home physician groups, physician offices. Not SNF-only wording.
- Do not invent statistics, testimonials, turnaround times, certifications, partnerships or guarantees.
- Do not name reference labs in client-facing copy. No stock photos presented as SMS staff or facilities.
- No PHI on this server (no BAA with Hawk Host); forms keep their "don't include health information" notes.
- Lab and imaging coverage are the same, except no imaging in Indiana. Courier same-day cutoff is 4:00 PM.
- Provider login links go to https://portal.smsmedical.net in the same tab.
