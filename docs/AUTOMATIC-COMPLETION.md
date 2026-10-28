# Automatic completion of past exams

## Rule

Every day at 20:00 Asia/Dhaka, the Laravel scheduler runs `scheduler:complete-past-exams`. Using today's date in Asia/Dhaka, it changes only non-deleted records with `status=scheduled` and `exam_date < today` to Completed. It applies to every exam type, including Viva and custom types.

Today's exams stay Scheduled even if their End Time has passed. Proposed, Completed, Postponed and Cancelled records are untouched. Backdated Scheduled entries and missed days are caught on the next successful daily run. Setting an old exam back to Scheduled makes it eligible again on the next run; use the appropriate actual status for postponed/cancelled exams.

The command updates each record under a transaction/row lock, rechecks its current date/status, increments its optimistic version and sets updated_by to null for the system action. Created-by, dates, times, counts, structures and notes are preserved. A stale manual edit is rejected with 409. Each change has exactly one `examschedule.auto_completed` audit, with System (Automatic completion), console channel, timestamp, cutoff date/timezone and before/after values. There is no browser/network IP for a CLI job, so IP remains null. Audit failure rolls back that record's status/version update. Repeated runs do not duplicate completed records or their audits.

## Preview or run once

From the project root:

```bash
php artisan scheduler:complete-past-exams --dry-run
php artisan scheduler:complete-past-exams
php artisan schedule:list
```

Dry run only counts eligible records; it changes neither schedules nor audit records. The second command completes eligible records immediately. Schedule list shows registered times, not proof that the operating-system timer is running.

## Local development

With the database service running, keep this terminal open:

```bash
php artisan schedule:work
```

This starts Laravel's scheduler loop. Automatic completion is due once daily at 20:00 Asia/Dhaka; opening Dashboard/Calendar is not required. Closing the terminal stops this local loop. No queue worker is needed.

## Windows/WAMP: one task per day at 8 PM

For a PC using the Bangladesh time zone (UTC+06:00 Dhaka), create a Windows Task Scheduler task. This direct timer starts PHP only once a day for completion; no minute-by-minute process is needed for this feature. Example paths assume WAMP64/PHP 8.3.28; use the installed version if different.

- Program/script: `D:\wamp64\bin\php\php8.3.28\php.exe`
- Add arguments: `"D:\wamp64\www\bpsc-exam-scheduler\artisan" scheduler:complete-past-exams --scheduled`
- Start in: `D:\wamp64\www\bpsc-exam-scheduler`
- Trigger: Daily at 8:00 PM. Do not enable repeating every minute/few minutes.
- Enable running missed tasks as soon as possible; if already running, do not start another instance.
- Select the appropriate Windows account and whether it should run while logged out. The account needs access to PHP, the project and writable storage/cache.

The computer and database must be running. This Windows trigger follows the PC's time zone; set it to Dhaka, or deliberately convert 20:00 Dhaka to the host's configured time. With missed-task recovery enabled, Windows can run a missed task after the PC becomes available. Backlog is handled on the next successful run. The --scheduled flag honors the enable/disable setting below. Row locks and eligibility rechecks prevent duplicate transitions during concurrent invocations.

Confirm Last Run Result is successful and check Administrator Audit Logs for action `examschedule.auto_completed`. Applying the ZIP does not create an operating-system task.

## Existing Laravel master scheduler (Windows/Linux)

If your deployment already runs `php artisan schedule:run` every minute for token pruning or other tasks, keep that setup. The newly registered completion command itself runs only at 20:00 Asia/Dhaka each day. The minute-by-minute master only checks due times; it does not repeatedly scan or complete exams. Do not also add a separate direct completion timer when using this option.

A Linux master cron example, using the normal deployment/service account and your actual paths:

```cron
* * * * * /usr/bin/php /var/www/bpsc-exam-scheduler/artisan schedule:run >> /var/www/bpsc-exam-scheduler/storage/logs/scheduler-cron.log 2>&1
```

The Laravel daily event uses Asia/Dhaka regardless of the host's time zone and a 30-minute overlapping-run mutex. Keep the configured cache working. If the host is offline at 20:00, this Laravel event runs at its next daily 20:00 due time and catches up all eligible past dates. No queue worker is needed.

## Linux: direct daily timer alternative

If you only want a once-daily completion process, use one direct cron entry instead of the master setup for this feature. Keep any other existing scheduled maintenance separately. On a server configured to Asia/Dhaka:

```cron
0 20 * * * /usr/bin/php /var/www/bpsc-exam-scheduler/artisan scheduler:complete-past-exams --scheduled >> /var/www/bpsc-exam-scheduler/storage/logs/exam-completion.log 2>&1
```

On a server configured to UTC, use `0 14 * * *` instead (14:00 UTC = 20:00 Dhaka). Verify the actual server time zone before choosing the cron time.

## Enable/disable

Automatic runs default to enabled. An existing working Laravel scheduler will start this task at its next due time after the patch. Optional `.env` setting:

```dotenv
SCHEDULER_AUTO_COMPLETE_PAST_EXAMS=true
```

Set it to false to skip automatic completion, then reload configuration:

```bash
php artisan optimize:clear
```

If your deployment caches configuration, rebuild that cache afterwards with `php artisan config:cache`. Host timers using --scheduled also honor this flag. An explicit manual command without --scheduled remains available when automatic runs are disabled.

If a run fails partway, already committed records retain their audits and remaining records are retried on a later run. Investigate the recorded error; do not change statuses directly in SQL to bypass auditing/version protection.
