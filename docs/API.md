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
  "exam_type":"bcs_written",
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

For Viva, use `nc_viva`/`bcs_viva`, omit center_count, supply board_count. Unused field may be null; a positive value is rejected. Counts are integer, >=0. Do not send version on create; include it on update/delete. Update currently expects all required schedule fields, even with PATCH.

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

Create/update schedules requires post_name (Unicode string, max 200). Optional ministry (Unicode string, max 200) represents Ministry/Organization. reference remains the optional Post Code key. candidate_count is optional nullable integer, range 0..10000000; blank/omitted create stores null, numeric zero stays zero. notes represents optional Notes/Remarks. title is optional legacy data; no web title input remains, and omitted titles are derived internally. Historical post_name/ministry values remain null until edited. New status code proposed; status labels remain English. GET schedule summary returns candidates_unspecified alongside the sum of supplied candidate counts. XLSX adds Ministry/Organization in column P, Post Name in D, Post Code in E. Advertisement columns F/G remain unchanged. New fields appear in audit snapshots.


## v1.2.2 English fields and audit IP filter

Role/unit/status display labels stay English under any Accept-Language; designation names remain stored text. Administrator-only GET /api/v1/audit-logs accepts optional ip_address (valid IPv4/IPv6, exact comparison) in addition to existing from/to/actor_id/action/page filters. Audit JSON already contains actor_id, actor_name, action, created_at, ip_address, channel, subject and details. Anonymous identity and non-network console IP may be null; no fabricated values are substituted.


## v1.2.3 conditional count / organization requirements

ministry (Ministry/Organization) is required on schedule create/update, Unicode string max 200. For all non-viva types, center_count is optional nullable integer (0..100000); board_count must be absent/null. For nc_viva and bcs_viva, board_count is required integer (0..100000); center_count must be absent/null. Existing nullable historical organization values remain readable; editing requires filling ministry. Other required fields: unit, exam_type, post_name, exam_date, status. Web and API share the same rules; no API role or audit contract changed.


## v1.3.0 post grade

Schedule create/update accepts optional post_grade: integer/null, 1..65535; Bengali numeric digits are normalized. Responses expose an integer/null. Blank edit clears the value. Existing records retain null until assigned. The grade is included in audit change snapshots, print/PDF and XLSX column Q; earlier A..P columns remain unchanged. CLI locale is English independently of the HTTP/API language preference.
