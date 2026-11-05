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

**Roles and scoping.** Three roles: `admin`, `officer`, `student`. Routes in `routes/web.php` are grouped by `role:` middleware (aliases registered in `bootstrap/app.php`). Admins manage departments, officers and semesters. Officers manage everything for **their own department only** — controllers enforce this via `authorizeDepartment($departmentId)` on the base `Controller` (`abort(403)` on mismatch); there are no policies. Any new officer action must do the same check. Students are created by officers with username = student ID and default password from `User::defaultPassword()` (capitalized last name + last 6 digits of the ID, e.g. `Santos105680`). Graduates/dropouts are deactivated (`users.is_active = false`), never deleted: they can't log in and are skipped by RFID lookup, `markAbsentees()` and the clearance list. Departments are likewise archived (`departments.archived_at`), never deleted, because deleting cascades away events and attendance. Archived departments' users can't log in (closure in `AuthController::login`'s `Auth::attempt` credentials), and `Department::active()` keeps them out of the officer form and `startSemester`.

**Semesters**: `Semester::current()` is the most recently started one (by id). The admin's "Start New Semester" (`AdminController::startSemester`) applies to all departments and can bulk-deactivate a year level. New fines carry the current `semester_id`; past semesters' fines are history (but unpaid ones still block clearance).

**Attendance → fines → clearance pipeline** (penalty points, fine tiers and clearance periods were removed in favour of this):

1. **Attendance** (`attendances.status`: `present`, `late` or `absent`, a plain string): officers record taps via RFID scan (`rfid_uid` on users) only. Scanning opens `Event::CHECK_IN_OPENS_MINUTES_BEFORE` (60) minutes before `start_time`; `Event::tapStatus()` makes a tap after `start_time` `late`. The first tap wins (`firstOrCreate`), so a second tap never turns `present` into `late`. Selfie attendance was removed; `photo_path` and `pending_verification` are leftovers.
2. **Each event sets its fines**: `events.absent_fine` and `events.late_fine` (₱, 0 = none).
3. **Absence processing**: after an event ends, `Event::markAbsentees()` marks every active department student without a tap as `absent`, creates one unpaid `Fine` (with `event_id`, current `semester_id`, `reason` "Absent"/"Late") per absent/late student, sets `events.fines_processed`, and returns `[absent, late]` counts. It runs from `EventController::processFines` (manual button) and the `cleartrack:process-fines` command, scheduled every 5 minutes in `routes/console.php` (needs `schedule:run` cron or `schedule:work`). It runs once per event, so fines are never double-charged.
4. **Clearance is derived, not stored**: `User::isCleared()` is true when the student has no `unpaid` fines from **any** semester (`User::hasUnpaidFines()`; whether old semesters should count is pending a client decision). Officers just record payments on the Fines page; `User::withUnpaidTotal()` adds `unpaid_total` to lists without a query per student. Fines with `event_id = NULL` are legacy clearance fines from the old tier system.

**Surveys**: an event may `requires_survey`; `Survey` → `SurveyQuestion` → `SurveyResponse`, created by officers, answered by students.

**Models** use PHP attributes (`#[Fillable]`, `#[Hidden]`) instead of `$fillable` properties — follow that style.

**Docs**: `docs/database-erd.mmd` is a Mermaid ERD of the schema; `docs/superpowers/` holds design specs/plans.
