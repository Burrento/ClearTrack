# Design Doc: ClearTrack Data Dictionary

**Date:** 2026-06-05
**Topic:** Data Dictionary Generation
**Status:** Draft

## Purpose
To provide a clear, structured markdown document that serves as the "Source of Truth" for the ClearTrack database schema. This helps developers and stakeholders understand the data model, field types, and business logic constraints (like penalty points and fine tiers).

## Requirements
- **Format:** Markdown (`.md`)
- **Structure:** 
  - Introduction
  - Table-of-Contents (Link to each table)
  - One section per database table containing:
    - Table name and brief description.
    - A table with columns: **Field Name**, **Data Type**, and **Description**.
- **Scope:** All tables defined in `database/migrations`, excluding core Laravel tables if preferred (but included for completeness unless instructed otherwise).

## Database Tables & Field Mapping Strategy

### 1. `users`
- **Description:** Central table for students, officers, and admins.
- **Fields:** `id`, `name`, `username`, `password`, `department_id` (FK), `rfid_uid`, `year_level`, `is_cleared`, `remember_token`, `timestamps`.

### 2. `departments`
- **Description:** Academic/Administrative units.
- **Fields:** `id`, `name`, `code` (Unique), `timestamps`.

### 3. `events`
- **Description:** Events that require attendance and potentially surveys.
- **Fields:** `id`, `department_id` (FK), `name`, `start_time`, `end_time`, `requires_survey`, `penalty_points` (renamed from fine_amount), `timestamps`.

### 4. `attendances`
- **Description:** Records of user presence at events.
- **Fields:** `id`, `event_id` (FK), `user_id` (FK), `status` (Enum: present, absent, pending_verification), `photo_path`, `verified_by_id` (FK), `timestamps`.

### 5. `surveys`
- **Description:** Feedback forms for events.
- **Fields:** `id`, `event_id` (FK), `title`, `description`, `timestamps`.

### 6. `survey_questions`
- **Description:** Questions within a survey.
- **Fields:** `id`, `survey_id` (FK), `question_text`, `type` (Default: text), `timestamps`.

### 7. `survey_responses`
- **Description:** Answers provided by users.
- **Fields:** `id`, `survey_question_id` (FK), `user_id` (FK), `answer`, `timestamps`.

### 8. `fines`
- **Description:** Monetary penalties for students.
- **Fields:** `id`, `user_id` (FK), `event_id` (FK, Nullable), `amount`, `status` (Enum: unpaid, paid), `reason`, `timestamps`.

### 9. `clearance_periods`
- **Description:** Configuration for clearance logic and fine tiers.
- **Fields:** `id`, `department_id` (FK), `status` (Enum: active, inactive), `fine_tiers` (JSON), `activated_at`, `timestamps`.

## Output File
- **Path:** `DATA_DICTIONARY.md` (Project Root)

## Success Criteria
- [ ] Every field from the latest migrations is represented.
- [ ] Data types accurately reflect migration types (e.g., `decimal(8,2)`, `foreignId`, `enum`).
- [ ] Descriptions explain relationships (FK) and constraints (Unique, Nullable).
- [ ] The markdown is valid and renders correctly.
