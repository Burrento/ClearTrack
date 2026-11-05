# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ClearTrack: a Laravel 13 (PHP ^8.3) app for tracking student event attendance per department, turning absences into penalty points, and gating student clearance on fines. Server-rendered Blade views with Tailwind v4 via Vite. Roles via `spatie/laravel-permission`. Default DB is SQLite. The README is the stock Laravel README — ignore it.

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

**Semesters**: `Semester::current()` is the most recently started one (by id). The admin's "Start New Semester" (`AdminController::startSemester`) applies to all departments: copies each department's latest `fine_tiers` into a new inactive `ClearancePeriod`, un-clears every student, and can bulk-deactivate a year level. Clearance periods and clearance fines carry `semester_id`; past semesters' rows are history.

**Attendance → points → fines → clearance pipeline:**

1. **Attendance** (`attendances.status`: `present` or `absent`): officers log `present` via RFID scan (`rfid_uid` on users) only. Selfie attendance was removed; the `photo_path` column and `pending_verification` enum value are leftovers with no code using them.
2. **Absence processing**: after an event ends, every department student without a `present` record gets an `absent` attendance and `events.fines_processed` is set. The logic lives in `Event::markAbsentees()`, called by `EventController::processFines` (manual button) and the `cleartrack:process-fines` command. The command is scheduled every 5 minutes in `routes/console.php` (needs `schedule:run` cron or `schedule:work`).
3. **Penalty points** are never stored per student; `User::getTotalPenaltyPoints()` sums `events.penalty_points` over the student's `absent` attendances for events starting on/after the current semester's `starts_at`.
4. **Clearance period** (`ClearancePeriod`, one per department per semester): officers define `fine_tiers` (JSON array of `{min_points, max_points|null, fine_amount, requirements}`) on the "Fines Settings" page. `ClearancePeriod::matchTier()` maps points to a tier (falls back to highest tier if points exceed all ranges).
5. **Activating** (`ClearanceController::activate`) deletes this semester's **unpaid** clearance fines (fines with `event_id = NULL`) for the department, resets `users.is_cleared`, then creates one fine per student from their tier and auto-clears students with 0 points. Students who already **paid** this semester's clearance fine are skipped entirely, so paid records are never deleted. `deactivate` removes the unpaid ones. Fines with a non-null `event_id` are legacy per-event fines and are not touched.
6. **Clearance**: officers manually set `users.is_cleared`; clearing is blocked while the student has `unpaid` fines from **any** semester (`User::hasUnpaidFines()`; whether old semesters should count is pending a client decision).

**Surveys**: an event may `requires_survey`; `Survey` → `SurveyQuestion` → `SurveyResponse`, created by officers, answered by students.

**Models** use PHP attributes (`#[Fillable]`, `#[Hidden]`) instead of `$fillable` properties — follow that style.

**Docs**: `docs/database-erd.mmd` is a Mermaid ERD of the schema; `docs/superpowers/` holds design specs/plans.
