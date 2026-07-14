# AGENTS.md

## Cursor Cloud specific instructions

This repo is the **Laravel 10 (PHP 8.3) JSON REST API backend** for "Sena Digital", a
digital wedding-invitation SaaS. The Angular frontend lives in a separate repo and is
**not** present here. Almost every route lives under `/api/...` (see `routes/api.php`);
`routes/web.php` only serves a minimal Blade welcome/payment page. Standard commands are
documented in `composer.json`, `package.json`, `README.md`, and `phpunit.xml`; the notes
below only capture non-obvious, environment-specific gotchas.

### Services & how to run them
- **MariaDB** (stands in for MySQL): required for essentially all endpoints, tests, and
  migrations. It is **not** auto-started in this VM. Start it before doing anything:
  `sudo service mariadb start`. The app connects over TCP as `root` with an empty
  password to `127.0.0.1:3306` (the `root@127.0.0.1` native-password user and empty
  password are already configured).
- **Laravel API server**: `php artisan serve --host=0.0.0.0 --port=8000`. Base URL
  `http://127.0.0.1:8000/api/...`. Run it in a tmux session so it survives.
- Vite (`npm run dev`), Redis, queue workers, and the scheduler are **optional** — the
  default drivers are `file`/`sync`, so the API runs fully without them.

### Non-obvious gotchas
- Two databases are used: `laravel` (dev, per `.env` / `.env.example`) and
  `horuzt_testing` (tests, per `.env.testing` / `phpunit.xml`). Both must exist.
- The test suite is **mixed**: `RefreshDatabase` tests migrate a fresh schema, but
  `AdminWebsiteThemeConnectionTest` uses `DatabaseTransactions` and therefore requires
  `horuzt_testing` to already be **migrated AND seeded**. If that one test fails with
  `Table 'horuzt_testing.roles' doesn't exist`, run:
  `php artisan migrate --seed --force --env=testing`.
- First-time-only setup (not needed once the VM snapshot has it): copy `.env.example`
  → `.env`, set `APP_ENV=local`/`APP_DEBUG=true`, `php artisan key:generate`,
  `php artisan storage:link`, then `php artisan migrate --seed --force` for the `laravel`
  DB. The dev `APP_KEY` and seeded data persist in the snapshot.
- Lint is Laravel Pint: `./vendor/bin/pint --test`. The repo currently has **many
  pre-existing style violations** (seeders, routes, some tests) — a non-clean Pint report
  is the baseline, not something your change broke. Avoid `pint` (auto-fix) unless you
  intend to reformat unrelated files.
- Auth uses Laravel Sanctum bearer tokens. Quick end-to-end smoke test:
  `POST /api/v1/register` (email + 8-char password) → `POST /api/v1/login` → use the
  returned `access_token` as `Authorization: Bearer <token>` on `GET /api/profile`.
- `reset_clean.sh` wipes all non-admin data and restarts the server; only run it if you
  actually want a clean slate.
