# CargoTrace

**Global shipment visibility & tracking platform.** A control tower for logistics operators
and a friendly tracking portal for customers. Built with Laravel 12, MySQL, Tailwind CSS,
Leaflet and a Sanctum-secured REST API.

> Final project for Web Programming 2.
>
> **Live demo:** https://cargotrace-67702.containers.snapdeploy.app (demo login: `admin@cargotrace.test` / `password`)  
> Free hosting sleeps when idle: the first request may take about a minute.

## Features

- Authentication with three roles (admin, operator, customer) and server-side authorization (middleware + Laravel Policy)
- Shipments with cargo items, created in one database transaction; search, filter, sort and server-side pagination
- Admin management of carriers, locations and users
- Dashboard counters computed from the database
- Weather forecast on the shipment page (Open-Meteo, no API key), public tracking page at `/track`, and CSV export of the shipment list
- Route map per shipment (Leaflet + OpenStreetMap): origin, destination and tracking-point markers with popups, plus geocoding (place name to coordinates) for locations and tracking events
- Dashboard KPIs (on-time delivery rate, delay rate, exception rate, average delay, average exception resolution time) and charts (status, carrier, exception type, monthly trend) with Chart.js, scoped per role
- In-app notifications (bell in the top bar + Notifications page): customers are told about status changes, ETA changes and exceptions on their shipments; staff are told about new exceptions. Optional email channel with `NOTIFY_BY_EMAIL=true`
- Audit log (admin only): every important change (shipments, tracking events, exceptions, users, carriers, locations, settings) is recorded automatically with actor, old/new values and IP; passwords are never logged. Append-only. Can be disabled with `AUDIT_ENABLED=false`
- Security hardening: security headers, rate-limited login, expiring API tokens, escaped output, allow-listed filters; see `docs/SECURITY.md`
- Polished UI: dark top bar with page title, search, notification bell and profile; time-based greeting (Good morning / afternoon / evening); demo-account box on the login page controlled by `SHOW_DEMO_ACCOUNTS`
- Search and filters on the shipment list, REST API and CSV export: number/customer, status, carrier, origin, destination, delay status (low/medium/high/on time) and date range, with sorting and server-side pagination
- Responsive UI (sidebar on desktop, hamburger menu and card lists on mobile)

## Problem

Shipment information is scattered, customers cannot see where their cargo is, and delays or
exceptions are noticed too late. CargoTrace centralises shipments, tracking events, delay
detection and exception management in one place.

## Roles

| Role | What they can do |
|---|---|
| Administrator | Manage users, carriers, locations, delay rules; view everything and the audit log |
| Logistics Operator | Create and update shipments, add tracking events, manage exceptions |
| Customer | View own shipments, status, ETA, route map, timeline and notifications |

## Tech stack

PHP 8.2+, Laravel 12, MySQL-compatible database (MariaDB 10.4 in development), Eloquent,
Blade, Tailwind CSS 4 (via Vite), Vanilla JS, Laravel Sanctum, Leaflet + OpenStreetMap, Chart.js, PHPUnit.

## Local setup

```bash
git clone https://github.com/mandalika05/cargotrace.git
cd cargotrace
composer install
cp .env.example .env
php artisan key:generate
# create an empty database named "cargotrace", then set DB_* in .env
php artisan migrate --seed
npm install
npm run dev          # keep this running in one terminal
php artisan serve    # in a second terminal -> http://127.0.0.1:8000
```

Demo accounts (synthetic data, password `password`; also shown on the login page of the live demo):

| Role | Email |
|---|---|
| Administrator | `admin@cargotrace.test` |
| Logistics operator | `operator@cargotrace.test` |
| Customer | `customer@cargotrace.test` |

## Run the tests

```bash
php artisan test
```

## Deployment

Docker image (PHP 8.3 + Apache) deployed to Render with a MySQL-compatible database (TiDB Cloud Starter, free tier).
Migrations run on start and demo data is seeded only when the database is empty. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
The test suite runs on every push through GitHub Actions (`.github/workflows/tests.yml`).

## Documentation

- [Requirements and business rules](docs/REQUIREMENTS.md)
- [Database design (ERD)](docs/ERD.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Delay and exception rules](docs/DELAY_AND_EXCEPTIONS.md)
- [REST API reference](docs/API.md) and the [Postman collection](docs/postman/CargoTrace.postman_collection.json)
- [Security](docs/SECURITY.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Final QA checklist](docs/QA_CHECKLIST.md)

## Known limitations and future work

- The live demo runs on free hosting: it sleeps when idle and the database is MySQL-compatible (TiDB), not MySQL itself.
- No strict Content-Security-Policy yet (see `docs/SECURITY.md`); no two-factor login or email verification.
- Notifications are in-app (email is optional and off by default); real-time push (WebSockets) is a possible next step.
- Weather and geocoding depend on free public APIs (Open-Meteo, Nominatim); failures are handled gracefully but data may be unavailable.
- Future work: carrier API integrations, document uploads (bill of lading), multi-language UI, exportable reports.

## Attribution

Laravel, Tailwind CSS, Leaflet, OpenStreetMap, Chart.js and Laravel Sanctum are open-source
libraries used under their respective licenses. Icons follow the [Feather Icons](https://feathericons.com)
set (MIT license, Copyright Cole Bemis) and are embedded as inline SVG. Fonts: Plus Jakarta Sans and
Fraunces (SIL Open Font License), self-hosted through the `@fontsource` npm packages (no request to Google Fonts, so they also work offline). Application code is written for this project.
Parts of it were developed with AI assistance and reviewed and understood by the author.
