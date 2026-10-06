# Release verification — 6 October 2026

Source: project-base-06-10-2026-9-59-PM.zip. This is a new-project overlay, not an update for a live Choice installation.

| Check | Result |
| --- | --- |
| Automated Laravel/PHPUnit feature tests | 32 passed, 245 assertions; PHP 8.3.6, SQLite in-memory |
| PHP syntax | All shipped PHP checked; no syntax errors |
| Blade compilation | `view:cache` passed |
| Route cache | `route:cache` passed |
| Production frontend build | Vite build passed |
| Composer lock validation | Passed; only new PHP dependency is Sanctum 4.3.3 |
| Composer advisory scan | No known advisories reported at verification time |
| npm advisory scan | 0 advisories after dependency cleanup and patch updates |
| Paste & Replace overlay / cleanup | Checked against an untouched extraction of the supplied baseline; second cleanup run is safe |
| Real browser/mobile screenshot verification | Not completed: environment browser socket restrictions prevented browser launch |
| Target MySQL/WAMP migration and real-device acceptance | Must be verified in the user's environment |
| Independent deployment/penetration assessment | Separate production acceptance step; not performed here |

Tests cover staff-only entry and absence of Choice/public/registration routes; all three roles; unauthorized direct requests; schedule create/edit/delete and stale-version conflicts; today/week boundary dates; filtering/totals; zero/negative count and Viva center/board validation; optional time rules; staff/designation forms and CRUD; safe staff profile/password updates; last/self administrator protection; inactive/deleted staff; login logging/throttling; API token hashes/expiry/revocation/logout; API roles/date serialization/options; audit before/after snapshots and password exclusion; XLSX typed-string formula protection; real PDF/XLSX generation; Bengali OTL text shaping; and security response headers.

Dependency advisories are point-in-time results, not a guarantee of future security. No default/test user, test database, source `.env`, vendor tree, node_modules or runtime cache/log is included in the release ZIP. Precompiled CSS/JS is included to support first installation; source and lockfiles remain authoritative for future builds.
