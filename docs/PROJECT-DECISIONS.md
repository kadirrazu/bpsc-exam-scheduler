# Current project decisions

This is the current requirements record. Earlier decisions are archived in history/PROJECT-DECISIONS-through-v1.3.0.md; superseded rules there are historical. Latest supplied reference: project-reference-09-10-2026-07-05-PM.zip, followed by the delivered patches.

## Access and identity

BPSC Exam Scheduler is staff-only, using a single database and Asia/Dhaka time. Administrator manages everything, deletion and audits; Editor creates/edits schedules but cannot delete; Viewer reads and exports. Designation and Unit are metadata, not permission scopes. No public frontend or Android application is included; authenticated API access is available.

## Language and layout

Bengali is the default web language; English can be selected. Terminal commands/output remain English. Unit labels/headings are ইউনিট in Bengali; Unit values are always the stored English strings in both languages. Designation, Role and Status remain English. AM/PM markers remain English in both languages (including report/footer times). Schedule display digits convert at runtime in Bengali; stored values, edit fields and raw API/XLSX values remain unchanged. Search matches Bengali/ASCII digit equivalents.

Header/footer backgrounds span the available page width with zero root side margins; content and navigation stay inside the centered container. Authenticated header commission name is Bangladesh Public Service Commission (BPSC). Footer text stays English and its version follows config/scheduler.php. Personal menu shows name, designation and Unit; Role is on My Profile. Mobile layout remains responsive.

## Schedules and counts

Dashboard defaults to today through today + 6 days, chronological order. Schedules defaults to every non-deleted entry, exam date descending then ID descending. Single date/range/type/Unit/Status/text filters apply; single date overrides the range. One range endpoint selects that day. Pagination uses the shared style: 25 exams per page in the default view; 25 distinct dates per page in date-wise view, keeping every matching exam on a date together. Exports use the selected scope/filter/order, capped at 10,000 rows; date ranges have no 366-day cap.

Only Exams and Units cards appear on both pages. They count all matching rows and distinct matching Units across pages. No combined candidate/center/board card is displayed. The API retains its additional distinct exam_types/grades summary keys for compatibility; these do not appear as cards. Counts per individual exam remain available.

Table headings are bold. Unit / Exam Type shares one column, showing the raw Unit on the first line and the English exam type on the next. Start/End share a Time column with a spaced hyphen; Viva shows Start only. Headers and values are horizontally/vertically centered except Post Name and Action, which retain left alignment.

Default display retains one row per exam. An optional date-wise display puts Date first, then a per-date serial starting at 1, and merges each date cell across that date’s exam subrows. Default flat display retains Serial then Date and its continuous serial. The selected display applies to dashboard/list, print, PDF and XLSX; filters/order and existing access controls remain the same. Grouped XLSX merges only Date cells and omits AutoFilter because of the merged cells. Very large PDF date groups may move to a fresh page and leave whitespace.

## Entry rules

Order: Exam Type, Unit, Post Code, Post Name, Post Grade, Number of Vacant Posts, Ministry/Organization, Advertisement Number, Advertisement Year, Exam Date, Start Time, End Time, Candidates, conditional Centers/Boards, Status, Notes/Remarks. Mandatory: Unit, Exam Type, Post Name, Ministry/Organization, Exam Date, Status. Viva requires Boards; non-viva Centers is optional. Other fields are optional. Grade is a positive integer; Vacant Posts is optional, integer 0..10000000; missing counts/grade/vacancies remain null. Internal legacy title is not a form field.

Desktop form row 1: Exam Type, Unit, Post Code. Row 2: Post Name, Post Grade, Number of Vacant Posts. Mobile stacks these fields in the same reading order. Viva Board Structure precedes Candidates/Boards; other lower fields retain their layout.

Viva has Start Time only; End Time is hidden and prohibited on writes. Optional repeatable Board Structure stores candidates_per_board × boards rows (up to 50; both positive integers). Totals automatically fill Candidates/Boards while editing a complete valid structure; both totals remain manually editable. Saved manual overrides are not recalculated on page load. If API totals are omitted/blank, the server derives them; explicitly supplied totals remain overrides. Aggregate limits: 100000 boards and 10000000 candidates. Empty structure remains null; non-Viva cannot store structure. Structure and vacancies appear in details, reports and audit snapshots. In schedule/report tables, every board structure row appears on its own line inside the Boards cell, followed by the manually saved Total Boards on a separate bold line. Structure is no longer duplicated beneath Post Name. Legacy Viva end times are untouched by the migration, hidden in reports and cleared when that record is saved.

Five English exam types/codes: Preliminary (MCQ Type)/preliminary, Written/written, Viva/viva, Departmental/departmental, Senior Scale/senior_scale. The v1.5.0 data migration unifies prior NC/BCS codes, including soft-deleted records, preserves historical audits and logs each change with a version increment. Automatic rollback of that migration is blocked; restoring the prior code/database backup is required to revert it.

Exam Units: Unit 01–20, Non Cadre (Exam), Cadre (Exam). Staff Units additionally include Non Cadre (Confidential), Cadre (Confidential), IT Section, Administration Wing and Law Wing.

Statuses: Proposed, Scheduled, Completed, Postponed, Cancelled, with distinct table/report backgrounds. Labels are English.

## Security, audit and documentation

Passwords require 8+ characters with upper/lowercase letters, digit and symbol. Authorization is enforced server-side. Schedule edits/deletes use optimistic versions; deletion is soft and requires confirmation. Audit records preserve who/action/time/source IP/channel and report access/generation/print requests. Audit UI/API is administrator-only; secrets are excluded. Console migration actions have no network IP. Proxy trust is configured explicitly during deployment.

Sanctum API tokens are hashed, expire and are revocable. See SECURITY-AND-DEPLOYMENT.md and API.md for operational contracts.

The root README is stable: only a short overview, installation and development commands. It has no changing release version or patch notes. Subsequent decisions, updates and verification notes belong under docs/. The footer version still changes on each release.


## Print/PDF credit and pagination (v1.6.2)

Table headings repeat on each PDF page using thead. PDF page numbers appear in the footer. Print/PDF credits remain literal English, 7pt and 65% opacity: Software Developed By: IT Section, BPSC; only IT Section, BPSC is bold. mPDF does not apply CSS text opacity, so PDF credits are drawn with SetAlpha(0.65) on each page. Printed timestamps remain present. Web-print uses a fixed footer with CSS opacity. Native browser page numbering remains controlled by its print settings.

XLSX now uses 16 compact columns A..P: Serial/Date, Date/Serial (depending on display), Unit / Exam Type, Post Name, Post Code, Advertisement Number, Advertisement Year, Time, Candidates, Centers, Boards, Status, Notes/Remarks, Ministry/Organization, Post Grade, Vacant Posts. Unit/type and board structures use cell line breaks; the Boards cell includes its total. Boards without structure remain numeric. Header row 4 repeats when printing the workbook.


## Unit/type emphasis (v1.6.3)

The combined Unit / Exam Type cell uses bold Unit text in blue (#1D4ED8), a thin divider, and bold exam type text in green (#166534) below. Shared Blade markup covers web/print/PDF; XLSX rich text retains the colors/bold with a separator line between runs. Raw Unit/type values, filters and API records remain unchanged.
