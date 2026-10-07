# BPSC Exam Scheduler v1.3.1

## Fix

The project has no Unit test files. Its empty `tests/Unit` directory can be omitted from ZIP packages, while `phpunit.xml` previously required it. Remove that unused suite and keep all Feature tests and testing environment settings. The footer version is now v1.3.1.

## Apply after v1.3.0

Extract this patch into the project root and replace the included files. Preserve the existing `.env`, `APP_KEY`, and database. Run from the project root:

```bash
php artisan optimize:clear
php artisan test
```

No migration, dependency installation, or frontend rebuild is required. Refresh the browser to see the new footer version.

## Validation

The complete Feature suite was run with `tests/Unit` absent: 62 tests, 748 assertions passed. Existing APP_URL isolation for WAMP subfolder installations is preserved. Validation used PHP 8.3 and SQLite on Linux; Windows/WAMP was not directly tested.
