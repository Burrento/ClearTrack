# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ClearTrack: a Laravel 13 (PHP ^8.3) app for tracking student event attendance per department, fining absent and late students per event, and clearing students once their fines are paid. Server-rendered Blade views with Tailwind v4 via Vite. Roles via `spatie/laravel-permission`. Default DB is SQLite. The README is the stock Laravel README — ignore it.

## Commands

```bash
composer setup            # install deps, copy .env, key:generate, migrate, npm build
composer dev              # serve + queue:listen + pail logs + vite, concurrently
php artisan migrate:fresh --seed   # reset DB with roles, 2 departments, demo users
composer test             # config:clear + php artisan test (in-memory SQLite)
php artisan test --filter=test_officers_can_register_students   # single test
php artisan test tests/Feature/RegistrationTest.php             # single file
vendor/bin/pint           # format PHP
php artisan cleartrack:process-fines   # mark absences for all ended, unprocessed events
```

Seeded logins (`RolesAndPermissionsSeeder`): `superadmin` / `password` (admin), `SIT_Officer` / `password` (officer), `LGC25-105680` / `Student105680` (student). Login is by `username`, not email.

## Architecture

**Roles and scoping.** Three roles: `admin`, `officer`, `student`. Routes in `routes/web.php` are grouped by `role:` middleware (aliases registered in `bootstrap/app.php`). Admins manage departments, officers and semesters. Officers manage everything for **their own department only** — controllers enforce this via `authorizeDepartment($departmentId)` on the base `Controller` (`abort(403)` on mismatch); there are no policies. Any new officer action must do the same check. Students are created by officers with username = student ID and default password from `User::defaultPassword()` (capitalized last name + the number after the ID's last dash, e.g. `Santos105680`, `Santos6578`); IDs must match `User::STUDENT_ID_PATTERN` (LGC, 2-digit year, dash-separated numbers: `LGC26-6578`, `LGC26-2-0014`). Admins also have an all-departments Students page (`AdminStudentController`: filter, edit incl. moving department and Active, and a permanent delete that cascades the student's attendance and fines; deactivating is the history-keeping option). Graduates/dropouts are deactivated (`users.is_active = false`), not deleted: they can't log in and are skipped by RFID lookup, `markAbsentees()` and the clearance list. Departments are likewise archived (`departments.archived_at`), never deleted, because deleting cascades away events and attendance. Archived departments' users can't log in (closure in `AuthController::login`'s `Auth::attempt` credentials), and `Department::active()` keeps them out of the officer form and `startSemester`.

**Semesters**: `Semester::current()` is the most recently started one (by id). The admin's "Start New Semester" (`AdminController::startSemester`) applies to all departments and can bulk-deactivate a year level. New fines carry the current `semester_id`; past semesters' fines are history (but unpaid ones still block clearance).

**Attendance → fines → clearance pipeline** (penalty points, fine tiers and clearance periods were removed in favour of this):

1. **Events and sessions**: an event is one day with 1–3 `EventSession`s (`morning`, `afternoon`, `evening`, each with `starts_at`/`ends_at`); `events.start_time`/`end_time` span them and are set by `EventController::validateEvent`. `Event::created` adds one session spanning the event's times, so `Event::create([...start_time, end_time])` (tests, old code) still works; the form then replaces it via `saveSessions`. A session with taps can't be removed. An event can be for specific year levels only (`events.year_levels`, JSON, null = all): `Event::attendees()` (used by `markAbsentees()`), `isFor()` (the scanner refuses other year levels) and `scopeForStudent()` (student pages) all honour it; the list lives in `User::YEAR_LEVELS`.
2. **Attendance** is one row per student per session (`attendances.event_session_id`, unique with `user_id`) with `time_in_at` and `time_out_at`. RFID taps only (`rfid_uid` on users); the scanner page posts JSON (`AttendanceController::logRfid`) and never reloads. Windows (constants on `EventSession`): time in from 60 min before the start (or from `event_sessions.in_opens_at` if earlier, set by a session's "Start now", `EventController::startSession`) until 30 min before the end, after the start = `late`; time out from 30 min before to 30 min after the end. Ending early (`EventController::endSession`, or `endEvent` for the whole day, which also deletes sessions not opened yet) sets `event_sessions.closed_at`: the session takes no more taps at once (no time-out window), missing time outs are fined as usual, and `Event::closesAt()` (latest session `closesAt()`) lets absences be processed straight away; the scheduled command uses `closesAt()` too. `Event::resolveTap()` decides in/out/repeat: an open out window wins for a student tapped in and not out (morning out overlaps afternoon in); an out-only tap is refused once the student tapped into a later session. `status` is `present`/`late` from the time in, or set at processing: `absent` (no taps), `missed_in` (only tapped out). Each session can turn off its time in or time out (`event_sessions.has_in`/`has_out`, ticks on the event form, at least one): an in-only session takes time ins until its end and closes then; an out-only session's tap-out row is `present`. Selfie attendance was removed; `photo_path` and `pending_verification` are leftovers.
3. **Each event sets its fines, charged per session**: `late_fine`, `missed_in_fine`, `missed_out_fine` (₱, 0 = none; the form defaults to 20/50/50). Absent (no taps) is one fine worth no time in + no time out, counting only the taps the session takes; `events.absent_fine` is a leftover, no longer used. Late and no time out can both apply. Year levels are `User::YEAR_LEVELS` (First to Fourth Year).
4. **Absence processing** (the Fines page exports students who still owe as CSV or a printable page, `FineController::export`): after the last time out closes (`Event::closesAt()`), `Event::markAbsentees()` goes through every session × active department student, marks `absent`/`missed_in`, creates one unpaid `Fine` per kind (with `event_id`, current `semester_id`, `reason` like "No time out (morning)"), sets `events.fines_processed`, and returns counts per kind. It runs from `EventController::processFines` (manual button) and the `cleartrack:process-fines` command, scheduled every 5 minutes in `routes/console.php` (needs `schedule:run` cron or `schedule:work`). It runs once per event, so fines are never double-charged. Officers can't edit a processed event; the admin's Events page (`AdminEventController`) can "Fix" one: change its sessions' `has_in`/`has_out` and fine amounts, then `Event::reprocess()` deletes its unpaid fines, resets `missed_in` statuses, runs `markAbsentees()` again under the original semester, drops new fines that match a paid one (same student + reason), and lists paid fines that no longer apply for refund.
5. **Clearance is derived, not stored**: `User::isCleared()` is true when the student has no `unpaid` fines from **any** semester (`User::hasUnpaidFines()`; whether old semesters should count is pending a client decision). Officers just record payments on the Fines page; `User::withUnpaidTotal()` adds `unpaid_total` to lists without a query per student. Fines with `event_id = NULL` are legacy clearance fines from the old tier system.

**Backups**: admins have a Backups page (`BackupController`): "Back up now" runs `mysqldump` (path in `config/cleartrack.php`, `MYSQLDUMP_PATH`) into `BACKUP_PATH` as `manual-*.sql.gz` (newest 10 kept), and lists/downloads every `*.sql.gz` there. On the server `BACKUP_PATH` is `/home/cleartrack/backups` (added to `.env` by `deploy.sh`), where cron's nightly and `deploy.sh`'s pre-deploy dumps also land. Restoring is manual (see `docs/deploy-digitalocean.md`).

**Activity log**: `App\Http\Middleware\LogActivity` (appended to the `web` group) writes an `ActivityLog` row for every successful state-changing request, plus failed logins and backup downloads: user, action label (`ACTIONS` map keyed by `Controller@method`, unmapped ones fall back to method + URL), subject (the route's model or the form's name/ID, read before the action runs), the `status` flash shown, and IP. Card taps (`logRfid`, each already an attendance row) and marking fines paid (`markAsPaid`) are skipped (`SKIP`). Admins read it on the Logs page (`AdminController::logs`). New actions are logged automatically; add a label to `ACTIONS` for a readable name.

**Surveys**: an event may `requires_survey`; `Survey` → `SurveyQuestion` → `SurveyResponse`, created by officers, answered by students.

**Models** use PHP attributes (`#[Fillable]`, `#[Hidden]`) instead of `$fillable` properties — follow that style.

**Docs**: `docs/database-erd.mmd` is a Mermaid ERD of the schema; `docs/superpowers/` holds design specs/plans.
