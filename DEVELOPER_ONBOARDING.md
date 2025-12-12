## Developer Onboarding & Quick Commands

Quick steps to spin up the project locally and test the recently added features (branches, suppliers & purchases):

1) Ensure XAMPP is running (Apache + MySQL).
2) Place the repo in `htdocs` and open `http://localhost/pos_project/`.

3) Import DB schema (overwrites `pos_system` if exists):

    mysql -u root -p < schema.sql

4) Run seed script to create admin user, branch, supplier, sample product and a sample purchase:

    php seeds/seed_admin.php

5) Login via `login.php` with `admin` / `admin123` and change password.

6) Add or view branches:
 - `view_branches.php`
 - `add_branch.php`

7) Add or view suppliers:
 - `view_suppliers.php`
 - `add_supplier.php`

8) Add purchases:
 - `add_purchase.php` (increases stock for selected product)
 - `view_purchases.php`

9) Dashboard branch filter: Go to `dashboard.php` and select a branch from the dropdown to filter stats.

10) Codebase notes:
 - Use `auth_check.php` `check_access(['admin','cashier'])` to allow multiple roles.
 - Use `$_SESSION['branch_id']` to scope operations to the current branch or pass `branch_id` in endpoints.

11) Quick commands (PowerShell):

    # Import schema
    mysql -u root -p < schema.sql
    # Seed the DB
    php seeds/seed_admin.php
    # Validate server via curl
    curl http://localhost/pos_project/get_dashboard_data.php
