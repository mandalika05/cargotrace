# CargoTrace

**Global shipment visibility & tracking platform.** A control tower for logistics operators
and a friendly tracking portal for customers. Built with Laravel 12, MySQL, Tailwind CSS,
Leaflet and a Sanctum-secured REST API.

> Status: **work in progress** (final project for Web Programming 2). This README grows with every stage.

## Features so far

- Authentication with three roles (admin, operator, customer) and server-side authorization (middleware + Laravel Policy)
- Shipments with cargo items, created in one database transaction; search, filter, sort and server-side pagination
- Admin management of carriers, locations and users
- Dashboard counters computed from the database
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

Demo accounts (local only, password `password`):
`admin@cargotrace.test`, `operator@cargotrace.test`, `customer@cargotrace.test`.

## Run the tests

```bash
php artisan test
```

## Documentation

- [Requirements and business rules](docs/REQUIREMENTS.md)
- [Database design (ERD)](docs/ERD.md)
- [Architecture](docs/ARCHITECTURE.md)

## Attribution

Laravel, Tailwind CSS, Leaflet, OpenStreetMap, Chart.js and Laravel Sanctum are open-source
libraries used under their respective licenses. Icons follow the [Feather Icons](https://feathericons.com)
set (MIT license, Copyright Cole Bemis) and are embedded as inline SVG. Fonts: Plus Jakarta Sans and
Fraunces (SIL Open Font License) via Google Fonts. Application code is written for this project.
Parts of it were developed with AI assistance and reviewed and understood by the author.
