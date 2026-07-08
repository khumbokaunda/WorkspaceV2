# Meridian

A workplace management system for internal teams: people directory with an
org chart and a per-person document vault, attendance with a live team
board, leave with balances and an approvals queue, projects and tasks with
a Kanban board, an asset register with an assignment workflow, and a
certifications area with a cross-team skills matrix and scheduled expiry
reminders. Light and dark themes from one set of design tokens, a command
palette (Ctrl or Cmd plus K), slide-over drawers for day-to-day actions,
role-based access control with per-person overrides, and a complete audit
trail.

The product name is a placeholder: rename it by changing the `org_name`
value on the Settings screen, no code changes involved.

## Stack

Procedural PHP 8.2+ with mysqli prepared statements, MySQL 8.x (or
MariaDB 10.11+), Bootstrap 5.3, jQuery 3.7, DataTables, Parsley,
DOMPurify, SweetAlert2, PHPMailer, Inter and Font Awesome. All frontend
libraries ship as pinned local copies under `public/assets/vendor/`, so
the app renders identically with or without internet access. No
framework, no ORM, no npm, no build step.

## Layout

Only `public/` is web-reachable. Application code, configuration, the
schema, uploads and logs live above the document root and cannot be
requested over HTTP.

```
public/          front controller and static assets (the document root)
app/             bootstrap, router, config, middleware, controllers, views, partials, helpers
database/        schema.sql baseline plus numbered migrations
storage/         uploads (gated downloads only) and logs
cron/            scheduled jobs
config.php       secrets, gitignored, copied from config.example.php
```

## Install

1. Requirements: PHP 8.2+ with mysqli and fileinfo, MySQL 8.x or
   MariaDB 10.11+, Composer, and a web server able to point its document
   root at `public/`.

2. Install PHP dependencies (PHPMailer):

   ```
   composer install
   ```

3. Create the database and a dedicated user, then load the baseline schema
   and seed:

   ```
   mysql -e "CREATE DATABASE meridian CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -e "CREATE USER 'meridian'@'localhost' IDENTIFIED BY 'choose-a-password'"
   mysql -e "GRANT ALL ON meridian.* TO 'meridian'@'localhost'"
   mysql meridian < database/schema.sql
   ```

   Then apply the numbered migrations in order. They carry additive schema
   changes that are not in the baseline, so a fresh install needs them too:

   ```
   for f in database/migrations/*.sql; do mysql meridian < "$f"; done
   ```

4. Configure:

   ```
   cp config.example.php config.php
   ```

   Fill in the database credentials, a random 64 hex character `app.key`
   (`php -r "echo bin2hex(random_bytes(32));"`), the base URL, and SMTP
   settings if email should really send. With `mail.enabled` false,
   messages append to `storage/logs/mail.log` instead.

5. Point the web server document root at `public/`. Apache reads the
   included `.htaccess`; for nginx, route everything that is not an
   existing file under `public/assets/` to `public/index.php`. For a quick
   local run:

   ```
   php -S localhost:8080 -t public
   ```

   (The PHP built-in server needs a router script for pretty URLs; any
   request that is not a real file must reach `public/index.php`.)

6. Make `storage/` writable by the web server user.

7. Schedule the certification reminder job daily:

   ```
   0 7 * * * php /path/to/meridian/cron/certification_reminders.php
   ```

## First sign in

The seed creates one administrator, username `admin`, password
`ChangeMe!12345`. The first sign in forces a password change. Create real
accounts from Admin, Users and Access, or through the onboarding wizard in
the People directory.

## Roles, permissions, visibility

Three built-in roles ship seeded: Administrator, Manager, Staff. What a
user may do is a permission set: role defaults plus per-person grant or
revoke overrides. What a user sees (navigation areas and dashboard
widgets) is module visibility: role defaults plus per-person overrides.
Both are edited under Admin, Roles and Permissions. Hiding a module also
blocks direct URL access because the same resolution runs server-side in
the middleware.

## Security notes

Single front controller with a declarative middleware pipeline (auth,
CSRF on every write, rbac, module visibility, login throttling with
progressive lockout). Hardened session cookies with idle and absolute
timeouts. Bcrypt passwords with a forced first-login change and hashed
single-use reset tokens. Every query is a prepared statement; every
rendered value passes the `e()` escaper. Uploads are validated by
extension, detected MIME and size, stored under random names outside the
document root, and served only through short-lived signed tokens with the
permission re-checked at download time. Material state changes write an
audit row.
