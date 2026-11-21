# Deployment

CargoTrace ships as a Docker image (PHP 8.3 + Apache, front-end built with Vite in a first stage).
The reference setup is free-tier: **Render** (web service) + **TiDB Cloud Starter** (MySQL-compatible database).
Any host that runs Docker and offers a MySQL-compatible database works the same way.

## Files

| File | Purpose |
|---|---|
| `Dockerfile` | Two-stage build: Node builds CSS/JS, then PHP + Apache with `composer install` (dev packages stay: the demo seeder needs Faker) |
| `docker/entrypoint.sh` | On start: listen on `$PORT`, cache config and views, migrate, seed demo data if empty |
| `render.yaml` | Render Blueprint (service definition, non-secret settings) |
| `.env.production.example` | Production settings reference (no real secrets) |
| `.github/workflows/tests.yml` | Runs the test suite on every push and pull request |

## Environment variables (set in the Render dashboard)

| Variable | Value |
|---|---|
| `APP_KEY` | output of `php artisan key:generate --show` (starts with `base64:`) |
| `APP_URL` | the public URL, e.g. `https://cargotrace.onrender.com` |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from the database provider (TiDB: Connect dialog; port `4000`) |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` (TLS to the database) |
| `SHOW_DEMO_ACCOUNTS` | `true` shows the demo accounts on the login page |
| `RUN_MIGRATIONS` | `true` runs `php artisan migrate --force` on start |
| `SEED_DEMO` | `true` seeds demo data **only if there are no users yet** (`cargotrace:seed-if-empty`) |

`APP_ENV=production` and `APP_DEBUG=false` are fixed in `render.yaml`. Secrets are never committed.

## Steps (summary)

1. Create the database (TiDB Cloud Starter) and note host, port, user, password, database name.
2. On Render: New > Blueprint > select this repository, then fill the variables marked secret.
3. Wait for the first build (several minutes). The service must answer `GET /up` (health check).
4. Open the URL and sign in with a demo account (see README).

## Operational notes

- **Free web services sleep** after about 15 minutes without traffic; the first request afterwards takes about a minute.
- The container has no persistent disk. All state lives in the database (sessions and cache use the `database` driver); the app stores no uploaded files.
- No queue worker or scheduler is needed: notifications and audit entries are written synchronously. Outbound email is optional and off by default.
- Updating: push to GitHub; Render rebuilds. Migrations run automatically; demo data is not duplicated.
- Behind Render's HTTPS proxy the app trusts `X-Forwarded-*` headers (`trustProxies` in `bootstrap/app.php`), so links, secure cookies and HSTS work.
- TiDB is MySQL-compatible, not MySQL itself. Development and the automated tests run on MySQL/MariaDB and SQLite.
