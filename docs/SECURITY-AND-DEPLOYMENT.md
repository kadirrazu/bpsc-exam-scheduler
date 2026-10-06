# Security and deployment acceptance

Included application controls: Fortify password hashing, CSRF on web mutations, server-side roles/Gates, no public registration/recovery, active-account checks, session revocation/versioning, hashed Sanctum tokens/expiry/revocation, login/API/export throttling, strict input allowlists, SQL parameter binding, escaped Blade output, XLSX formula injection prevention, no remote PDF assets, admin-only append-only model audits, confirmation for delete, protected last administrator, same-origin script CSP in production and security headers.

No security guarantee can make all hacking impossible. This version is ready for local/staging acceptance; production requires the host controls and acceptance below. MFA is not implemented yet; prioritize administrator MFA and matching API challenges before broad production use. Existing two-factor/passkey database columns alone do not enable MFA.

## Hosting configuration

- Serve **only public/**. Do not expose Laravel root, `.env`, storage, vendor, ZIP/backups, composer metadata, cleanup scripts or database files. `prepare-scheduler.php` is CLI-only and returns 404 through HTTP, but should still be outside public/.
- Require HTTPS and redirect HTTP at the reverse proxy/web server. Set APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain; SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true, SESSION_ENCRYPT=true, SESSION_SAME_SITE=strict, SESSION_LIFETIME=30, SESSION_COOKIE=bpsc_scheduler_session.
- When TLS terminates at a proxy, configure Laravel trusted proxies **only for that deployment's trusted proxy addresses**; do not blindly trust every incoming forwarded header. Test secure cookies, client IP auditing and IP throttling from the actual host. Application cannot infer the correct proxy topology from a ZIP.
- Keep APP_KEY/secrets out of git and logs. Do not regenerate APP_KEY after real use. Use separate databases/credentials/app keys/cookie names from Choice Taking.
- Create the first administrator interactively. Do not retain Choice test accounts or known passwords. Seeders never create them in this project.
- Production runtime DB account should have the minimum permissions required; use separate migration credentials where available. Restrict audit_logs UPDATE/DELETE permissions for runtime credentials at the database level. The ORM's immutability does not stop a privileged DBA or raw SQL.
- Use shared Redis/database cache and sessions for multiple web instances; throttling must be shared. Limit PHP request sizes and enable reverse-proxy request/time/rate limits. Keep production PDF/cache directories outside public with limited write access.
- Set up encrypted backups, tested restore, logs/monitoring, alerting for repeated auth failures and unexpected 5xx, disk-space monitoring for audit growth. Audit retention is an administrative policy; no silent pruning is configured.
- Run scheduled token pruning (`schedule:run` each minute). No queue worker needed for v1.
- CORS is unnecessary for a native Android app; do not add permissive browser origins/credentialed wildcard CORS.
- Keep PHP/Laravel/OS dependencies updated through reviewed changes. Run composer audit and npm audit regularly; this package's lock verification is a point-in-time check, not a permanent clean bill of health.

## Production build

After configuration and migration:

```text
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Remove `public/hot` if a local Vite dev server was used. Keep debug disabled. The production CSP deliberately permits same-origin scripts only; inline style attributes remain allowed for Tabler. Third-party analytics/scripts require an explicit reviewed CSP change.

## Acceptance checks on target WAMP/staging/server

1. Login each of the three roles; attempt forbidden direct URLs and API mutations as Viewer/Editor; confirm 403 and no changed data.
2. Create/edit/delete non-Viva/Viva schedules; dates/times/counts; confirm conflict protection with two simultaneous browser tabs. Check MySQL migration completion.
3. Verify 390px phone/desktop navigation, forms, table scrolling and no page-wide horizontal overflow; PDF/XLSX/print with many rows and Bengali titles; printed table headings/page numbers.
4. Test revoked/expired token and stale sessions after admin password/role/activation changes, login abuse throttling, secure-cookie handling behind the actual proxy, and independent unauthorized accounts.
5. Confirm Administrator audit-only access, history/actor/IP accuracy and no raw credentials. Static asset requests/client-only UI interactions are outside audit scope.
6. Run an independent authenticated security assessment and verify recovery from backups before public hosting. Android implementation follows API acceptance; don't bypass TLS certificate validation.
