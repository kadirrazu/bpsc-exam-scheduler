# Current project decisions

This is the current requirements record. Earlier decisions are archived in history/PROJECT-DECISIONS-through-v1.3.0.md; superseded rules there are historical. Latest supplied reference: project-reference-27-10-2026-11-54-PM.zip (v1.7.5). Current update: v1.7.7.

## Access and identity

BPSC Exam Scheduler is staff-only, using a single database and Asia/Dhaka time. Administrator manages everything, deletion and audits; Editor creates/edits schedules but cannot delete; Viewer reads and exports. Designation and Unit are metadata, not permission scopes. No public frontend or Android application is included; authenticated API access is available.

## Language and layout

Bengali is the default web language; English can be selected. Terminal commands/output remain English. Unit labels/headings are ইউনিট in Bengali; Unit/type names are shown as entered, without automatic translation. Initially seeded names are English. Stored canonical codes remain stable when display names change. Designation, Role and Status remain English. AM/PM markers remain English in both languages (including report/footer times). Schedule display digits convert at runtime in Bengali; stored values, edit fields and raw API/XLSX values remain unchanged. Search matches Bengali/ASCII digit equivalents.

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

Eight seeded English exam types/codes: Preliminary (MCQ Type)/preliminary, Written/written, Viva/viva, Departmental/departmental, Senior Scale/senior_scale, Aptitude Test/aptitude_test, Practical Test/practical_test, Typing Test/typing_test. The database catalog is the live source; administrators may add more types or rename existing ones. The v1.5.0 data migration unifies prior NC/BCS codes, including soft-deleted records, preserves historical audits and logs each change with a version increment. Automatic rollback of that migration is blocked; restoring the prior code/database backup is required to revert it.

Seeded Exam Units: Unit 01–20, Non Cadre (Exam), Cadre (Exam). Seeded Staff Units additionally include Non Cadre (Confidential), Cadre (Confidential), IT Section, Administration Wing and Law Wing.

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


## Catalog management (v1.7.0)

Exam Management contains Exam Types and Units. Calendar is a top-level navigation item immediately after Dashboard. Only Administrator manages catalogs; Editor/Viewer cannot mutate or browse catalog administration. All active roles still receive live options for schedule/report use. Name/order edits propagate to forms, filters, search, reports, profile/user displays and API labels. Immutable string codes preserve existing records and API references; new codes are generated automatically. Units have independent for_exams/for_users usage flags; types have a Viva workflow flag. Used records (including soft-deleted schedules/users) prevent deletion and incompatible workflow/scope changes. Updates/deletes use optimistic versions; writers lock catalog parents and recheck usage before saving. Metadata changes are audited with who/action/time/IP.

Migration creates/populates the catalogs, including any historical codes found in schedules/users, without rewriting source records/versions. db:seed includes ExamTypeSeeder and UnitSeeder; seed defaults only create missing codes, preserving existing names/settings/order. The schema migration blocks automatic rollback to avoid silently losing custom catalog data; revert using the matching code/database backup. Config arrays now define seed defaults only.

## Calendar (v1.7.0)

Default month follows Asia/Dhaka. The seven-column month starts Sunday, with Friday/Saturday in its final two columns; previous/next month and a month picker are provided. Optional Unit/type filters affect monthly totals and selected-day details together. Hover or keyboard focus displays total exams and counts by type. Mobile tap/click opens that date's details below, using the exact shared dashboard table, role-gated actions, 25-exam pagination and filtered export/print links. Empty dates remain selectable. Monthly aggregation groups only date/type with COUNT for ONLY_FULL_GROUP_BY compatibility, excludes soft-deleted records and does not load every month's schedule into memory. Calendar/report views and date selections are audited; API calendar is available for future Android clients.


## Calendar layout and emphasis (v1.7.1–v1.7.2)

Calendar markup uses isolated bpsc-calendar-* classes. Tabler's .calendar-date has max-width 14.28%, which previously constrained the nested day link and broke labels into characters; the application no longer reuses that class. Day links fill the grid cell with explicit width/max-width/min-width rules. Days with matching exams use pale blue backgrounds; dates are blue, exam counts orange and type information green. Every exam date shows an explicit Exam count with a distinct numeric badge, followed by a separator and the complete, wrapping type-name/count breakdown. Empty days use muted gray. Friday/Saturday columns use warm weekend shades and red date numbers; this does not exclude their exams. Larger centered dates improve scanning. Mobile preserves readable cells with horizontal scrolling inside the calendar rather than shrinking text into narrow columns. Tooltip uses a colored gradient, separate date/total/type colors and opacity/transform transitions. JavaScript moves tooltip nodes outside the scroll container and positions them within the viewport to prevent clipping; Escape, scrolling and resizing dismiss them. Keyboard focus also shows the tooltip; reduced-motion users get no animation. Top navigation places Calendar after Dashboard, with a separate active state.


## Calendar contrast and week order (v1.7.3)

The legend includes Exam day, No exam day and Weekly holiday. Empty in-month days use neutral gray; empty weekend cells retain a subtle warm-gray tint, while exam-day gradients remain unchanged. Count labels use dark blue, numeric totals dark amber and type names/counts dark green for clearer contrast. Today has a prominent 3px blue border and pale outer ring, retaining its blue border on hover/focus. Header generation and month grid both use Sunday through Saturday. Counts, filters and API data are unchanged.


## Calendar day-section focus (v1.7.4)

The #day-schedules fragment target retains tabindex=-1 for focus/navigation. Its own focus outline is suppressed with the scoped #day-schedules:focus rule, preventing the browser's dark rectangle around the day table after fragment navigation. Descendant links/buttons retain their existing focus styles. No data, filters or table borders change.


## Header and calendar spacing (v1.7.5)

The full authenticated header name Bangladesh Public Service Commission (BPSC) is inside one strong element. Calendar's title/icon use flex alignment with gap-2; filter buttons share a wrapping flex container with gap-2 for both horizontal and vertical spacing. Existing compiled utility styles are reused.


## Schedule detail view (v1.7.7)

English display switching reads Exam-wise rows / Date-wise rows; Bengali wording remains unchanged. The details page retains its compact summary (Unit, Post Name with Ministry/Organization, Exam Type and Exam Date). Beneath it, three side-by-side cells show Exam Type, Unit and Post Code; mobile stacks those cells. The main two-column table has row labels only, with no Field/Value header row, and all values are bold.

Post Name & Grade combines the name with (Grade: X); a missing grade omits the complete parenthesized suffix. Advertisement Number & Year combines number and year; a number alone or year alone remains readable without empty parentheses, and both absent display a dash. Time uses the exact schedule timeLabel formatter shared with dashboard tables, including Start only for Viva and English AM/PM in both languages. Vacant Posts immediately precedes Candidates. Notes/Remarks and Last Updated remain the final rows. Viva keeps Board Structure and Boards; non-Viva keeps Centers.

Post Code is purple, Post Name blue, Ministry/Organization green and the grade suffix amber. Zero stays distinct from missing values. Bengali numeric/date conversion remains display-only; Unit/type names use current catalog labels without automatic translation. User text remains escaped and notes preserve line breaks. Viewer read access, role-gated controls, optimistic versions, API records and audit behavior are unchanged.
