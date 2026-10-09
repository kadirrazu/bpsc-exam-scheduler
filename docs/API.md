# BPSC Exam Scheduler API v1

Base: `https://your-host/api/v1`. Always send `Accept: application/json`. Mutations use JSON `Content-Type: application/json` and TLS. Obtain token through login; send `Authorization: Bearer <token>` afterwards. Store it in Android encrypted storage, never logs or URLs. UI session cookies do not authenticate API requests.

## Authentication and metadata

| Method | Path | Role / result |
| --- | --- | --- |
| POST | /auth/login | email, password, device_name → token, expires_at, user |
| GET | /auth/me | Own user/designation; password omitted |
| POST | /auth/logout | Revoke current API token; 204 |
| POST | /auth/logout-all | Revoke all own API tokens; 204; does not log out web |
| GET | /options | Current types, units, statuses, roles, active designations |

Example login body:

```json
{"email":"you@example.gov.bd","password":"your-password","device_name":"Android — personal phone"}
```

Tokens expire after 24 hours. At most five active tokens per user; another login evicts the oldest when full. Password/email/role/activation change revokes tokens. Reauthenticate; no refresh token is issued.

## Schedules

| Method | Path | Permission |
| --- | --- | --- |
| GET | /schedules | All active roles; filters + summary + schedules paginator |
| GET | /schedules/{id} | All roles; `{data: record}` |
| POST | /schedules | Admin/Editor; `{data: record}`, 201 |
| PUT/PATCH | /schedules/{id} | Admin/Editor; full validated payload + version |
| DELETE | /schedules/{id} | Administrator; `confirmation: DELETE`, `version` required; 204 |
| GET | /schedules/export/xlsx | All roles; binary XLSX |
| GET | /schedules/export/pdf | All roles; binary PDF |

List/export query fields: `date`, `from`, `to` (YYYY-MM-DD), `exam_type`, `unit`, `status`, `search`. List supports `page`. Seven-day default. Responses return type codes; labels are in `/options`. Times are `HH:mm:ss` in records and must be sent as `HH:mm` on writes; dates identify Bangladesh calendar days. `exam_date` is serialized as YYYY-MM-DD, with no timezone conversion. `start_time`/`end_time` are local Bangladesh wall-clock times. On editing, copy the `version` from the latest record.

Non-Viva create:

```json
{
  "title":"47th BCS Written — English",
  "reference":"47 BCS",
  "exam_type":"written",
  "unit":"Unit 01",
  "exam_date":"2026-10-12",
  "start_time":"10:00",
  "end_time":"13:00",
  "candidate_count":500,
  "center_count":5,
  "status":"scheduled",
  "notes":null
}
```

For Viva, use `viva`, omit center_count, supply board_count. Unused field may be null; a positive value is rejected. Counts are integer, >=0. Do not send version on create; include it on update/delete. Update currently expects all required schedule fields, even with PATCH.

## Administration and profile

Administrator only: GET/POST `/users`, GET/PUT/PATCH/DELETE `/users/{id}`; GET/POST `/designations`, GET/PUT/PATCH/DELETE `/designations/{id}`; GET `/audit-logs`.

User payload: name, email, designation_id, role (`admin`/`editor`/`viewer`), is_active, password/password_confirmation (required create, optional update). Password >=12, mixed case, number, symbol. Designation must be active. No self-demotion/deactivation/deletion; at least one active administrator must remain. Deleted users soft-delete and cannot authenticate.

Designation payload: name (unique, <=100), sort_order (0–255), is_active (boolean). Assigned designations cannot be deleted; deactivate instead. DELETE user/designation requires `{"confirmation":"DELETE"}`. Designation list supports search/page; users supports search/role/status (`active`/`inactive`)/page.

GET audit filters: from/to dates, actor_id, exact action, page. Common actions: `auth.login`, `auth.logout`, `auth.failed`, `request.view`, `request.action`, `request.denied`, `examschedule.created/updated/deleted`, `user.created/updated/deleted`, `designation.created/updated/deleted`. Password changes are represented by `password_changed`, never password contents.

All roles: PUT `/profile` (name, email, designation_id; role/status/other user ID prohibited); PUT `/profile/password` (current_password, password, password_confirmation). Password/email update revokes the calling API token too; sign in again.

## Error contract

| Status | Meaning / client action |
| --- | --- |
| 401 | Missing/invalid/expired token; sign in |
| 403 | Permission denied or inactive account |
| 404 | Unknown/deleted record or unsupported route |
| 409 | Stale schedule version; reload, show changes, retry intentionally |
| 422 | Validation errors / invalid credentials / reporting limit; inspect errors/message |
| 429 | Rate limit; honour Retry-After |
| 500 | Unexpected server failure; do not blindly retry mutations |

Login: 5/minute per email+IP and 20/minute per IP; API 120/minute per authenticated user; export 10/minute per user. Account protection limits use a shared cache on multi-server deployments. Expired tokens can be pruned with `php artisan sanctum:prune-expired --hours=24`.

## v1.1.0 localization and advertisement fields

Optional schedule fields: advertisement_number (string/null, max 100), advertisement_year (1900–9999/null). Bengali numeric input normalizes to ASCII; returned year integer. Accept-Language bn/bn-BD/en selects response messages/labels; otherwise user preferred_locale, default bn. /options adds unit_labels with canonical units as keys. Own /profile accepts preferred_locale bn/en. Native clients keep canonical codes; no automatic translation of saved free text. Report list/details reads log report.viewed, PDF/XLSX log report.generated. Web print clicks log report.print_requested; not evidence of physical print completion.


## v1.2.0 units and password contract

GET /api/v1/options returns the revised `units` (22 exam units) and new `user_units` (27 staff units). Exam type display labels and unit_labels stay English even under Accept-Language: bn. Existing machine type codes are unchanged. Admin POST/PUT /api/v1/users requires a valid staff `unit`; JSON responses expose unit. Existing users receive nullable unit until an administrator assigns one. PUT /api/v1/profile prohibits unit assignment, independent of preferred_locale. Minimum new password length is 8; mixed case, numbers and symbols remain required. All permissions remain role-based. See UPDATE-v1.2.1-UI-UNITS-FIELDS-07-10-2026.md for the exact option sets.


## v1.2.1 schedule form revisions

Create/update schedules requires post_name (Unicode string, max 200). Optional ministry (Unicode string, max 200) represents Ministry/Organization. reference remains the optional Post Code key. candidate_count is optional nullable integer, range 0..10000000; blank/omitted create stores null, numeric zero stays zero. notes represents optional Notes/Remarks. title is optional legacy data; no web title input remains, and omitted titles are derived internally. Historical post_name/ministry values remain null until edited. New status code proposed; status labels remain English. GET schedule summary returns exams, units, exam_types and grades; the last three are distinct counts over all matching records, excluding null grades. XLSX adds Ministry/Organization in column P, Post Name in D, Post Code in E. Advertisement columns F/G remain unchanged. New fields appear in audit snapshots.


## v1.2.2 English fields and audit IP filter

Role/unit/status display labels stay English under any Accept-Language; designation names remain stored text. Administrator-only GET /api/v1/audit-logs accepts optional ip_address (valid IPv4/IPv6, exact comparison) in addition to existing from/to/actor_id/action/page filters. Audit JSON already contains actor_id, actor_name, action, created_at, ip_address, channel, subject and details. Anonymous identity and non-network console IP may be null; no fabricated values are substituted.


## v1.2.3 conditional count / organization requirements

ministry (Ministry/Organization) is required on schedule create/update, Unicode string max 200. For all non-viva types, center_count is optional nullable integer (0..100000); board_count must be absent/null. For viva, board_count is required integer (0..100000); center_count must be absent/null. Existing nullable historical organization values remain readable; editing requires filling ministry. Other required fields: unit, exam_type, post_name, exam_date, status. Web and API share the same rules; no API role or audit contract changed.


## v1.3.0 post grade

Schedule create/update accepts optional post_grade: integer/null, 1..65535; Bengali numeric digits are normalized. Responses expose an integer/null. Blank edit clears the value. Existing records retain null until assigned. The grade is included in audit change snapshots, print/PDF and XLSX column Q; earlier A..P columns remain unchanged. CLI locale is English independently of the HTTP/API language preference.


## Schedule list scope (v1.4.0)

`GET /api/v1/schedules` defaults to all dates with `exam_date DESC, id DESC`, 25 records per page. `scope=week` selects today through today + 6 days in Asia/Dhaka, ordered chronologically. A supplied `date` overrides `from`/`to`; a supplied range overrides the scope's default dates. One supplied range endpoint selects that date. Longer ranges are allowed; export row limits still apply. `scope=all` is the default for exports too. Pass the same filters/scope when exporting to keep the list and report consistent.

API numeric values and stored strings remain unchanged by language selection. Web/PDF/print format displayed schedule digits in Bengali when locale is `bn`; English leaves stored text unchanged. XLSX preserves raw values and numeric cells. Search matches Bengali and ASCII digit equivalents in schedule text fields.


## Unified exam types and summary (v1.5.0)

The only accepted exam_type codes are preliminary, written, viva, departmental and senior_scale. Their labels are Preliminary (MCQ Type), Written, Viva, Departmental and Senior Scale, always English. /options returns these five choices. The data migration maps historical NC/BCS Preliminary/Written/Viva records to their corresponding unified code, including soft-deleted rows. Old nc_* and bcs_* request/filter codes are no longer accepted; update API callers to the unified codes. Audit history preserves original codes.

The summary shape is {exams, units, exam_types, grades}. Exams counts matching records; units, exam_types and grades count distinct matching values across all pages. Missing post_grade is excluded. Combined candidate/center/board counts and candidates_unspecified have been removed from the list summary. Each individual schedule retains its existing counts.


## Unit display and cards (v1.5.1)

The web Unit heading is localized as ইউনিট in Bengali. Unit values and API unit_labels are always the raw configured English strings. Web cards show only exams and distinct units; the v1.5.0 API summary retains exam_types/grades for compatibility.


## v1.6.0 vacancies, Viva structure and date-wise display

This section supersedes earlier export column positions and Viva time rules. Optional `vacant_posts`: integer/null, 0..10000000; Bengali digits normalize to ASCII. Blank or omitted full update clears it. Optional Viva-only `board_structure`: null or up to 50 objects with exactly `candidates_per_board` (1..10000000) and `boards` (1..100000), both integers. Entirely blank rows are ignored. Aggregates must not exceed 100000 boards or 10000000 candidates.

```json
{
  "post_name": "Assistant Director",
  "post_grade": 9,
  "vacant_posts": 10,
  "ministry": "Bangladesh Public Service Commission",
  "unit": "Unit 01",
  "exam_type": "viva",
  "exam_date": "2026-10-12",
  "start_time": "10:00",
  "status": "scheduled",
  "board_structure": [
    {"candidates_per_board": 15, "boards": 3},
    {"candidates_per_board": 12, "boards": 4}
  ]
}
```

This example derives `board_count=7` and `candidate_count=93`. Explicit totals override derived values independently; blank/omitted totals derive from a valid nonempty structure. Without structure, Viva still requires board_count. Viva rejects nonempty end_time; omit it or send null. Non-Viva rejects nonempty board_structure; switching to non-Viva clears saved structure/boards. Response fields include vacant_posts and board_structure; structure row numeric values can be numeric strings after input normalization. Creation responses now include the database-generated version; use the latest version for updates. Raw API times remain separate start_time/end_time fields for compatibility.

`display=flat` is default. `display=grouped` opts into date-wise rows and paginates 25 distinct dates rather than 25 exams. The paginator data changes only when explicitly opted in:

```json
{"schedules": {"total": 1, "per_page": 25, "current_page": 1, "data": [
  {"exam_date": "2026-10-12", "exams": ["schedule objects for this date"]}
]}}
```

All exams on a date remain together; summary.exams still counts exams, whereas grouped schedules.total counts dates. Pass the same scope/date/range/search/type/unit/status/display to exports. Report audit details include display and pagination unit.

Current XLSX columns A..R: Serial, Date, Exam Type, Post Name, Post Code, Advertisement Number, Advertisement Year, Unit, Time, Candidates, Centers, Boards, Status, Notes/Remarks, Ministry/Organization, Post Grade, Number of Vacant Posts, Board Structure. Date cells merge in grouped mode. Time is a single display string (`start - end`, or Start only for Viva). Numeric counts/grade/vacancies remain numeric cells; Time/Structure are locale-formatted display strings.


## v1.6.1 presentation refinements

Grouped web/print/PDF/XLSX display now has Date first, then Serial starting at 1 for each date. Grouped XLSX Date is column A (merged), Serial is column B; C..R remain unchanged. Flat XLSX retains Serial A and Date B with continuous serial. Raw API data/pagination are unchanged. Localized presentation times preserve AM/PM in English; Bengali digits and date words continue to localize.


## v1.6.2 compact exports

Raw API schedule records/filters/permissions are unchanged. XLSX supersedes earlier column maps: A/B are Serial/Date in flat mode and Date/Serial in grouped mode. C Unit / Exam Type (two lines), D Post Name, E Post Code, F Advertisement Number, G Advertisement Year, H Time, I Candidates, J Centers, K Boards, L Status, M Notes/Remarks, N Ministry/Organization, O Post Grade, P Number of Vacant Posts. No separate Unit or Board Structure column remains. For Viva with structure, K contains one structure per line then the saved Total Boards; without structure K retains a numeric board count. Other numeric counts/grades/vacancies remain numeric. XLSX row 4 repeats on printed workbook pages. PDF/print tables use the same compact Unit/type and Boards presentation, repeated PDF table headers, page numbers and English credits.


## v1.6.3 export styling

XLSX column C is rich text: bold blue Unit, separator line, bold green exam type. Column positions and raw API payloads are unchanged.
