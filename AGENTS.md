# AGENTS.md

## Cursor Cloud specific instructions

### Project Overview

This is **saas-platform**, a Laravel 10 (PHP 8.4) multi-tenant SaaS admin panel for managing customer websites, licenses, modules, and payments (Iranian market, RTL/Persian UI).

### Services

| Service | How to start | Notes |
|---------|-------------|-------|
| MySQL 8.0 | `sudo mysqld_safe --user=mysql --skip-grant-tables &` | Listens on unix socket only (`/var/run/mysqld/mysqld.sock`); TCP disabled by `--skip-grant-tables`. Configure `DB_SOCKET=/var/run/mysqld/mysqld.sock` in `.env`. |
| Laravel dev server | `php artisan serve --host=0.0.0.0 --port=8000` | Serves the app at http://localhost:8000 |
| Vite (frontend dev) | `npm run dev` | Only needed when actively editing CSS/JS; `npm run build` suffices otherwise |

### Database

- MySQL socket: `/var/run/mysqld/mysqld.sock`
- `.env` must have `DB_SOCKET=/var/run/mysqld/mysqld.sock` and `DB_HOST=localhost` for socket-based connection (TCP is disabled when using `--skip-grant-tables`).
- Database name: `laravel` (create with `mysql -u root -e "CREATE DATABASE IF NOT EXISTS laravel ..."` via socket/sudo).
- Run migrations: `php artisan migrate --force`
- Seed: `php artisan db:seed --force`

### Key Commands

| Task | Command |
|------|---------|
| Install PHP deps | `composer install` |
| Install JS deps | `npm install` |
| Lint (code style) | `./vendor/bin/pint --test` |
| Fix lint | `./vendor/bin/pint` |
| Run tests | `php artisan test` |
| Build assets | `npm run build` |
| Dev server | `php artisan serve --host=0.0.0.0 --port=8000` |

### Gotchas

- The `composer.lock` requires PHP 8.4 (some Symfony packages need `>=8.4`). Do not use PHP 8.2 or lower.
- MySQL `--skip-grant-tables` implies `--skip-networking` in MySQL 8.0, so the server is socket-only. Laravel connects via `DB_SOCKET` env var.
- The login form requires a captcha field (simple math captcha). When testing login programmatically, use `Auth::login($user)` in tinker or tests.
- `name` field on User model is NOT used for login; the app authenticates via `mobile` + `password`.
- Existing code has Pint formatting issues (exit code 1 on `--test`); these are pre-existing and not introduced by setup.
- The `mkdir -p /var/run/mysqld && chown mysql:mysql /var/run/mysqld` step is needed before starting MySQL if the directory doesn't exist or has wrong permissions.
