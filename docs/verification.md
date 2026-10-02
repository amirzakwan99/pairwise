# MVP verification

The implementation followed the dependency stages in the specification: runtime/package foundation, migrations and calculation tests, authentication/group permissions, expense/read APIs, React UI, then acceptance and documentation. `03-shared-contracts.md` owns interfaces and corrected examples; future features remain deferred.

## Coverage

Backend unit checks cover the corrected three-expense fixture, one payer, two people, custom shares, deterministic remainder allocation, decimal precision, opposing and repeated debts, full cancellation, excluded payer, zero shares, soft deletion, totals above one expense's database precision, and cycles that must retain all payments. Calculation tests require no HTTP requests.

Feature checks exercise stateful registration/login/me/logout, password updates, validation and throttling; creator-only permissions, guest contact emails and legacy-member denial; creator removal/re-addition; group-scoped member and expense IDs; equal and custom expense persistence; atomic replacement and failed-update preservation; denial of non-creator access even for legacy expense authors and payers; former-member debt retention and rejection on new forms; response contracts; sorting and stable ties; and group deletion including soft-deleted expenses.

Browser acceptance exercises real sessions and CSRF, name-only participants without registration, optional contact emails, atomic group creation with initial member names, equal/custom expenses, validation feedback, details explaining debt, editing/deletion and refreshed balances, former-participant debt retention and denial of non-creator access, password changes/logout, loading/error/retry/empty states, and layouts at 320, 390, 768 and 1440 pixels. Screenshots and failure traces are ignored generated artifacts in `frontend/test-results/`.

## Checks completed on 2026-10-02

| Check | Result |
| --- | --- |
| PHPUnit, SQLite in memory | 52 tests, 364 assertions passed |
| PHPUnit, MySQL 8.0.30 isolated test database | 52 tests, 364 assertions passed |
| Laravel Pint | Passed |
| Composer schema/lock validation | Passed with `--strict` |
| Composer locked installation | `composer install` succeeded without dependency changes |
| Composer platform requirements | Passed on PHP 8.3.21 with required extensions |
| MySQL migrations and development seed | Passed |
| Laravel production cache generation and clearing | Passed |
| Frontend clean install | `npm ci` succeeded from the committed lockfile |
| Frontend dependency tree | No unmet peer dependencies (`npm ls --depth=0`) |
| TypeScript and production build | Passed; generated deployable `frontend/dist/` |
| Browser acceptance | 3 Chromium tests passed with real Laravel/MySQL sessions |
| Responsive checks | No page overflow at 320, 390, 768 and 1440 pixels; mobile/desktop screenshots inspected |
| Frontend/backend connectivity | `/api/health` passed directly and through the Vite proxy |

## Verified environment

64-bit PHP 8.3.21, Composer 2.10.2, MySQL 8.0.30, Node 22.23.3 and npm 10.9.9. PHP extensions were enabled using ignored project-local configuration. Node and Chromium are also project-local; no global runtime change was required. MySQL verification uses a separate `pairwise_test` database; development/browser flows use `pairwise` on port 3307.

Exact dependencies are in the committed lockfiles. Setup/build/run/deploy commands are in the root README. Nginx/PHP-FPM/TLS configuration is supplied for deployment but cannot be executed on this Windows host; verify it on the intended Linux server.
