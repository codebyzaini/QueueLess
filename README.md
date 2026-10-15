# QueueLess — Smart Campus Queue Management System
## Academic Milestone 1: Complete Implementation Brief

QueueLess is a secure, lightweight, and modern web application engineered to solve administrative crowding at university department counters (Registrar, Financial Aid, Academic Advising, ID Cards).

This repository represents the **First Submission**, providing a fully functional authentication system, complete database schema, protected student dashboard, and zero-dependency offline-ready user interface.

---

## 1. Teacher & Academic Requirements Compliance

| Requirement | Implementation Details | Status |
| :--- | :--- | :---: |
| **1. Public Entry / Landing Page** | `index.php` — Overview, problem/solution, three-step guide, illustrative ticket preview labeled sample data. Authenticated sessions auto-redirect to `home.php`. | **Complete** |
| **2. Login Page** | `login.php` — Email/password validation, password show/hide toggle, prepared statements, generic error messages against account enumeration. | **Complete** |
| **3. Registration Page** | `register.php` — Server-side validation, duplicate email check, `password_hash()`, role strictly forced to `'student'` (elevation impossible). | **Complete** |
| **4. Student Homepage** | `home.php` — Protected route, personalized greeting using name from MySQL, live campus services and open/closed badges from MySQL, graceful empty states. | **Complete** |
| **5. Frontend Tech Stack** | HTML5 semantic elements, Vanilla CSS with custom property design system, Vanilla JS micro-interactions. Zero external fonts or CDNs (100% offline capable). | **Complete** |
| **6. PHP Backend & Session Auth** | Session-based authentication, anti-session-fixation regeneration, HTTP-only/SameSite cookies, CSRF protection on POST actions. | **Complete** |
| **7. MySQL Database Configuration** | Zero `.env` files. Private credentials stored in `/etc/queueless/database.php` outside web root. Reusable PDO connection with `utf8mb4`. | **Complete** |
| **8. Complete Project Database Schema** | `database/schema.sql` — Complete schema with 3 core entities (`users`, `services`, `queue_tickets`), foreign keys, deletion policies, generated active-ticket safeguard, and indexes. | **Complete** |
| **9. Database Documentation & ER Diagram** | `docs/DATABASE_DESIGN.md` — Mermaid ER diagram, relationship cardinalities, active-ticket rule analysis, and concurrent token generation logic. | **Complete** |

---

## 2. Technology Stack & Design Principles

- **Backend:** PHP 8.x (CLI & Apache module)
- **Database:** MySQL 8.x (InnoDB engine, `utf8mb4` character set, `utf8mb4_unicode_ci` collation)
- **Database Driver:** PHP Data Objects (PDO) with prepared statements and exception mode
- **Web Server:** Apache 2.4 (VirtualBox Ubuntu target environment)
- **Frontend:** Semantic HTML5, Vanilla CSS3 (Custom Properties design system), Vanilla JavaScript (ES6)
- **Zero Third-Party Dependencies:** No React, Vue, Laravel, Composer, NPM, Node.js, or external CDN dependencies. Fully operational in air-gapped / offline university lab environments.

---

## 3. Project Directory Structure

```
QUEUELESS_project/
├── index.php                    # Public landing page; redirects active sessions to home.php
├── login.php                    # Student login form & authentication logic
├── register.php                 # Student registration form & account creation
├── home.php                     # Protected student dashboard, live ticket status & counter grid
├── join_queue.php               # Transactional queue reservation controller (pessimistic locking)
├── cancel_ticket.php            # Secure student ticket cancellation controller
├── logout.php                   # Secure session termination (POST + CSRF protected)
├── config/
│   ├── database.php             # Reusable PDO connection manager (reads /etc/queueless/database.php)
│   └── database.local.php.example # Configuration template for deployment
├── includes/
│   ├── auth.php                 # Session authentication helpers & route guards
│   ├── csrf.php                 # CSRF token generation & timing-safe validation
│   ├── functions.php            # Core helper functions (HTML escaping, redirects, flash alerts)
│   ├── header.php               # Shared accessible HTML header & navigation
│   └── footer.php               # Shared footer with academic metadata & script inclusions
├── assets/
│   ├── css/
│   │   └── style.css            # Deep Navy design system, responsive styles, accessible focus states
│   ├── js/
│   │   └── script.js            # Password show/hide toggle & progressive form enhancements
│   └── images/                  # Project imagery asset folder
├── database/
│   ├── schema.sql               # Authoritative DDL schema for the complete project
│   └── seed.sql                 # Sample campus service records (zero passwords/credentials)
├── docs/
│   ├── DATABASE_DESIGN.md       # Comprehensive entity analysis, constraints & Mermaid ER diagram
│   ├── SETUP_UBUNTU.md          # Step-by-step Ubuntu VirtualBox LAMP deployment guide
│   └── TESTING.md               # Test matrix, verification report, and manual test scripts
├── README.md                    # Project overview & architectural guide
└── .gitignore                   # Excludes sensitive local database files and system artifacts
```

### Purpose of Shared Helper Files
- **`includes/functions.php`**: Centralizes OWASP-compliant secure session initialization (`init_session()`), strict HTML output escaping (`e()`), HTTP redirection (`redirect()`), and flash messages (`set_flash()`, `get_flashes()`).
- **`includes/header.php` and `includes/footer.php`**: Eliminate duplicate boilerplate across pages, maintain consistent university branding, and ensure offline asset linking.

---

## 4. Security Architecture Highlights

1. **Zero-Credential Exposure in Code:**
   Database credentials are never stored in web-accessible directories or in version control. Production credentials reside in `/etc/queueless/database.php` (file permissions `600`, owned by `www-data:www-data`).
2. **SQL Injection Immunity:**
   Every database query utilizing dynamic user input strictly uses PDO prepared statements with parameterized inputs.
3. **Password Security:**
   Passwords are never stored in plaintext. They are hashed using PHP's standard `password_hash($password, PASSWORD_DEFAULT)` and verified using `password_verify()`.
4. **Session Fixation Prevention:**
   Upon successful authentication in `login.php`, `session_regenerate_id(true)` is immediately invoked to discard the pre-login session ID.
5. **Cross-Site Request Forgery (CSRF) Protection:**
   All state-altering requests (login, registration, logout) are verified against a cryptographically secure random token generated with `random_bytes(32)` and validated using timing-attack resistant `hash_equals()`.
6. **Role Elevation Guard:**
   The registration form hardcodes `'student'` in the SQL insert statement. Any client attempts to pass role values are ignored.
7. **Cross-Site Scripting (XSS) Prevention:**
   All dynamic database output is escaped using `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

---

## 5. Quick-Start Guide (Ubuntu VirtualBox)

For complete detailed steps, refer to [`docs/SETUP_UBUNTU.md`](docs/SETUP_UBUNTU.md).

```bash
# 1. Copy project into Apache root
sudo cp -r /media/sf_queueless_share /var/www/html/queueless
sudo chown -R www-data:www-data /var/www/html/queueless

# 2. Create MySQL database and import schema
sudo mysql -u root -p -e "CREATE DATABASE queueless_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -u root -p queueless_db < /var/www/html/queueless/database/schema.sql
sudo mysql -u root -p queueless_db < /var/www/html/queueless/database/seed.sql

# 3. Create least-privilege MySQL user
sudo mysql -u root -p -e "CREATE USER 'queueless_app'@'localhost' IDENTIFIED BY 'YourSecurePassword123!'; GRANT SELECT, INSERT, UPDATE, DELETE ON queueless_db.* TO 'queueless_app'@'localhost'; FLUSH PRIVILEGES;"

# 4. Create protected credentials file
sudo mkdir -p /etc/queueless
sudo cp /var/www/html/queueless/config/database.local.php.example /etc/queueless/database.php
# (Edit /etc/queueless/database.php to match your password)
sudo chown www-data:www-data /etc/queueless/database.php
sudo chmod 600 /etc/queueless/database.php

# 5. Open in Firefox inside Ubuntu
# URL: http://localhost/queueless/
```

---

## 6. Guide for Presenting This Project to Your Teacher

When presenting this submission, you can confidently explain:
1. **Separation of Concerns:** Show how business logic, presentation (`header.php`, `footer.php`), and configuration (`config/database.php`) are cleanly decoupled without requiring an overly complex framework.
2. **Database Concurrency & Constraints:** Refer to [`docs/DATABASE_DESIGN.md`](docs/DATABASE_DESIGN.md) to explain why active-ticket safety uses both application row locking and a generated-column unique constraint rather than a naive unique key on status.
3. **Security Engineering:** Point out that real credentials are never in the project directory, cookies are hardened with `HttpOnly` and `SameSite`, and logout requires a POST method with CSRF validation.
4. **Milestone Transparency:** Explain that this first submission delivers the authentication foundation, database architecture, and live service monitoring, setting the stage for Milestone 2's interactive token queue advancement.
