# CampusTrack

CampusTrack is a small, role-based academic management system for an educational institution. It uses Core PHP, PDO, MySQL, HTML, CSS, and vanilla JavaScript. It is intended to run locally with XAMPP and includes data for trying the admin, faculty, and student workflows.

## Requirements

- XAMPP with PHP 8.0 or newer and MySQL/MariaDB
- PHP extensions `pdo_mysql` and `fileinfo` enabled
- Apache `.htaccess` support for the included protection rules
- A writable `uploads/` directory for faculty materials

No Composer install, hosted database, paid service, API key, email provider, or payment service is required.

## XAMPP setup

1. Copy the `campustrack` folder into XAMPP's `htdocs` directory. The expected path is `C:\xampp\htdocs\campustrack` on Windows or `/Applications/XAMPP/htdocs/campustrack` on macOS.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Create the `campustrack` database and import `database/schema.sql` in phpMyAdmin. The SQL file also creates/selects that database, so importing from phpMyAdmin's home page works when the MySQL account can create databases.
4. Alternatively, open `http://localhost/campustrack/setup.php` and choose **Create database tables and demo data**. The installer will not replace records when it detects an existing user table containing accounts.
5. Open `http://localhost/campustrack/` and sign in with one of the demo accounts below.
6. Remove `setup.php` after a successful install. Change the demo passwords before putting any real student information in the system.

The schema uses `INSERT IGNORE` and `CREATE TABLE IF NOT EXISTS`; importing it again will not drop tables or erase existing rows.

### Database connection

XAMPP defaults are already configured in `config/db.php`: host `localhost`, database `campustrack`, user `root`, blank password, port `3306`. Use environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_PORT` to override these values.

### Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@campustrack.com` | `admin123` |
| Faculty | `faculty1@campustrack.com` | `faculty123` |
| Faculty | `faculty2@campustrack.com` | `faculty123` |
| Faculty | `faculty3@campustrack.com` | `faculty123` |
| Student | `student1@campustrack.com` through `student10@campustrack.com` | `student123` |

All seeded passwords are stored as bcrypt hashes. The seeded student account for `student1@campustrack.com` has deliberately low attendance to demonstrate the below-75% alert.

## Features

### Admin

- Dashboard totals for students, faculty, courses, and today's attendance
- Add, edit, and delete courses
- Create, edit, delete, and search user accounts; filter by role and department; reset passwords
- Assign faculty to courses and enroll or remove students from course rosters
- Generated roll numbers and employee IDs when none are entered

### Faculty

- Dashboard limited to assigned courses, with course attendance and grade averages
- Record or revise attendance by course and class date; the unique student/course/date key prevents duplicate records
- Enter or update internal, midterm, and final marks; total, percentage, and letter grade are calculated when saved
- Add materials as links or upload PDF, image, and Office documents (up to 5 MB); remove shared materials

### Student

- Dashboard with overall attendance, course summaries, and below-75% alerts
- Weekly timetable, live course attendance summary/chart, and grades
- Printable report card with course marks, attendance, average, and a simple 4.0-scale CGPA estimate
- Download course files or open course resource links

### Shared behavior

- Role-aware sign-in, role-based access checks, common navigation, flash messages, and custom 403/404 pages
- Prepared database statements, HTML output escaping, CSRF tokens on forms, password hashing, and session ID regeneration at sign-in
- Server-side and browser-side form checks
- AJAX admin search and periodic student attendance refresh
- Responsive layout and an offline canvas chart fallback if the Chart.js CDN cannot be reached

## Database overview

- `users` stores accounts and their roles.
- `courses` optionally references its faculty member (`ON DELETE SET NULL`).
- `enrollments` connects students and courses, with one row per student/course pair.
- `attendance` belongs to a student and course, with one row per student/course/date.
- `grades` stores three assessment marks and the calculated result per student/course.
- `schedules` and `materials` belong to a course.
- Student-owned records and course records use foreign keys with cascading deletes where appropriate.

## Security and deployment notes

- Faculty pages only query courses assigned to the signed-in faculty member.
- Student queries derive the student ID from the signed-in session; no student ID can be selected from the page URL.
- Course-file downloads go through an authenticated endpoint that confirms the student is enrolled. Direct web access to `uploads/` is blocked by `.htaccess`.
- The site uses Bootstrap 5 and Chart.js CDN references as requested, but its layout uses local CSS and charts have a local fallback. Core login, CRUD, and report printing do not require those CDNs.
- The report card opens the browser's print dialog; select “Save as PDF” to create the PDF. No separate PDF package is required.
- Before real use, remove the demo accounts, set unique strong passwords, configure a database password, and serve the site over HTTPS.

## Project map

```text
campustrack/
├── admin/                 Admin dashboard, courses, accounts, and assignments
├── assets/css/style.css   Responsive theme and print styles
├── assets/js/script.js    Validation, AJAX, chart, and small UI behaviors
├── auth/                  Login and logout
├── config/                PDO connection and session/RBAC helpers
├── database/schema.sql    Tables and demo seed data
├── errors/                403 and 404 pages
├── faculty/               Attendance, grades, and course materials
├── includes/              Shared layout and helper functions
├── student/               Dashboard, attendance, schedule, grades, and report
├── uploads/                Uploaded course materials (ignored by Git)
├── index.php
└── setup.php
```