````markdown name=README-setup.md
# Setup: Create the database with XAMPP (phpMyAdmin) for Lost_and_found

Steps to create/import the database locally using XAMPP:

1. Start XAMPP
   - Open the XAMPP Control Panel and start Apache and MySQL.

2. Using phpMyAdmin (GUI)
   - Open your browser and go to: http://localhost/phpmyadmin
   - Click "Import" in the top menu OR:
     - If you prefer creating DB first: click "New", enter `lost_and_found` as database name and Create.
     - Then choose the `sql/init_lost_and_found.sql` file from this repo (or the file you saved) and click "Go" to import.
   - After import, the database `lost_and_found` and its tables will be created.

3. Using MySQL CLI shipped with XAMPP
   - Open a terminal (or XAMPP Shell) and run:
     - On Windows (from XAMPP shell): mysql -u root < path\to\sql\init_lost_and_found.sql
     - On macOS/Linux (if using XAMPP CLI): mysql -u root < /full/path/to/sql/init_lost_and_found.sql
   - Note: XAMPP default root has no password. If you set a password, add `-p` and enter it when prompted.

4. Configure your app to use the DB
   - Copy `php/config.php` into your app (or adapt to your repo's config location).
   - If you changed MySQL credentials, update `db_user`/`db_pass` accordingly.

5. Creating the initial admin user (securely)
   - Do NOT insert a plaintext password. Use PHP's password_hash() to create a bcrypt hash.
   - Example small PHP script to create an admin user (run one time, e.g. via CLI or in a protected environment):

```php
<?php
// create_admin.php - run once to add an admin user
$cfg = require __DIR__ . '/../php/config.php';
$pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}", $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$username = 'admin';
$email = 'admin@example.com';
$password = 'ChangeMeStrong!'; // change this before running
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute([$username, $hash, $email]);
echo "Admin user created.\n";
```

   - Edit the `$password` value to a strong password, or set an `ADMIN_PASSWORD` environment variable, then run:
     - php php/create_admin.php

6. Next steps
   - Update your app's login/registration code to use `password_verify()` when checking passwords.
   - Hook up file uploads (photo_path) to a safe uploads directory and store relative paths.
   - Add validation and authorization (admin/staff roles) as needed.

If you want, I can also add example PHP pages (list items, report item, admin panel) wired to this schema.
````