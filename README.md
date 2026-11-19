# Ngunda Wireless (NWS)

Smart Wi-Fi Hotspot Management, Voucher Authentication, and Network Control Platform for Starlink / Ubuntu Server LTS gateways.

## Repository Structure

- `docs/` — Architecture plan (`PHASE1-ARCHITECTURE-PLAN.md`) and build status (`STATUS.md`)
- `laravel/` — Laravel central management web application (RBAC, packages, voucher/token engine, QR print sheets, dashboard, audit trail)
- `scripts/phase2/` — Read-only Ubuntu Server & hardware assessment script (`assess-ubuntu.sh`) and pre-change network backup script (`backup-network-config.sh`)

## Quick Start (Laravel Application)

```bash
cd laravel
cp .env.example .env
composer install
php artisan key:generate
# Set DB_*, NWS_TOKEN_PEPPER, NWS_ADMIN_EMAIL, and NWS_ADMIN_PASSWORD in .env
php artisan migrate --seed
php artisan serve
```

## Running Tests

```bash
cd laravel
php vendor/bin/phpunit
```
