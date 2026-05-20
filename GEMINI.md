# ClearTrack

ClearTrack is a web application built with the Laravel 13 framework, utilizing PHP 8.3 and Tailwind CSS 4. It follows a standard Laravel MVC architecture and is designed for high performance and developer productivity.

## Project Overview

- **Framework:** Laravel 13
- **Language:** PHP 8.3
- **Frontend Styling:** Tailwind CSS 4
- **Asset Bundling:** Vite 6
- **Database Architecture:** Migrations-based (defaulting to SQLite if not configured otherwise)
- **Testing:** PHPUnit 12

## Building and Running

### Prerequisites
- PHP 8.3+
- Composer
- Node.js & npm

### Setup
To initialize the project, run:
```bash
composer run setup
```
This command will:
1. Install PHP dependencies.
2. Create a `.env` file from `.env.example`.
3. Generate an application key.
4. Run database migrations.
5. Install npm dependencies.
6. Build frontend assets.

### Development
To start the development environment (including the server, queue listener, logs, and Vite):
```bash
composer run dev
```

### Testing
To run the test suite:
```bash
composer run test
```

## Development Conventions

### Code Style
- **PHP:** Adheres to Laravel standards. [Laravel Pint](https://laravel.com/docs/pint) is used for automated code styling.
- **Frontend:** Follows Tailwind CSS 4 conventions.

### Architecture
- **Controllers:** Located in `app/Http/Controllers`.
- **Models:** Located in `app/Models`.
- **Routes:** Defined in `routes/web.php` (web) and `routes/api.php` (API, if applicable).
- **Views:** Blade templates are in `resources/views`.
- **Assets:** CSS and JS source files are in `resources/css` and `resources/js`.

### Testing Practices
- Feature tests are located in `tests/Feature`.
- Unit tests are located in `tests/Unit`.
- Always verify changes with `composer run test` before committing.

## Key Files
- `composer.json`: Defines PHP dependencies and scripts (setup, dev, test).
- `package.json`: Defines Node dependencies and Vite scripts.
- `vite.config.js`: Configuration for Vite asset bundling.
- `.env.example`: Template for environment variables.
- `artisan`: Laravel's command-line interface.
