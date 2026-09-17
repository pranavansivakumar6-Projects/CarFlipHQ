# CarFlip HQ MVP

A simple PHP + MySQL car flipping management system.

Vehicle acquisition, repair management, task tracking, expense tracking, and profit analysis platform for automotive flipping businesses.

## How to run locally with XAMPP

1. Copy the `carfliphq` folder into `htdocs`.
2. Start Apache and MySQL in XAMPP.
3. Create an empty `carfliphq` database if it does not already exist.
4. From the application directory, run `php migrate.php`.
5. Open: `http://localhost/carfliphq/index.php`
6. Create the first admin account when prompted.

## Database migrations

Normal web requests only connect to the database. They never install or upgrade the schema.

Before applying migrations to a populated database, create a backup:

```bash
mysqldump --single-transaction --routines --triggers -h HOST -P PORT -u USER -p DATABASE > carfliphq-before-migration.sql
```

Then run pending migrations explicitly from the application directory:

```bash
php migrate.php
```

The runner creates a `schema_migrations` ledger and executes each file in `migrations/` once, in filename order. A failed migration is not recorded, later migrations are not run, and a database lock prevents two migration processes from running concurrently.

For future database changes, add a timestamp-prefixed PHP file to `migrations/` that returns a callable accepting `PDO`. Migrations should be additive and idempotent where practical. Never run `migrate.php` from a public URL or from a normal page request.

Rollback procedure:

1. Stop the deployment if a migration fails.
2. Revert the application release.
3. Restore the pre-migration SQL backup if the failed migration changed schema or data.
4. Correct the migration and run `php migrate.php` again.

## Deploying on Railway

1. Create a new Railway project from the GitHub repository.
2. Add a MySQL database service to the same Railway project.
3. Deploy the web service from this repo. Railway will use the included `Dockerfile`.
4. Set `APP_BASE_PATH` to an empty value for the Railway web service.
5. Create a Railway database backup before upgrading an existing installation.
6. Run `php migrate.php` once as an explicit deployment command with the Railway database variables available.
7. Open the Railway public URL and create the first admin account.

Railway storage is not the same as permanent cPanel disk storage. Uploaded receipts/photos may need a Railway volume or external object storage before serious production use.

## Current features

- Dashboard
- Add cars
- View all cars
- Individual car detail page
- Add expenses per car
- Add tasks per car
- Edit/delete expenses
- Edit/delete tasks
- Receipt/bill photo uploads for expenses
- Photo uploads for tasks
- Expense payer tracking
- Purchase payment tracking
- 50/50 cost, profit, and settlement summary
- Quick task status updates
- Profit/loss calculation
- Update car sale/status details
- First-admin setup
- Login/logout session protection
- Admin user management
- Task assignment from account names
- Multi-person task assignment
- Quick task assignment updates
- Task/job hours tracking
- Car photo and document uploads
- Parts tracking
- Sale listing and offer tracking
- Reports page
- Expanded dashboard metrics
- CSV spreadsheet import from Excel or Google Sheets
- Per-car CSV spreadsheet export

## Next features to add

- Partner roles
- Reports export
