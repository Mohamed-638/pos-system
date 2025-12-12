# DB setup & restore instructions

If you've lost the database (e.g., `pos_system`) locally, this repository includes an inferred schema and a seed script to recreate it.

Files:
- `schema.sql` — SQL file that creates all tables, constraints, and helpful indexes.
- `seeds/seed_admin.php` — PHP seed script that inserts an admin user (with password hashing), a sample product, and a default `licenses` entry.

Steps to recreate DB locally (XAMPP / phpMyAdmin):

1) Import `schema.sql` using phpMyAdmin or mysql CLI:

    -- Linux / macOS / Windows (WSL)
    mysql -u root -p < schema.sql

    -- Or from phpMyAdmin: select 'Import' -> Choose `schema.sql` -> Submit.

2) Confirm `db_connect.php` has the correct credentials (host, username, password and DB name `pos_system`).

3) Run the seed script (from terminal in repository root):

    php seeds/seed_admin.php

   This will:
   - Create an admin user with username `admin` and password `admin123` (change this immediately after login)
   - Add a sample product
   - Create a default license for `LITE-YOUR-CLIENT-CODE-001` and compute a `machine_id` for the current path

Safety & notes:
- The schema is inferred from code and may require adjustments to match production tables exactly.
- `seeds/seed_admin.php` uses `password_hash()` to create secure bcrypt-compatible hashes.
- After running the seed script, sign in to the app through `login.php` and change the generated password.
- Always backup your MySQL data directory before replacing the DB (if you have existing data).
