# SplitWise PRO - Dynamic PHP Group Expense Tracker

A full-featured, secure, and modern group expense tracker built in **PHP (8.x)** with **Vanilla CSS (Glassmorphism Dark Mode)** and **PDO Database Abstraction** supporting both **SQLite** and **MySQL**.

---

## 🌟 Key Features

1. **User Authentication & Authorization**:
   - Secure registration and login with bcrypt password hashing (`password_hash`).
   - Session fixation protection & CSRF token protection on all forms.

2. **Group Management & Unique Short URLs**:
   - Create groups with name, description, and initial participant list.
   - Generates a short, unique 6 to 8 character token (`a-z`, `0-9`) for group share links (e.g., `/public?token=2g4hvk`).
   - Owner controls: Edit group details, add/remove participants, edit/delete expenses, or delete groups.

3. **Multi-Currency Support (Default: INR ₹)**:
   - Default currency is set to **INR (₹)** across the application (`config.php`).
   - Group owners can customize and change the currency anytime per group (e.g. `₹ (INR)`, `$ (USD)`, `€ (EUR)`, `£ (GBP)`, `¥ (JPY)`, `AED`, etc.) during group creation or editing.
   - All expense totals, participant shares, and settle-up calculations dynamically render using the selected group currency.

4. **Hand-Drawn Paper Theme (Neubrutalism)**:
   - Playful hand-drawn notebook paper aesthetic with subtle gridlines, notebook red margin rules, and paper tape sticker details.
   - Bold ink borders (`3px solid #1e293b`), tactile 3D pop shadows, interactive button press physics (`transform: translate`).
   - Playful cartoon typography with Google Fonts **Fredoka** and handwritten **Kalam**.
   - Fully responsive grid and flex layouts built for fluid rendering on all screen sizes (mobile phones, tablets, desktop).

4. **Public Shareable Link**:
   - Owner can copy a unique tokenized URL (`/public.php?token=...`).
   - Shareable with anyone to view real-time expense breakdown, individual totals, and final settlement recommendations without requiring a login.

5. **Debt Settlement Algorithm ("Who Pays Whom and How Much")**:
   - Automatically computes net balance per participant (`Total Paid - Total Share`).
   - Applies a debt-simplification greedy algorithm to determine minimal transfers needed to settle all debts.

6. **Database Driver Agnostic (SQLite & MySQL)**:
   - Configurable database backend in `config.php`.
   - Auto-migrates database schema on first connection.

7. **SQL Injection Proof**:
   - 100% PDO prepared statements with bound parameters across all database interactions.
   - HTML escaping (`e()` / `htmlspecialchars`) on all outputs against XSS.

---

## 📁 Directory & File Structure

```
expance/
├── config.php                  # Central configuration (SQLite/MySQL choice, credentials, currency)
├── index.php                   # Dashboard displaying user's expense groups
├── register.php                # Account registration page
├── login.php                   # User login page
├── logout.php                  # Logout handler
├── create_group.php            # Group creation form with dynamic participant inputs
├── group.php                   # Group workspace (expenses, balances, settlements, share link)
├── public.php                  # Read-only public share view accessible via token
├── add_expense.php             # Form to log new expenses and select split participants
├── edit_expense.php            # Owner form to edit existing expenses
├── delete_expense.php          # Owner handler to remove expenses
├── add_participant.php         # Add participant handler
├── delete_participant.php      # Remove participant handler
├── edit_group.php              # Edit group details
├── delete_group.php            # Delete group handler
├── includes/
│   ├── db.php                  # PDO connection factory & schema auto-initialization
│   ├── auth.php                # Authentication, CSRF token, & authorization helpers
│   ├── functions.php           # Calculations, group queries, & debt settlement algorithm
│   ├── header.php              # Shared HTML navigation header
│   └── footer.php              # Shared HTML footer
├── css/
│   └── style.css               # Glassmorphism dark mode responsive stylesheet
├── js/
│   └── main.js                 # Clipboard copy, dynamic inputs, & checkbox toggles
└── database/schema_mysql
    ├── expense_tracker.sqlite  # SQLite database file (Auto-created when using SQLite)
    ├── schema_sqlite.sql       # Reference SQL script for SQLite
    └── schema_mysql.sql        # Reference SQL script for MySQL
```

---

## ⚙️ Configuration & Database Setup

### 1. SQLite Mode (Default)
By default, the application runs out-of-the-box using SQLite. No extra database server installation required.

In `config.php`:
```php
define('DB_DRIVER', 'sqlite');
define('DB_SQLITE_PATH', __DIR__ . '/database/expense_tracker.sqlite');
```

### 2. MySQL Mode
To switch to MySQL:
1. Open `config.php` and set `DB_DRIVER` to `'mysql'`.
2. Configure your MySQL connection credentials:

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```
*The application automatically creates all necessary MySQL tables (`users`, `groups`, `participants`, `expenses`, `expense_splits`) upon connection.*

---

## 🔒 Security Measures

- **SQL Injection Prevention**: Every SQL query is executed via PDO prepared statements (`$db->prepare(...)` and `$stmt->execute([...])`). No raw input strings are concatenated into SQL queries.
- **XSS Protection**: HTML rendering uses `e()` helper (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- **CSRF Protection**: Form submissions require a cryptographically generated session CSRF token (`verifyCSRFToken()`).
- **Password Hashing**: Passwords stored using standard `PASSWORD_BCRYPT`.

---

## 🚀 Running locally

Start PHP built-in web server inside project directory:
```bash
php -S localhost:8000
```
Open `http://localhost:8000` in your web browser.
