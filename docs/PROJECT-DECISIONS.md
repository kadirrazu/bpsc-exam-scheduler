# BPSC Exam Scheduler — requirements and decisions

Source baseline: project-base-06-10-2026-9-59-PM.zip (6 October 2026).

Name suggestion: **BPSC Exam Scheduler**. UI Bengali by default, switchable to English; staff-only, Bangladesh time.

Reused: Laravel/Fortify authentication, Tabler/Vite, staff CRUD/forms, designation model, session management, PhpSpreadsheet/mPDF, Nikosh Bengali font and Choice PDF text-node shaping. Choice-domain routes/controllers/models/services/views/tests/migrations are removed from the new copy via a checksum-guarded manifest. Original Choice source and data are not edited.

Single database: staff/designations/schedules/audits/tokens. This is independent of the BCS Result Processing multi-examination databases.

| Capability | Administrator | Editor | Viewer |
| --- | --- | --- | --- |
| Dashboard, list/details, filters | Yes | Yes | Yes |
| XLSX/PDF/print schedule | Yes | Yes | Yes |
| Create/edit exam schedules | Yes | Yes | No |
| Delete exam schedules | Yes | No | No |
| User/designation administration | Yes | No | No |
| Audit log viewing | Yes | No | No |
| Own profile/password | Yes | Yes | Yes |

Designation is descriptive, not a permission role. Secretary/Director etc. do not implicitly gain administrator permissions.

Data: title required, reference optional, type/unit/date required, start/end optional, candidate count required; Viva boards required, other exams center count required; notes optional, status required. Advertisement number/year optional (v1.1.0). Exam types: NC Preliminary MCQ, NC Written, NC Viva, BCS Preliminary/Written/Viva, Departmental, Senior Scale. Initial units: Unit 01–20, Non Cadre (Exam), BCS, Departmental, Senior Scale; review exact unit names during acceptance.

Default date range: today to today+6 inclusive. Single date overrides valid From/To. A single From or To means that one day. Pagination 25 rows; totals cover all matching rows. Exports use identical filters and include all matching rows (up to 10,000). Separate Centers/Boards columns accommodate mixed lists.

Schedule mutations: optimistic integer version + row lock, status changes preserve history, soft deletion. Audit snapshots are in the same transaction as normal CRUD. Request audits separately record route/method/subject/status/channel/IP/device and record all dynamic authenticated page requests, including denied requests. Login/logout/failed authentication have explicit audit events. Client-side interactions and static assets are not server audit events. Deleted user names/IDs remain in audit snapshots. Audit data has no UI/API update/delete endpoint; database administrators still have physical database access.

API: versioned `/api/v1`, Sanctum opaque tokens, SHA-256 token storage, 24-hour expiry, five simultaneous device tokens maximum, revoke on sensitive account changes, no automatic refresh tokens. Current role is checked on every request; client role claims are ignored. No Android app is included.

Initial limits/assumptions: all units available to all roles; types/units defined in config, not CRUD; one row per day/session; no public frontend, attachments, notifications, exam-center directory or batch recurrence. Range cap 366 days/export cap 10,000 prevents unbounded synchronous generation. Larger reporting/long-range demand can justify a queued export phase.

Production is a separate deployment/security acceptance task. Application hardening cannot guarantee that hacking is impossible.

## v1.1.0 additions

User preferred_locale defaults bn. Bengali UI/validation and local font, English switch, Unicode data entry, Gregorian advertisement year normalized from Bengali digits, report.viewed/report.generated/report.print_requested audit records. Language display never alters stored content or machine codes. See UPDATE-v1.1.0-BANGLA-REPORT-AUDIT.md.


## v1.2.0 revised UI and staff requirements (7 October 2026)

These requirements supersede translated product/type/unit labels and the earlier 12-character password minimum. Product: BPSC Exam Scheduler in every locale. Bengali commission name: বাংলাদেশ সরকারী কর্ম কমিশন, as explicitly requested. All exam types and units remain English. Exam units = 20 numbered units + Non Cadre (Exam) + Cadre (Exam). Staff units additionally include the two confidential branches, IT Section, Administration Wing and Law Wing. Staff unit is administrator-managed organizational metadata, not access scope. Existing null staff units and historical schedule units are preserved, never silently inferred. Minimum password length = 8 with mixed case, digit and symbol. Two-row header, workflow/user-management dropdowns, local icon font, accessible password reveal controls and custom favicon. Choice footer text preserved verbatim except commission name; see the v1.2.0 update file for exact text and the deliberately unchanged footer version label.


## v1.2.1 schedule fields (7 October 2026)

Post Name replaces the free Exam Title input and is mandatory at entry, appearing first. Post Code, Ministry/Organization, Number of Candidates and Notes/Remarks are optional. Unspecified count is null, never silently converted to zero; summaries disclose unspecified records. Historical titles and counts stay intact. Proposed status added; distinct yellow/blue/green Proposed/Scheduled/Completed backgrounds propagate to web, print/PDF and XLSX. The new post_name/ministry fields are audited and returned by API; see the combined update file.


## v1.2.2 English reference fields and audit identity (7 October 2026)

Designation, Role, Unit and Status labels/options are always English. Stored designation names render directly; Administrator/Editor/Viewer and Active/Inactive remain English. Audit identity includes who/what/when/source IP, with separate IP and channel columns and an exact IPv4/IPv6 filter, administrator-only on web/API. Console operations have no network source and use null IP. Proxy headers are not blindly trusted; known proxy configuration is deployment-specific. See UPDATE-v1.2.2-ENGLISH-FIELDS-AUDIT-IP-07-10-2026.md.


## v1.2.3 schedule form revision (7 October 2026)

Latest field order: Unit, Exam Type, Post Code, Post Name, Ministry/Organization, Advertisement Number, Advertisement Year, Exam Date, Start Time, End Time, Candidates, conditional Centers/Boards, Status, Notes/Remarks. Ministry/Organization is now mandatory. Non-viva Centers is optional; Viva Boards is mandatory and replaces Centers in the UI. Prior v1.2.1 optional-organization rules are superseded. See UPDATE-v1.2.3-SCHEDULE-FORM-07-10-2026.md.


## v1.2.4 footer and personal menu (7 October 2026)

Footer commission name is English in every locale: Bangladesh Public Service Commission (BPSC). Software Version uses v + config('scheduler.version'), superseding the formerly preserved literal 1.0. Subsequent release version bumps automatically update the footer. The personal account dropdown shows name/designation/unit only; own Role appears on My Profile, while administrator role management and authorization remain intact. See UPDATE-v1.2.4-FOOTER-USER-MENU-07-10-2026.md.


## v1.3.0 grade and terminal language (7 October 2026)

Authoritative source is project-reference-07-10-2026-1-22-AM.zip. Optional positive-integer Post Grade follows Post Name, with nullable historical values. The API/model store numeric grade, Bengali digits accepted. README.md provides concise English installation/update instructions. CLI app locale is English; HTTP locale middleware preserves Bengali-default web and saved language selection. Source audit regressions were corrected to match existing exact-IP/console-IP tests. See UPDATE-v1.3.0-POST-GRADE-README-07-10-2026.md.
