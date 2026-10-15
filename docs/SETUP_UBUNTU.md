# QueueLess — Ubuntu VirtualBox Deployment & Setup Guide

This comprehensive guide details how to transfer, deploy, configure, and verify the **QueueLess** system inside your Ubuntu VirtualBox environment (Apache 2.4, PHP 8.x, MySQL 8.x).

> [!IMPORTANT]
> **Manual Execution Notice:** The terminal commands listed in this guide are designed to be executed manually by you inside your Ubuntu VirtualBox terminal. Do not run destructive database commands or overwrite system configuration without reviewing them first.

---

## 1. Prerequisites Checklist in Ubuntu

Before transferring files, open a terminal in Ubuntu and verify the status of your LAMP services:

```bash
# Check Apache web server status
sudo systemctl status apache2

# Check MySQL database server status
sudo systemctl status mysql
```

### Checking Required PHP Extensions
QueueLess requires `pdo_mysql`, `mbstring`, and `session`. Run:
```bash
php -m | grep -E "pdo_mysql|mbstring|session"
```

If `pdo_mysql` or `mbstring` is missing from the output, install them:
```bash
sudo apt update
sudo apt install -y php-mysql php-mbstring
sudo systemctl restart apache2
```

---

## 2. Transferring Project Files from Windows to Ubuntu

Choose one of the two standard methods below:

### Option A: Oracle VirtualBox Shared Folder (Recommended)
1. In VirtualBox menu: **Devices** -> **Shared Folders** -> **Shared Folders Settings...**
2. Add your Windows folder `QUEUELESS_project` as a shared folder named `queueless_share`. Check **Auto-mount**.
3. Inside your Ubuntu terminal, add your user to the `vboxsf` group (only needed once):
   ```bash
   sudo usermod -aG vboxsf $USER
   ```
   *(Log out and log back into Ubuntu if this is your first time configuring shared folders).*
4. Access the files at `/media/sf_queueless_share/` and copy them to Apache's document root:
   ```bash
   sudo cp -r /media/sf_queueless_share /var/www/html/queueless
   ```

### Option B: Using SCP / Secure Copy (from Windows PowerShell)
If SSH is running on your Ubuntu VM (with port forwarding or bridged networking):
```powershell
# From Windows PowerShell in the QUEUELESS_project directory:
scp -r * your_ubuntu_user@<VM_IP_ADDRESS>:/tmp/queueless/
```
Then inside Ubuntu terminal:
```bash
sudo mv /tmp/queueless /var/www/html/queueless
```

---

## 3. Directory Permissions & File Ownership

Set safe Unix file permissions so Apache (`www-data`) can read project files without allowing arbitrary file modifications:

```bash
# Set owner to www-data and group to www-data
sudo chown -R www-data:www-data /var/www/html/queueless

# Grant read/execute permissions to directories
sudo find /var/www/html/queueless -type d -exec chmod 755 {} \;

# Grant read permissions to files
sudo find /var/www/html/queueless -type f -exec chmod 644 {} \;
```

---

## 4. MySQL Database Setup & User Creation

Open the MySQL command-line client as an administrator:

```bash
sudo mysql -u root -p
```
*(Enter your MySQL root password when prompted).*

Execute the following commands inside the MySQL shell:

```sql
-- 1. Create the dedicated database
CREATE DATABASE IF NOT EXISTS `queueless_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- 2. Create the dedicated application user (least-privilege)
-- Replace 'YourStrongAppPassword123!' with your desired password
CREATE USER IF NOT EXISTS 'queueless_app'@'localhost' 
    IDENTIFIED BY 'YourStrongAppPassword123!';

-- 3. Grant only required DML permissions (no DROP or administrative rights)
GRANT SELECT, INSERT, UPDATE, DELETE ON `queueless_db`.* TO 'queueless_app'@'localhost';

-- 4. Apply privilege updates
FLUSH PRIVILEGES;

-- 5. Exit MySQL shell
EXIT;
```

---

## 5. Importing the Schema and Seed Data

Import the complete tables schema and optional sample campus services:

```bash
# Import table structure (schema.sql)
sudo mysql -u root -p queueless_db < /var/www/html/queueless/database/schema.sql

# Import sample campus services (seed.sql)
sudo mysql -u root -p queueless_db < /var/www/html/queueless/database/seed.sql
```

Verify that the tables exist:
```bash
mysql -u queueless_app -p'YourStrongAppPassword123!' queueless_db -e "SHOW TABLES; SELECT id, name, is_open FROM services;"
```
You should see:
- `users`
- `services`
- `queue_tickets`
And 5 sample departments listed.

> [!TIP]
> **Idempotent Seed Execution:** `database/seed.sql` uses `INSERT IGNORE` targeting the unique constraint `uq_services_name`. You can safely re-execute `seed.sql` at any time without triggering duplicate key errors (Error 1062) or breaking existing queue tickets.

---

## 6. Configuring the Private Credentials File

To prevent password leaks and adhere to zero-credential exposure standards, actual database credentials are stored in `/etc/queueless/database.php` outside the web document root:

1. Create the directory with root privileges:
   ```bash
   sudo mkdir -p /etc/queueless
   ```

2. Create the file `/etc/queueless/database.php`:
   ```bash
   sudo nano /etc/queueless/database.php
   ```

3. Paste the following configuration, using the password you set in Step 4:
   ```php
   <?php
   return [
       'host'     => '127.0.0.1',
       'port'     => 3306,
       'database' => 'queueless_db',
       'username' => 'queueless_app',
       'password' => 'YourStrongAppPassword123!',
       'charset'  => 'utf8mb4'
   ];
   ```
   *(Save and exit nano: Press `Ctrl+O`, `Enter`, then `Ctrl+X`).*

4. Secure permissions so only the web server user can read it:
   ```bash
   sudo chown www-data:www-data /etc/queueless/database.php
   sudo chmod 600 /etc/queueless/database.php
   ```

---

## 7. Testing the PDO Database Connection

Run a quick PHP CLI test to verify that the connection succeeds:

```bash
php -r "require '/var/www/html/queueless/config/database.php'; \$pdo = get_db_connection(); if (\$pdo) { echo 'SUCCESS: Connected to MySQL database via PDO!\n'; } else { echo 'ERROR: ' . get_db_connection_error() . '\n'; }"
```

**Expected output:**
```
SUCCESS: Connected to MySQL database via PDO!
```

---

## 8. Opening and Testing the Application

1. Open Firefox inside your Ubuntu VirtualBox desktop.
2. Navigate to:
   ```
   http://localhost/queueless/
   ```
3. You should see the QueueLess landing page with the illustrative ticket preview.
4. Click **Get Started** or navigate to `http://localhost/queueless/register.php` to register a student account.
5. Log in at `http://localhost/queueless/login.php`.
6. You will be redirected to `http://localhost/queueless/home.php` displaying your personalized greeting, active department counters, and the "No Active Queue Ticket" banner.
7. Click **Join Queue** on an open department (e.g. Registrar's Office) to reserve your virtual spot.
8. The dashboard will immediately display your active ticket card showing your sequential token number (e.g., `#1`), queue position, and dynamic wait estimate.
9. Click **Cancel Ticket** to test the secure cancellation workflow and confirm that the status updates to `cancelled` in MySQL.

---

## 9. Troubleshooting & Diagnostics

### Issue 1: "Database configuration is missing" or "Database service is temporarily unavailable"
- **Cause:** `/etc/queueless/database.php` does not exist or Apache cannot read it.
- **Fix:**
  ```bash
  ls -l /etc/queueless/database.php
  # Ensure file is owned by www-data:
  sudo chown www-data:www-data /etc/queueless/database.php
  sudo chmod 600 /etc/queueless/database.php
  ```

### Issue 2: "Access denied for user 'queueless_app'@'localhost'"
- **Cause:** Password mismatch in `/etc/queueless/database.php` or user privileges not flushed.
- **Fix:**
  ```bash
  sudo mysql -u root -p -e "ALTER USER 'queueless_app'@'localhost' IDENTIFIED BY 'YourStrongAppPassword123!'; FLUSH PRIVILEGES;"
  ```

### Issue 3: HTTP 500 Internal Server Error
- **Cause:** PHP syntax error or missing extension.
- **Fix:** Inspect the Apache error log:
  ```bash
  sudo tail -n 30 /var/log/apache2/error.log
  ```

### Issue 4: Session Errors or Redirect Loops
- **Cause:** PHP session directory `/var/lib/php/sessions` lacks write permissions.
- **Fix:**
  ```bash
  sudo chown -R www-data:www-data /var/lib/php/sessions
  sudo chmod 733 /var/lib/php/sessions
  ```
