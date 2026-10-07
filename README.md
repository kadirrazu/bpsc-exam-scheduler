# BPSC Exam Scheduler

Staff-only BPSC exam scheduling with responsive web access and an Android-ready API. Version: **v1.3.1**.

- Administrator: all management and audit access; Editor: schedule entry/edit; Viewer: read/export only.
- Date filters, conditional Viva boards, XLSX/PDF/print, Bengali/English web UI, and actor/action/time/IP auditing.
- Post Grade is an optional positive integer (e.g. 9 or 10). Terminal/Artisan messages stay English.

## Requirements

PHP 8.3+, Composer 2, MySQL, and Node.js 20.19+ or 22.12+ with npm. Enable extensions required by Composer; use `composer check-platform-reqs`. Tests also need PDO SQLite. Run commands from the project root.

## Fresh installation

Use a dedicated empty database. Create `bpsc_exam_scheduler` with `utf8mb4`, then:

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
composer install
composer check-platform-reqs
```

Edit `.env`: set `APP_URL`, `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Use `APP_URL=http://localhost:8000` for Artisan serve, or your WAMP URL ending in `/public` for a subfolder setup.

```bash
php artisan optimize:clear
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DesignationSeeder
php artisan scheduler:create-admin
npm ci
npm run build
php artisan serve
```

Open `/login`. No default administrator/password is provided. The create-admin command asks for credentials and Unit. New passwords need 8+ characters with upper/lowercase letters, a number and a symbol.

## Existing installation / updates

Back up code/database, apply the Paste & Replace patch, then read its update notes. For **v1.3.1**:

```bash
php artisan optimize:clear
php artisan test
```

Keep the existing `.env` and `APP_KEY`. Do not reset the database, run `migrate:fresh`, re-seed users, or regenerate the key. This patch applies after v1.3.0 and needs no migration, dependency install or asset rebuild. Refresh the browser after updates.

## Deployment and operations

Serve only `public/`, require HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, and enable secure session cookies. Keep `.env`/backups private. After configuring the server, run `php artisan config:cache`, `route:cache`, and `view:cache`. Run `php artisan schedule:run` every minute to prune expired API tokens; a queue worker is not required for current reports.

Web: `/login` → `/dashboard`. API: `/api/v1`. Timezone: `Asia/Dhaka`. UI language selection never changes terminal language. Footer version follows `config/scheduler.php`.

See [API documentation](docs/API.md), [deployment/security](docs/SECURITY-AND-DEPLOYMENT.md), and [v1.3.1 update notes](UPDATE-v1.3.1-TEST-SUITE-07-10-2026.md).
