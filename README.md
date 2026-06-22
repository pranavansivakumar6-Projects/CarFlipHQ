# CarFlip HQ MVP

A simple PHP + MySQL car flipping management system.

Vehicle acquisition, repair management, task tracking, expense tracking, and profit analysis platform for automotive flipping businesses.

## How to run locally with XAMPP

1. Copy the `carfliphq` folder into `htdocs`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin.
4. Import `sql/carfliphq.sql`.
5. Open: `http://localhost/carfliphq/index.php`
6. Create the first admin account when prompted.

## Deploying on Railway

1. Create a new Railway project from the GitHub repository.
2. Add a MySQL database service to the same Railway project.
3. Deploy the web service from this repo. Railway will use the included `Dockerfile`.
4. Set `APP_BASE_PATH` to an empty value for the Railway web service.
5. Import `sql/carfliphq.sql` into the Railway MySQL database.
6. Run any newer migration files in `sql/` that are not already included in the imported database.
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
