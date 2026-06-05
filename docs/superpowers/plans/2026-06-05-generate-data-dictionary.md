# Data Dictionary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate a comprehensive Markdown Data Dictionary for the ClearTrack project based on current migrations and models.

**Architecture:** A single documentation file (`DATA_DICTIONARY.md`) containing tables for each database entity with Field Name, Data Type, and Description columns.

**Tech Stack:** Markdown.

---

### Task 1: Initialize Data Dictionary File

**Files:**

- Create: `DATA_DICTIONARY.md`

- [ ] **Step 1: Create the file with introduction and Table of Contents**

```markdown
# ClearTrack Data Dictionary

This document provides a detailed overview of the database schema for the ClearTrack system.

## Table of Contents

- [Users](#users)
- [Departments](#departments)
- [Events](#events)
- [Attendances](#attendances)
- [Surveys](#surveys)
- [Survey Questions](#survey-questions)
- [Survey Responses](#survey-responses)
- [Fines](#fines)
- [Clearance Periods](#clearance-periods)
- [Core Laravel Tables](#core-laravel-tables)
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: initialize DATA_DICTIONARY.md"
```

---

### Task 2: Document `users` Table

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add the users table section**

```markdown
## Users

Stores information for students, officers, and administrators.

| Field Name     | Data Type   | Description                                                 |
| :------------- | :---------- | :---------------------------------------------------------- |
| id             | bigint      | Primary key. Unique identifier for the user.                |
| name           | string      | Full name of the user.                                      |
| username       | string      | Unique login identifier for the user.                       |
| password       | string      | Hashed authentication password.                             |
| department_id  | bigint (FK) | Foreign key to `departments.id`. Nullable for admins.       |
| rfid_uid       | string      | Unique RFID tag identifier for attendance tracking.         |
| year_level     | string      | Student's current academic year level (e.g., "1st Year").   |
| is_cleared     | boolean     | Manual override flag set by officers for student clearance. |
| remember_token | string      | Token used for "remember me" authentication.                |
| created_at     | timestamp   | Record creation time.                                       |
| updated_at     | timestamp   | Last record update time.                                    |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document users table in data dictionary"
```

---

### Task 3: Document `departments` Table

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add the departments table section**

```markdown
## Departments

Academic or administrative units within the institution.

| Field Name | Data Type | Description                                                   |
| :--------- | :-------- | :------------------------------------------------------------ |
| id         | bigint    | Primary key.                                                  |
| name       | string    | Full name of the department.                                  |
| code       | string    | Unique short code representing the department (e.g., "BSCS"). |
| created_at | timestamp | Record creation time.                                         |
| updated_at | timestamp | Last record update time.                                      |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document departments table in data dictionary"
```

---

### Task 4: Document `events` Table

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add the events table section**

```markdown
## Events

School events that students are expected to attend.

| Field Name      | Data Type   | Description                                                        |
| :-------------- | :---------- | :----------------------------------------------------------------- |
| id              | bigint      | Primary key.                                                       |
| department_id   | bigint (FK) | Foreign key to `departments.id`. The department hosting the event. |
| name            | string      | Descriptive name of the event.                                     |
| start_time      | datetime    | Scheduled start time of the event.                                 |
| end_time        | datetime    | Scheduled end time of the event.                                   |
| requires_survey | boolean     | Indicates if students must complete a survey for this event.       |
| penalty_points  | integer     | Points deducted for unexcused absence from this event.             |
| created_at      | timestamp   | Record creation time.                                              |
| updated_at      | timestamp   | Last record update time.                                           |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document events table in data dictionary"
```

---

### Task 5: Document `attendances` Table

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add the attendances table section**

```markdown
## Attendances

Logs of student presence and verification at events.

| Field Name     | Data Type   | Description                                                         |
| :------------- | :---------- | :------------------------------------------------------------------ |
| id             | bigint      | Primary key.                                                        |
| event_id       | bigint (FK) | Foreign key to `events.id`.                                         |
| user_id        | bigint (FK) | Foreign key to `users.id`. The student attending.                   |
| status         | enum        | Attendance status: `present`, `absent`, `pending_verification`.     |
| photo_path     | string      | File path to the uploaded verification selfie (nullable).           |
| verified_by_id | bigint (FK) | Foreign key to `users.id`. The officer who verified the attendance. |
| created_at     | timestamp   | Record creation time.                                               |
| updated_at     | timestamp   | Last record update time.                                            |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document attendances table in data dictionary"
```

---

### Task 6: Document `surveys`, `survey_questions`, and `survey_responses`

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add the survey-related tables**

```markdown
## Surveys

Evaluation forms linked to specific events.

| Field Name  | Data Type   | Description                                    |
| :---------- | :---------- | :--------------------------------------------- |
| id          | bigint      | Primary key.                                   |
| event_id    | bigint (FK) | Foreign key to `events.id`.                    |
| title       | string      | Title of the survey.                           |
| description | text        | Brief overview or instructions for the survey. |
| created_at  | timestamp   | Record creation time.                          |
| updated_at  | timestamp   | Last record update time.                       |

## Survey Questions

Individual questions within a survey.

| Field Name    | Data Type   | Description                                               |
| :------------ | :---------- | :-------------------------------------------------------- |
| id            | bigint      | Primary key.                                              |
| survey_id     | bigint (FK) | Foreign key to `surveys.id`.                              |
| question_text | text        | The content of the question.                              |
| type          | string      | Type of input expected (e.g., `text`, `multiple_choice`). |
| created_at    | timestamp   | Record creation time.                                     |
| updated_at    | timestamp   | Last record update time.                                  |

## Survey Responses

Individual answers provided by users to survey questions.

| Field Name         | Data Type   | Description                                       |
| :----------------- | :---------- | :------------------------------------------------ |
| id                 | bigint      | Primary key.                                      |
| survey_question_id | bigint (FK) | Foreign key to `survey_questions.id`.             |
| user_id            | bigint (FK) | Foreign key to `users.id`. The user who answered. |
| answer             | text        | The user's response content.                      |
| created_at         | timestamp   | Record creation time.                             |
| updated_at         | timestamp   | Last record update time.                          |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document survey tables in data dictionary"
```

---

### Task 7: Document `fines` and `clearance_periods`

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add the fines and clearance periods tables**

```markdown
## Fines

Monetary penalties issued to students based on points or violations.

| Field Name | Data Type    | Description                                                                |
| :--------- | :----------- | :------------------------------------------------------------------------- |
| id         | bigint       | Primary key.                                                               |
| user_id    | bigint (FK)  | Foreign key to `users.id`. The student fined.                              |
| event_id   | bigint (FK)  | Foreign key to `events.id` (nullable). Link to source event if applicable. |
| amount     | decimal(8,2) | Total monetary fine amount.                                                |
| status     | enum         | Payment status: `unpaid`, `paid`.                                          |
| reason     | text         | Explanation for the fine issuance.                                         |
| created_at | timestamp    | Record creation time.                                                      |
| updated_at | timestamp    | Last record update time.                                                   |

## Clearance Periods

Configuration for institutional clearance cycles.

| Field Name    | Data Type   | Description                                                       |
| :------------ | :---------- | :---------------------------------------------------------------- |
| id            | bigint      | Primary key.                                                      |
| department_id | bigint (FK) | Foreign key to `departments.id`.                                  |
| status        | enum        | Cycle status: `active`, `inactive`.                               |
| fine_tiers    | json        | Logic for point-to-fine mapping (min_points, max_points, amount). |
| activated_at  | timestamp   | Time when the clearance period was turned on.                     |
| created_at    | timestamp   | Record creation time.                                             |
| updated_at    | timestamp   | Last record update time.                                          |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document fines and clearance periods tables"
```

---

### Task 8: Document Core Laravel Tables

**Files:**

- Modify: `DATA_DICTIONARY.md`

- [ ] **Step 1: Add session and password reset tables**

```markdown
## Core Laravel Tables

System tables for framework functionality.

### sessions

Tracks active user sessions.

| Field Name    | Data Type   | Description                           |
| :------------ | :---------- | :------------------------------------ |
| id            | string      | Primary key. Session ID.              |
| user_id       | bigint (FK) | Foreign key to `users.id` (nullable). |
| ip_address    | string(45)  | User's IP address.                    |
| user_agent    | text        | Browser/Device information.           |
| payload       | longText    | Encrypted session data.               |
| last_activity | integer     | Last activity timestamp.              |

### password_reset_tokens

Stores secure tokens for password recovery.

| Field Name | Data Type | Description                         |
| :--------- | :-------- | :---------------------------------- |
| email      | string    | Primary key. Associated user email. |
| token      | string    | Hashed recovery token.              |
| created_at | timestamp | Token generation time.              |
```

- [ ] **Step 2: Commit**

```bash
git add DATA_DICTIONARY.md
git commit -m "docs: document core Laravel tables"
```
