# Implementation order and deployment

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 42: Development Approach

Build incrementally.

## Step 1

Set up:

```text
Laravel
MySQL
React
TypeScript
Vite
Tailwind
```

Make sure frontend and backend communicate successfully.

## Step 2

Implement:

```text
Authentication
```

## Step 3

Implement:

```text
Groups
Members
```

## Step 4

Implement:

```text
Expenses
Expense Splits
```

## Step 5

Implement:

```text
SettlementService
```

## Step 6

Write comprehensive settlement tests.

## Step 7

Build:

```text
Group Dashboard
Settlement Screen
Expense History
```

## Step 8

Improve mobile UX.

## Step 9

Defer PWA functionality until after MVP acceptance.

## Step 10

Prepare production deployment.

---

## Source section 44: README Requirements

Create a README containing:

- Project overview
- Requirements
- Installation
- Environment configuration
- Database setup
- Migration commands
- Seeder commands
- Development commands
- Test commands
- Production build commands
- Deployment instructions

Example:

```bash
composer install
npm install

cp .env.example .env

php artisan key:generate

php artisan migrate --seed

php artisan serve
npm run dev
```

Adjust commands according to the final project structure.

---

## Source section 47: Suggested First Development Task

Start by implementing the backend foundation and settlement engine before building the complete UI.

First create:

```text
Laravel project
Database migrations
Models
Factories
Seeders
SettlementService
Unit tests
REST API
```

Then build the React frontend.

Do not implement unnecessary features before the core expense → split → debt → pairwise settlement flow is working and tested.
