# v1.1.0 verification — 6 October 2026

Baseline: the delivered BPSC Exam Scheduler v1.0.0 patch applied to project-base-06-10-2026-9-59-PM.zip.

- Full suite: **42 tests passed, 350 assertions**, PHP 8.3.6 / Laravel 13 / SQLite in-memory.
- English regression suite still passes using English locale; Bengali tests cover default login, guest/authenticated switch, persistent preference, permissions and credential preservation, safe return URLs, Bengali free text and numeric input, advertisement optionality/validation, Unicode round-trip through API/audits, localized forms/validation, and stable machine codes.
- Report tests verify actor/filter/format/count/file/locale, XLSX advertisement columns, real Bengali PDF generation, no success log for failed generation, print-page view and user-bound signed print request, forged/cross-user rejection.
- Blade compilation and route caching passed; PHP and compiled Blade syntax checks passed.
- Vite production build passed. Nikosh font bundled locally; CSS asset URLs are relative for root/subdirectory hosting.
- Existing SQLite database migration passed: users (1), schedules (5), designations (20) retained; existing users defaulted to bn and advertisement fields stayed null.
- No new dependency; v1.0.0 reviewed lockfiles are unchanged. No queue changes.
- Target MySQL/WAMP deployment and actual desktop/mobile visual/print acceptance remain to be tested in the user's environment. Browser visual testing is still blocked by this execution environment's socket restrictions.

Audit meaning: generated means server completed PDF/XLSX generation; viewed means the web/API report response rendered; print_requested means the authenticated button request was received, never a claim of physical print completion or offline file opening.
