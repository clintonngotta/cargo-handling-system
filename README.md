# Cargo Handling System

A Laravel + FilamentPHP service for bulk liquid terminal management. It tracks palm oil products (CPO, CPOL, PFAD) through one workflow:

**Vessel discharge (inbound) → shore storage tank → truck delivery (outbound)**

Built for the Bulkstream Limited System Developer technical assessment.

## Status

The domain rules, ingestion pipeline, persistence and Filament dashboard are built and tested. A queue or API log source, the standalone repository and the live demo are still to do. [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md) tracks each requirement.

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): layers, the dependency rule, and how a log flows through the system
- [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md): requirement status, data model, assumptions, known limitations and remaining work

## Requirements

- PHP 8.5 and Composer
- Docker, to run MySQL (needed for the concurrency test and any shared environment)

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

Open <http://localhost:8000/admin> and log in as `operator@bulkstream.test` with the password `password`. These are demo credentials, so change them on any public environment.

The seed data includes one deliberate failure: `VSL-2003` is rejected because it would overflow tank `PFAD-01`. To see a retry succeed, raise that tank's capacity to at least 1,194,000 L under **Storage Tanks**, then choose **Retry** on the dashboard.

## The dashboard

- **Dashboard:** live stat tiles, tank levels and recent rejections, refreshed every 5 seconds
- **Storage Tanks:** balance, capacity, available room and percentage full. The balance is read-only
- **Cargo Movements:** a filterable ledger with a retry action for rejected movements
- **Log movement:** a reactive form that previews the transit loss, resulting balance and any rule violation before you submit

## Testing

```sh
php artisan test
vendor/bin/pint --test
```

SQLite has no row locks, so `ConcurrentSettlementTest` is skipped by default. To run it, race the worker processes against MySQL:

```sh
docker compose up -d --wait
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=cargo_test \
DB_USERNAME=root DB_PASSWORD=root php artisan test --filter=ConcurrentSettlementTest
```

## Project layout

```text
app/
├── Domain/Cargo/         Business rules in plain PHP, with no framework imports
│   ├── Enums/            Commodity, MovementDirection, MovementStatus
│   ├── Exceptions/       CargoDomainException and its subtypes
│   ├── Services/         TransitLossPolicy
│   └── ValueObjects/     Litres, AdjustedQuantity, TankState
├── Application/Cargo/    Use cases that coordinate the domain and the database
│   ├── Actions/          Ingest, settle, preview, retry, update tank
│   └── Data/             MovementLogData, MovementPreview, reports
├── Models/               Eloquent persistence: StorageTank, CargoMovement
├── Observers/            Entity state hooks for those models
└── Filament/             Operator dashboard: resources, widgets, shared actions
```
