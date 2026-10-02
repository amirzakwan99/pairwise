# Architecture and dependencies

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 3: Recommended Technology Stack

Use the following stack unless there is a strong technical reason not to.

## Backend

- PHP 8.3+
- Laravel 12+
- Laravel REST API
- Laravel Sanctum for authentication
- MySQL 8+
- Composer

## Frontend

- React
- TypeScript
- Vite
- Tailwind CSS
- React Router
- Axios or native fetch
- TanStack Query if useful

## Development

- Git
- Docker optional
- Laravel migrations
- Laravel seeders
- PHPUnit/Pest for backend tests
- Vitest/React Testing Library if frontend tests are appropriate

## Deployment target

Linux server with:

- Nginx
- PHP-FPM
- MySQL

The application should be deployable as a normal Laravel + React application.

---

## Source section 4: Architecture

Use a clear separation between frontend and backend.

```text
React + TypeScript
        |
        | REST API / JSON
        |
Laravel API
        |
        |
MySQL
```

The frontend should NOT directly access the database.

All business logic related to expenses and settlement calculations should live in the backend.

The frontend is responsible for:

- UI
- Form validation for user experience
- Displaying data
- Calling API endpoints
- Client-side state management

The backend is responsible for:

- Authentication
- Authorization
- Validation
- Expense calculations
- Debt calculations
- Settlement calculations
- Data integrity

---

## Source section 30: Backend Architecture

Keep business logic out of controllers.

Prefer a structure similar to:

```text
app/
├── Http/
│   ├── Controllers/
│   └── Requests/
│
├── Models/
│
├── Services/
│   └── SettlementService.php
│
└── ...
```

Create a dedicated service for settlement calculations.

For example:

```php
SettlementService
```

Responsibilities:

- Read group expenses
- Calculate expense shares
- Build raw debts
- Pairwise-net debts
- Return settlement results

Controllers should remain thin.

---

## Source section 38: Performance

The initial application is expected to have small groups and relatively few expenses.

Do not over-engineer.

However:

- Use eager loading where appropriate.
- Avoid N+1 queries.
- Add appropriate database indexes.
- Do not calculate settlements using hundreds of unnecessary database queries.

Settlement calculation should preferably load the relevant expenses/splits efficiently and perform the calculation in memory.

---

## Source section 43: Coding Standards

Follow modern Laravel and React conventions.

Backend:

- PSR-12
- Strict typing where practical
- Form Request validation
- API Resources where useful
- Policies
- Services for business logic
- Database transactions for multi-table writes

Frontend:

- TypeScript
- Avoid `any` unless unavoidable
- Reusable components
- Clear API service layer
- Responsive Tailwind classes
- Accessible form controls
- Proper loading/error/empty states

Do not create overly complicated abstractions.

Prefer simple, maintainable code.

## Dependency compatibility procedure

The source versions are baseline requirements, not permission to independently install arbitrary latest releases. Before scaffolding, inspect the actual PHP, Composer, Node, npm and MySQL runtimes and the selected packages' official compatibility requirements. Choose one mutually supported version set and record it in the generated project's README. Preserve PHP 8.3+ and Laravel 12+ baseline unless a documented runtime constraint requires a coordinated adjustment.

Use one repository with backend/ (Laravel) and frontend/ (React/Vite). Keep business logic in backend/; use one frontend API client and centralized TypeScript interfaces matching 03-shared-contracts.md. Prefer native fetch to avoid a mandatory extra HTTP dependency; TanStack Query and Docker remain optional. Choose one backend test runner. Do not install optional libraries without a concrete need.

Commit backend/composer.lock and frontend/package-lock.json; use npm consistently. Future work must use composer install and npm ci against the lockfiles, not re-resolve or upgrade dependencies independently. Verify Composer platform requirements, clean frontend installation, TypeScript checking and production build. Configure Tailwind using the selected major version's official Vite integration; do not mix instructions from different majors. Record exact versions and required extensions; never invent compatibility guarantees. A specification can prevent conflicting choices, but final compatibility must be verified against the implementation environment.
