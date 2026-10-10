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

Seeded logins (`RolesAndPermissionsSeeder`): `superadmin` / `password` (admin), `SIT_Officer` / `password` (officer), `51410` / `Dwcc51410` (student). Login is by `username`, not email.

## Architecture

**Roles and scoping.** Three roles: `admin`, `officer`, `student`. Routes in `routes/web.php` are grouped by `role:` middleware (aliases registered in `bootstrap/app.php`). Admins manage departments and officers. Officers manage everything for **their own department only** — controllers enforce this via `authorizeDepartment($departmentId)` on the base `Controller` (`abort(403)` on mismatch); there are no policies. Any new officer action must do the same check. Students are created by officers with username = student ID and default password `Dwcc{username}`.

**Attendance → points → fines → clearance pipeline:**

1. **Attendance** (`attendances.status`: `present`, `absent`, `pending_verification`, …): officers log `present` via RFID scan (`rfid_uid` on users), or students upload a selfie (stored on the `public` disk under `selfies/`) that an officer approves/rejects.
2. **Absence processing**: after an event ends, every department student without a `present` record gets an `absent` attendance and `events.fines_processed` is set. The logic lives in `Event::markAbsentees()`, called by `EventController::processFines` (manual button) and the `cleartrack:process-fines` command. The command is not scheduled in `routes/console.php`.
3. **Penalty points** are never stored per student; `User::getTotalPenaltyPoints()` sums `events.penalty_points` over the student's `absent` attendances.
4. **Clearance period** (`ClearancePeriod`, one active per department): officers define `fine_tiers` (JSON array of `{min_points, max_points|null, fine_amount, requirements}`) on the "Fines Settings" page. `ClearancePeriod::matchTier()` maps points to a tier (falls back to highest tier if points exceed all ranges).
5. **Activating** (`ClearanceController::activate`) deletes all existing clearance fines (fines with `event_id = NULL`) for the department, resets `users.is_cleared`, then creates one fine per student from their tier and auto-clears students with 0 points. Re-running recalculates from scratch; `deactivate` removes those fines. Fines with a non-null `event_id` are legacy per-event fines and are not touched.
6. **Clearance**: officers manually set `users.is_cleared`; clearing is blocked while the student has `unpaid` fines.

**Surveys**: an event may `requires_survey`; `Survey` → `SurveyQuestion` → `SurveyResponse`, created by officers, answered by students.

**Models** use PHP attributes (`#[Fillable]`, `#[Hidden]`) instead of `$fillable` properties — follow that style.

**Docs**: `docs/database-erd.mmd` is a Mermaid ERD of the schema; `docs/superpowers/` holds design specs/plans.
