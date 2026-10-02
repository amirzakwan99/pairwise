# Pairwise — shared group expenses

A Laravel API and React app for personal groups, built from the MVP specification in `personal-expense-debt-spec/`. Register as a creator, create a group with optional member names (added one at a time), add further participants by name with optional contact email, and record equal or custom expenses. Participants do not need accounts or login. The dashboard shows expense totals, saved shares, and who pays whom. Only the original group creator can access the group and manage its participants and expenses, including expenses paid by other people.

## Verified versions

| Component | Verified version |
| --- | --- |
| PHP / Composer | 64-bit PHP 8.3.21 / Composer 2.10.2 |
| Database | MySQL 8.0.30 |
| Backend | Laravel 12.69.3, Sanctum 4.3.3, PHPUnit 11.5.56 |
| Node / npm | Node 22.23.3 / npm 10.9.9 |
| Frontend | React 19.3.0, React Router 7.18.4, TypeScript 5.9.3 |
| Build / CSS | Vite 7.3.6, React Vite plugin 5.2.0, Tailwind + Vite plugin 4.3.3 |
| Browser tests | Playwright 1.63.0, Chromium |

Use Node 22.12+ within the 22.x line, PHP 8.3+ on a 64-bit runtime, and MySQL 8+. Node 21 installed originally in this workspace does not satisfy the selected Vite/plugin engines. The selected package engines and peer dependencies were checked, together with Composer platform requirements, MySQL migrations and frontend build. Exact resolved dependencies are committed in `backend/composer.lock` and `frontend/package-lock.json`. Use **composer install** and **npm ci**, never independently upgrade packages as part of setup.

Required PHP extensions: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, **pdo_mysql**, Session, Tokenizer, XML; ZIP supports Composer archive installation. Tests using SQLite additionally need **pdo_sqlite** and **sqlite3**, and PHPUnit needs XMLWriter. Check `php -m` and `composer check-platform-reqs`; enable missing extensions in the active `php.ini`. Tailwind 4 uses `@tailwindcss/vite` and `@import "tailwindcss"`, matching its [official Vite setup](https://tailwindcss.com/docs/installation/using-vite). Runtime requirements were checked against [Laravel deployment](https://laravel.com/docs/12.x/deployment) and [Vite](https://vite.dev/guide/).

## Local installation

Create a dedicated MySQL database and user; do not point tests at a database with valuable data:

```sql
CREATE DATABASE pairwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pairwise'@'localhost' IDENTIFIED BY 'choose-a-local-password';
GRANT ALL PRIVILEGES ON pairwise.* TO 'pairwise'@'localhost';
```

From the repository root:

```sh
cd backend
composer install
cp .env.example .env
# Edit DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD.
php artisan key:generate
php artisan migrate
php artisan db:seed
composer check-platform-reqs
cd ../frontend
npm ci
```

On PowerShell use `Copy-Item .env.example .env` and `npm.cmd`. The development seed creates owner `amir@example.test` (password `password123`) and name-only participants Ali and Abu, with Langkawi Trip, Dinner, Grab, Drinks and a custom-split Hotel expense. Seed only development databases. Re-running the seed skips an existing Langkawi Trip owned by Amir.

Run in two terminals:

```sh
# Terminal 1, backend/
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2, frontend/
npm run dev
```

Open **http://127.0.0.1:5173**. Vite proxies `/api` and `/sanctum` to Laravel. `/api/health` returns `{ "data": { "status": "ok" } }`, including through the proxy. Keep the same hostname in your browser and environment settings; do not switch between localhost and 127.0.0.1 in one session.

### Environment and authentication

`backend/.env.example` defaults to MySQL on port 3306, session/cache storage in MySQL, and development domains on 127.0.0.1. Set the database credentials before migrations. Never commit `.env` or change `APP_KEY` for an existing deployment.

Authentication uses [Sanctum stateful SPA sessions](https://laravel.com/docs/12.x/sanctum). The API client fetches `/sanctum/csrf-cookie` before writes, sends credentials and the decoded `X-XSRF-TOKEN` header, and never stores tokens in localStorage. Keep CSRF protection enabled. Set `SANCTUM_STATEFUL_DOMAINS` to browser hosts, including development ports but without schemes; `FRONTEND_URL` includes the scheme. Cookies are HttpOnly for the session and SameSite=Lax; XSRF-TOKEN is readable for the CSRF header. The app provides feedback for expired sessions, CSRF errors, validation errors and throttling.

### This Windows workspace

If the browser reports `ERR_CONNECTION_REFUSED`, start all prepared development services in the background from the repository root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/Start-Local.ps1
```

Then open **http://127.0.0.1:5173**. The launcher reuses running services and starts missing ones in hidden processes, with logs in `.tools/`.

Project-local ignored tools were prepared in `.tools/`: checksum-verified Node 22.23.3, Chromium, a PHP configuration enabling MySQL/SQLite drivers and disabling the pre-existing incompatible SSH extension, and an isolated MySQL data directory on **port 3307**. The current local `.env` points at that database. Global PHP/Node installations were left intact. These machine-specific tools and data are not part of a fresh clone.

To use the prepared tools in a new PowerShell terminal, run from the repository root:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
. ./scripts/Use-LocalTools.ps1
node --version
php -m
```

If the isolated database is stopped, start it in a terminal (adjust the Laragon location if necessary):

```powershell
& C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqld.exe --no-defaults --basedir=C:/laragon/bin/mysql/mysql-8.0.30-winx64 --datadir=C:/Users/azakw/Documents/PersonalProject/Splitwise/.tools/mysql-data --port=3307 --bind-address=127.0.0.1 --mysqlx=OFF --console
```

This isolated development server uses an empty-password local root account. For normal local installation and deployment, use a dedicated database account and the standard setup above. Stop the isolated server with `mysqladmin -h 127.0.0.1 -P 3307 -u root shutdown`.

### Upgrading an existing installation

Run `php artisan migrate` before using name-only participants. The additive migration makes identity email/password nullable and adds membership contact email; it preserves existing IDs, expense shares and debts. Existing registered member rows remain available as historical participants, but only the original group creator can access each group. New participants are always group-local guests, even if their contact email matches a registered account. Guest credentials remain null and cannot authenticate. Registration still requires email and password for creators.

Once guest identities exist, rolling back this migration would require non-null credentials and is intentionally refused. Restore a pre-migration backup if a schema rollback is required; do not delete guests or invent login credentials to force it.

## Checks

```sh
cd backend
composer validate --strict
composer check-platform-reqs
php artisan test
php vendor/laravel/pint/builds/pint --test

cd ../frontend
npm ci
npm run typecheck
npm run build
npm exec playwright install chromium
# Start both development servers and seed the development database first.
npm run test:e2e
```

Backend tests default to an in-memory SQLite database. For MySQL integration checks, create a separate `pairwise_test` database, grant the test account access, and override the process environment:

```sh
DB_CONNECTION=mysql DB_DATABASE=pairwise_test php artisan test
```

PowerShell equivalent: `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='pairwise_test'; php artisan test`. Unset those variables afterwards with `Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE`. **RefreshDatabase rebuilds the selected test database.** The app database should remain separate. Browser tests create uniquely named disposable accounts/groups in the development database and require the documented seed accounts; do not run them against production. Screenshots and failure traces are written to `frontend/test-results/`.

## Money and debt invariants

All IDs are opaque ULID strings. MYR values are decimal strings on the wire and DECIMAL(12,2) in MySQL. PHP parses them exactly to integer cents on a 64-bit runtime. Negative amounts, excessive precision and numeric JSON money values are rejected; custom shares can be zero. Equal shares sort participant IDs lexicographically, divide with integer arithmetic and assign remaining cents to the first IDs. The backend persists all shares.

SettlementService accumulates each non-payer share as participant → payer and nets opposing debts **only within that pair**. It never reroutes a debt through a third person. A three-person cycle remains three payments even if everyone has zero net balance. Summaries retain payable and receivable totals separately. Settlements are derived on read, so editing or soft-deleting expenses cannot leave stored stale balances.

The corrected fixture (Dinner 120 by Amir, Grab 60 by Ali, Drinks 30 by Abu, equal shares) gives Ali → Amir **20.00**, Abu → Amir **30.00**, Abu → Ali **10.00**. Hotel is additional seed data and changes those amounts. Tests keep the corrected fixture separate from the seed.

Creator removal marks participants inactive while preserving historical expenses, shares and debts. Only active participants can be selected on new or replacement expenses. Adding a removed name reactivates the same identity and membership. Duplicate active names are rejected case-insensitively within each group. The creator cannot be removed; ownership transfer is deferred. Participants have no self-service leave flow. A group deletion cascades its complete history. Group locks serialize membership and expense writes, and full-form PATCH replaces shares atomically. Expense access and edit rights belong exclusively to the original group creator, independently of expense authorship or payer. Optional contact emails never link accounts or confer access.

See [the shared contract](personal-expense-debt-spec/03-shared-contracts.md) and [API route map](personal-expense-debt-spec/08-api.md). API resources use `{data: ...}`, except settlements use `{currency, settlements}`. Expense lists support `sort=date|amount|description` and `direction=asc|desc`, with ID tie-breaking.

## Production deployment

Deploy on Linux with Nginx, PHP-FPM 8.3+ and MySQL 8+. Serve the React build and Laravel endpoints from **one HTTPS origin**. The examples in `deploy/` route `/api/`, `/sanctum/` and `/up` to `backend/public/index.php`, and other routes to the React SPA. Replace example.com, certificate paths, `/srv/pairwise`, and the PHP-FPM socket with your actual installation. Validate Nginx configuration before reload. Never expose the repository or backend root as the public web root.

1. Install PHP extensions and provision a dedicated database/user. Deploy the repository to `/srv/pairwise` or adjust the paths.
2. In `backend/`, run `composer install --no-dev --prefer-dist --optimize-autoloader`. Create `.env` securely and set the production database credentials and these settings:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://example.com
   FRONTEND_URL=https://example.com
   SANCTUM_STATEFUL_DOMAINS=example.com
   SESSION_DOMAIN=null
   SESSION_SECURE_COOKIE=true
   SESSION_HTTP_ONLY=true
   SESSION_SAME_SITE=lax
   SESSION_DRIVER=database
   CACHE_STORE=database
   ```

3. Generate `APP_KEY` only on first deployment. Give PHP-FPM write access to `backend/storage` and `backend/bootstrap/cache`, without making application source writable. Back up an existing database before applying migrations.
4. Run `php artisan migrate --force`, `composer check-platform-reqs`, and `php artisan optimize`. **Do not seed production.**
5. In `frontend/`, run `npm ci` and `npm run build` using Node 22. Install/build with dev dependencies present; the output in `dist/` is the deployable static artifact. Node is not needed to serve it.
6. Install TLS certificates, configure Nginx using `deploy/nginx.conf` and its FastCGI include, run `nginx -t`, and reload Nginx/PHP-FPM. If TLS terminates at a reverse proxy, configure Laravel trusted proxies for that deployment.
7. Check `/up`, `/api/health`, deep SPA links and register/login/logout. Verify secure cookies and a real expense mutation. Logs are in `backend/storage/logs`; keep `APP_DEBUG=false`. Back up MySQL and retain the environment/APP_KEY securely.

For updates, install from lockfiles, build a new frontend artifact, run the required checks, back up the database, apply migrations and refresh Laravel caches. No worker, scheduler or optional external service is required for this MVP. See [verification notes](docs/verification.md) for checks actually run. Production Nginx/TLS deployment must be verified on the target Linux server.

PWA, payments/mark-paid, settlement history, invitation links, percentage splits, conversion and the rest of `12-future-features.md` remain deferred.
