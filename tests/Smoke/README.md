# Smoke tests (`tests/Smoke`)

These run against the **real dev database** (`pgsql`, database `company_crm`) and drive
the **real HTTP routes** with real seeded demo data. They are the guard against
"tests hijau tapi UI ngawur" — green unit/feature tests that pass on fresh factory
fixtures while the actual seeded app behaves differently.

They have caught bugs the phpunit suite missed:

- **H7b** — a false green where deactivation looked fine on factory data but the real
  seeded book told a different story.
- **H7d / L2-D** — verified revenue and archival on *actual* seeded rows, not fixtures.

## Why they are NOT in the phpunit suite

`phpunit.xml` only registers the `Unit` and `Feature` testsuites, so `tests/Smoke`
**never runs automatically** with `pest` / `php artisan test` / CI. That is deliberate:

- They need a live `pgsql` database seeded with the demo data — they will fail on a
  blank or `:memory:` sqlite DB.
- They point the `pgsql` connection back at `company_crm` explicitly (phpunit.xml
  forces `DB_DATABASE=:memory:`), so they only make sense on a developer box.
- Every write runs inside a rolled-back transaction (or is restored on teardown),
  so they leave the dev data exactly as they found it — but they still touch it.

## Running them

```bash
# 1. Have the demo data seeded (once):
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoLoginSeeder   # predictable one-login-per-role

# 2. Run the smoke suite explicitly (path, not testsuite name):
vendor/bin/pest tests/Smoke
```

## Demo credentials

The demo logins (from `DemoLoginSeeder`) all use the password `password`:

| email                    | role        |
|--------------------------|-------------|
| `test@example.com`       | admin       |
| `manager@crm.test`       | supervisor  |
| `cs@crm.test`            | cs          |
| `sales@crm.test`         | sales       |
| `maintenance@crm.test`   | maintenance |

These are throwaway local-only credentials for manual QA and smoke tests — never a
production concern.
