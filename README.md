# BPSC Exam Scheduler

Staff-only exam scheduling for BPSC, with role-based web/API access, Bengali/English UI, schedule reports and audit logs.

## Installation

Requirements: PHP 8.3+, Composer 2, MySQL, Node.js 20.19+ or 22.12+ and npm. Tests require PDO SQLite. Run commands from the project root.

Create an empty MySQL database using utf8mb4, then:

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
composer install
composer check-platform-reqs
```

Set APP_URL and DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env. For Artisan serve, use APP_URL=http://127.0.0.1:8000; for WAMP subfolders, use the actual URL ending in /public.

```bash
php artisan key:generate
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=DesignationSeeder
php artisan scheduler:create-admin
npm ci
npm run build
php artisan serve
```

Open /login. The create-admin command creates the first administrator; there are no default credentials.

## Development commands

Run these in separate terminals while working:

```bash
php artisan serve
```

```bash
npm run dev
```

As needed:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
npm run build
```

Use migrate only when new migrations are present, and build for production assets. On an existing installation, retain .env and APP_KEY; do not reset the database or rerun first-install steps.
