# 🎯 Online Event Management System

A full-featured web-based Event Management System built with **PHP + MySQL**.

---

## 🚀 Quick Setup

### 1. Requirements
- **PHP 8.0+** (XAMPP / WAMP / Laragon)
- **MySQL 8.0+**
- A web server (Apache via XAMPP recommended)
- *(Optional)* Composer — for TCPDF PDF generation

### 2. Database Setup

1. Open **phpMyAdmin** or MySQL CLI
2. Run the database schema:
```sql
source /path/to/database/schema.sql
```
Or import `database/schema.sql` via phpMyAdmin → Import tab.

### 3. Configure Database

Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'event_management_db');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
```

### 4. Place Files

Copy this folder to your web server root:
- **XAMPP**: `C:/xampp/htdocs/online event management system/`
- **WAMP**: `C:/wamp64/www/online event management system/`

### 5. Access the App

Open browser → `http://localhost/online%20event%20management%20system/`

---

## 🔑 Demo Credentials

| Role    | Email                   | Password   |
|---------|-------------------------|------------|
| Admin   | admin@eventms.com       | Admin@123  |
| Student | aarav@student.com       | Admin@123  |
| Student | priya@student.com       | Admin@123  |

---

## 📄 PDF Tickets (Optional — TCPDF)

For server-side PDF generation, install TCPDF:
```bash
composer install
```

Without Composer, the system uses a **browser-printable HTML ticket** with a "Print / Save as PDF" button — works perfectly without any extra setup!

---

## 📁 Project Structure

```
online event management system/
├── index.php                    # Landing page
├── login.php                    # Login (admin + student)
├── register.php                 # Registration
├── logout.php
├── config/
│   └── db.php                   # Database config + helpers
├── database/
│   └── schema.sql               # MySQL schema + seed data
├── includes/
│   ├── header.php               # Public navbar
│   ├── footer.php               # Public footer
│   ├── auth_check.php           # Role guard
│   ├── admin_layout.php         # Admin sidebar (open)
│   ├── admin_layout_end.php     # Admin sidebar (close)
│   ├── student_layout.php       # Student sidebar (open)
│   └── student_layout_end.php   # Student sidebar (close)
├── admin/
│   ├── dashboard.php            # Stats + charts
│   ├── events.php               # Event list + search
│   ├── event_add.php            # Create event (with live preview)
│   ├── event_edit.php           # Edit event
│   ├── event_delete.php         # Delete handler
│   ├── registrations.php        # View all registrations
│   └── reports.php              # Generate reports + charts
├── student/
│   ├── dashboard.php            # Student home
│   ├── events.php               # Browse events + register
│   ├── my_registrations.php     # My registrations + cancel
│   └── download_pdf.php         # PDF/printable ticket
├── api/
│   ├── register_event.php       # AJAX register
│   └── cancel_event.php         # AJAX cancel
├── assets/
│   ├── css/style.css            # Full design system
│   └── js/main.js               # JS interactions
└── composer.json                # TCPDF dependency
```

---

## ✨ Features

| Feature | Details |
|---------|---------|
| **Auth** | Role-based login (Admin/Student), bcrypt passwords |
| **Admin Events** | Create, Edit, Delete events with live preview |
| **Admin Reports** | Bar chart, doughnut chart, per-event student lists |
| **Student Browse** | Category filters, search, capacity bars |
| **Student Register** | AJAX registration with instant feedback |
| **Student Cancel** | AJAX cancellation for upcoming events |
| **PDF Ticket** | Printable HTML ticket (+ TCPDF if installed) |
| **Design** | Dark glassmorphism, Chart.js, animated hero |

---

## 🛠 Tech Stack

- **Frontend**: HTML5 + CSS3 (Glassmorphism) + Vanilla JS
- **Backend**: PHP 8+
- **Database**: MySQL 8+
- **Charts**: Chart.js (CDN)
- **Fonts**: Google Fonts (Inter + Outfit)
- **PDF**: TCPDF (optional) / HTML Print fallback
