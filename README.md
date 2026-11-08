# QueueLess — Smart Campus Queue Management System

A secure, web-based campus queue management system designed to reduce waiting times, improve counter operations, and make university administrative services easier to access.

QueueLess allows students to join service queues and monitor their tickets, staff members to manage their assigned counters, and administrators to monitor campus queue activity and manage staff accounts.

---

## 1. Project Overview

University administrative offices often experience overcrowding, long waiting times, and inefficient manual queue handling.

QueueLess addresses these problems through a centralized web application with three role-based portals:

| Portal | Main Responsibilities |
|---|---|
| **Student** | Register, log in, view services, join queues, monitor ticket status, and cancel waiting tickets. |
| **Staff** | Manage an assigned service queue, call the next student, complete service, and skip the currently serving ticket. |
| **Admin** | Monitor campus services and queues, create staff accounts, and assign staff to services. |

The application uses PHP and MySQL for its backend, with a responsive frontend built using HTML, CSS, and vanilla JavaScript.

## 2. Main Features

### Student Portal
- Student registration and login.
- Personalized student dashboard.
- View available campus services.
- Check whether services are open or closed.
- Join an available service queue.
- Receive a sequential ticket number.
- View current ticket status and queue position.
- Cancel a ticket while it is waiting.
- View recent ticket history.
- Update profile name and password.

### Staff Portal
- Secure staff login.
- Staff dashboard showing the assigned service.
- View the currently serving ticket.
- View students waiting in the assigned queue.
- Call the next waiting student.
- Complete the currently serving ticket.
- Skip the currently serving ticket.
- View recent queue activity.

Each service is assigned to one staff member in the current implementation.

### Admin Portal
- Secure administrator login.
- View campus service information.
- Monitor registered students and queue activity.
- Monitor service availability and staff assignments.
- Create staff accounts.
- Assign staff members to services.
- Generate secure, time-limited staff account setup links.

The system is designed with one administrator account. Public registration is restricted to the student role; administrator and staff accounts are not created through the public student registration form.

## 3. Queue Management Workflow

The main queue lifecycle is:

1. A student logs in and selects an available campus service.
2. The system checks whether the student already has an active ticket.
3. The system generates the next sequential token number for the selected service.
4. The ticket is added to the waiting queue.
5. Assigned staff members call the next waiting student.
6. The ticket changes to the serving state.
7. Staff complete or skip the currently serving ticket.
8. Students can view their ticket status and cancel tickets that are still waiting.

Queue operations use database transactions and row-level locking to help prevent conflicting queue updates and duplicate token allocation during concurrent requests.

### Ticket Statuses

| Status | Meaning |
|---|---|
| `waiting` | Student is waiting for their turn. |
| `serving` | Staff has called the student. |
| `completed` | Service has been completed. |
| `skipped` | The currently serving ticket was skipped. |
| `cancelled` | Student cancelled the waiting ticket. |
| `served` | Legacy/compatible status retained in the database. |

## 4. Technology Stack

| Component | Technology |
|---|---|
| Backend | PHP 8.x |
| Database | MySQL 8.x |
| Database access | PHP PDO |
| Web server | Apache 2.4 |
| Frontend | HTML5 |
| Styling | CSS3 |
| Client-side scripting | Vanilla JavaScript (ES6) |
| Development environment | Ubuntu Linux inside VirtualBox |
| Code editor | Visual Studio Code |
| Version control | Git and GitHub |

### Development Principles

- No PHP framework is required.
- No frontend framework such as React or Vue is required.
- No Composer or NPM dependency is required by the application.
- Database operations use PDO prepared statements.
- Shared PHP components reduce repeated code.
- The interface uses a responsive Premium Tech-inspired design.
- Application assets are stored locally.

## 5. Project Directory Structure

```text
QueueLess/
│
├── index.php
├── login.php
├── register.php
├── logout.php
├── home.php
├── join_queue.php
├── cancel_ticket.php
│
├── admin/
│   ├── index.php
│   └── staff.php
│
├── staff/
│   ├── index.php
│   ├── setup.php
│   └── queue_action.php
│
├── config/
│   ├── database.php
│   └── database.local.php.example
│
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
│
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── style.backup.css
│   │
│   ├── js/
│   │   └── script.js
│   │
│   └── images/
│       └── logo.png
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── docs/
│   ├── DATABASE_DESIGN.md
│   ├── SETUP_UBUNTU.md
│   └── TESTING.md
│
├── backups/                  # Local database backups; not committed
├── README.md
└── .gitignore
```

### Important File Responsibilities

| File | Responsibility |
|---|---|
| `config/database.php` | Creates and manages the reusable PDO database connection. |
| `includes/auth.php` | Authentication helpers, role checks, and protected route access. |
| `includes/csrf.php` | CSRF token generation and validation. |
| `includes/functions.php` | Shared helpers, HTML escaping, redirects, and flash messages. |
| `includes/header.php` | Shared navigation, branding, and page header. |
| `includes/footer.php` | Shared page footer and script inclusion. |
| `join_queue.php` | Handles student queue registration and token allocation. |
| `cancel_ticket.php` | Handles student ticket cancellation. |
| `admin/staff.php` | Staff account creation and service assignment. |
| `staff/setup.php` | Secure staff password setup using a one-time setup token. |
| `staff/queue_action.php` | Handles staff queue operations. |

## 6. Database Design

QueueLess uses MySQL with the InnoDB storage engine and UTF-8 support.

### Main Database Tables

| Table | Purpose |
|---|---|
| `users` | Stores student, staff, and administrator accounts. |
| `services` | Stores campus service information, assigned staff, and service availability. |
| `queue_tickets` | Stores student tickets, token numbers, queue status, and timestamps. |
| `staff_setup_tokens` | Stores hashed, time-limited staff account setup tokens. |

### Relationships

- A user can have student, staff, or administrator access according to their assigned role.
- A service can be assigned to one staff member in the current implementation.
- A student can have multiple historical queue tickets.
- Each queue ticket belongs to a student and a service.
- Staff setup tokens are associated with staff accounts and the administrator who created them.

For the detailed database structure and ER diagram, see:

[`docs/DATABASE_DESIGN.md`](docs/DATABASE_DESIGN.md)

**Important:** `database/schema.sql` is intended for initial database creation. Do not import it over an existing database containing project data, because the schema script may recreate or remove existing tables.

## 7. Installation and Setup — Ubuntu VirtualBox

For the complete installation guide, see:

[`docs/SETUP_UBUNTU.md`](docs/SETUP_UBUNTU.md)

### Requirements

- Ubuntu Linux
- Apache 2.4
- PHP 8.x
- PHP PDO and PDO MySQL extensions
- MySQL 8.x
- A web browser
- Visual Studio Code (recommended)

### Step 1 — Install Required Packages

```bash
sudo apt update
sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql
```

Check PHP:

```bash
php -v
```

Check Apache:

```bash
sudo systemctl status apache2
```

Check MySQL:

```bash
sudo systemctl status mysql
```

### Step 2 — Place the Project in the Web Directory

For a fresh installation, copy the project into Apache's web directory:

```bash
sudo cp -r QueueLess /var/www/html/queueless
```

Set appropriate ownership and permissions according to the deployment environment.

For an existing development setup, the project may instead be maintained in the user's home directory with Apache pointing to it through a symbolic link.

### Step 3 — Create the Database

For a fresh database installation only:

```bash
sudo mysql
```

Execute:

```sql
CREATE DATABASE queueless_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Exit MySQL:

```sql
EXIT;
```

Import the initial schema and sample service records:

```bash
sudo mysql queueless_db < /var/www/html/queueless/database/schema.sql
sudo mysql queueless_db < /var/www/html/queueless/database/seed.sql
```

**Do not repeat these import commands on an existing installation without first reviewing the schema and backing up the database.**

### Step 4 — Configure Database Credentials

QueueLess supports protected local database configuration.

The preferred production configuration location is:

```text
/etc/queueless/database.php
```

Create the directory:

```bash
sudo mkdir -p /etc/queueless
```

Create the configuration file using the example:

```bash
sudo cp /var/www/html/queueless/config/database.local.php.example \
/etc/queueless/database.php
```

Edit the configuration:

```bash
sudo nano /etc/queueless/database.php
```

Enter the correct database name, username, password, and host.

Set ownership and permissions:

```bash
sudo chown www-data:www-data /etc/queueless/database.php
sudo chmod 600 /etc/queueless/database.php
```

The application also supports a local development configuration file:

```text
config/database.local.php
```

This file must remain private and must never be committed to GitHub.

### Step 5 — Open QueueLess

Start Apache and MySQL:

```bash
sudo systemctl start apache2
sudo systemctl start mysql
```

Open the application in your browser:

```text
http://localhost/queueless/
```

## 8. Account Setup and User Roles

### Student Accounts

Students can register through the public registration page.

The registration process creates student accounts only. The submitted role cannot be used to obtain administrator or staff privileges.

### Administrator Account

The application is designed for one administrator account.

The administrator account must be provisioned securely rather than created through public student registration.

### Staff Accounts

Staff accounts are created by the administrator.

The administrator can assign a staff member to a campus service. A secure setup link is generated for the staff member to create their password.

Staff setup links:
- Use randomly generated tokens.
- Store token hashes in the database.
- Have a limited validity period.
- Can only be used once.

## 9. Security Features

QueueLess incorporates several security practices:

1. **Password Hashing:** Passwords are stored using PHP's `password_hash()` and verified using `password_verify()`.

2. **Prepared SQL Statements:** PDO prepared statements are used for database operations involving user input.

3. **Session Security:** Session IDs are regenerated after successful authentication to help prevent session fixation.

4. **Protected Sessions:** Session cookies use HTTP-only and SameSite settings, with secure-cookie handling when HTTPS is used.

5. **CSRF Protection:** State-changing form submissions use CSRF tokens validated on the server.

6. **Role-Based Access Control:** Student, staff, and administrator routes enforce their required roles.

7. **XSS Protection:** Dynamic output is escaped using HTML-safe output helpers.

8. **Private Database Credentials:** Local database credentials are excluded from version control.

9. **Secure Staff Setup:** Staff password setup uses hashed, expiring, one-time tokens.

10. **Transactional Queue Operations:** Database transactions and row-level locking help maintain consistency during queue operations.

## 10. Testing

The project includes testing documentation:

[`docs/TESTING.md`](docs/TESTING.md)

### PHP Syntax Validation

Run from the project directory:

```bash
find . -type f -name "*.php" \
-not -path "./vendor/*" \
-exec php -l {} \;
```

### Important Manual Test Cases

| Test | Expected Result |
|---|---|
| Student registration | A student account is created successfully. |
| Student login | The student is redirected to the student dashboard. |
| Invalid login | A suitable authentication error is displayed. |
| Join queue | A sequential ticket is generated. |
| Duplicate active ticket | A second active ticket is prevented. |
| Cancel waiting ticket | The ticket changes to cancelled. |
| Staff login | Staff are redirected to their dashboard. |
| Call next student | The next waiting ticket becomes serving. |
| Complete ticket | The currently serving ticket is completed. |
| Skip ticket | The currently serving ticket is skipped. |
| Admin login | The administrator dashboard opens. |
| Create staff account | A staff account and setup link are generated. |
| Assign staff | The selected service is assigned to the staff member. |
| Unauthorized access | Users cannot access pages outside their permitted roles. |

## 11. Current Project Scope

The current implementation includes:

- Student authentication and dashboard.
- Student queue joining and cancellation.
- Staff account creation and password setup.
- Service-based staff assignment.
- Staff queue management.
- Administrator monitoring and staff management.
- Secure PHP/MySQL backend.
- Responsive frontend interface.

### Current Design Decisions

- One administrator account.
- One staff member assigned per service.
- Queue actions are managed by the assigned staff member.
- Public registration is restricted to students.

Features such as multiple staff members managing the same service are outside the current implementation scope and may be considered for future development.

## 12. Future Enhancements

Potential future improvements include:

- Automatic live queue refresh.
- Real-time notifications for students.
- Multiple staff members assigned to a single service.
- Estimated waiting-time calculations.
- Queue analytics and reporting.
- Improved administrative reports.
- Additional accessibility improvements.

These are future enhancements, not claims about currently implemented functionality.

## 13. GitHub Repository

Source code and project updates:

[QueueLess — GitHub Repository](https://github.com/codebyzaini/QueueLess)

The repository excludes local database credentials, environment secrets, and database backup files through `.gitignore`.

## 14. Academic Project Summary

QueueLess demonstrates the application of:

- PHP-based server-side development.
- MySQL relational database design.
- Role-based authentication and authorization.
- Secure session management.
- CSRF protection and input validation.
- Database transactions and concurrency handling.
- Responsive frontend development.
- Practical software engineering and project documentation.

The project provides a functional campus queue management workflow connecting students, staff, and administration through a centralized web application.

---

**Project:** QueueLess — Smart Campus Queue Management System  
**Backend:** PHP  
**Database:** MySQL  
**Frontend:** HTML, CSS, JavaScript  
**Development Environment:** Ubuntu Linux / VirtualBox  
**Version Control:** Git / GitHub
