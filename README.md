# Meridian

A workplace management system for internal teams: people directory with an
org chart and a per-person document vault, attendance with a live team
board, leave with balances and an approvals queue, projects and tasks with
a Kanban board, an asset register with an assignment workflow, and a
certifications area with a cross-team skills matrix and scheduled expiry
reminders. Light and dark themes from one set of design tokens, a command
palette (Ctrl or Cmd plus K), slide-over drawers for day-to-day actions,
two-state notifications, opt-in two-factor authentication, role-based access
control with per-person overrides, and a complete audit trail.

The product name is a placeholder: rename it by changing the `org_name`
value on the Settings screen, no code changes involved.

## Stack

Procedural PHP 8.2+ with mysqli prepared statements, MariaDB 10.4+ (or
MySQL 8.x, see the note under Database), Bootstrap 5.3, jQuery 3.7,
DataTables, Parsley, DOMPurify, SweetAlert2, PHPMailer, a local QR library,
Inter and Font Awesome. All frontend libraries ship as pinned local copies
under `public/assets/vendor/`, so the app renders identically with or
without internet access. No framework, no ORM, no npm, no build step.

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

## Requirements

* PHP 8.2 or newer, with these extensions enabled: `mysqli`, `mbstring`,
  `fileinfo`, `json`, `session`, `openssl`. All of these ship with a
  standard PHP build and with XAMPP; confirm with `php -m`.
* MariaDB 10.4 or newer. MySQL 8.x also works for the baseline schema, but
  see the Database note about migration syntax.
* Composer, to install the two PHP libraries (PHPMailer for email and
  otphp for two-factor).
* A web server that serves `public/` as its document root and honours the
  included `.htaccess` (Apache), or an equivalent rewrite rule (nginx).

## Install

1. Install PHP dependencies. This installs PHPMailer and the otphp
   two-factor library into `vendor/`, which stays gitignored:

   ```
   composer install
   ```

2. Create the database and a dedicated user, then load the baseline schema
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

   On Windows, run each file by hand in order, for example:

   ```
   mysql meridian < database\migrations\001_notification_two_state.sql
   mysql meridian < database\migrations\002_totp_recovery.sql
   ```

3. Configure:

   ```
   cp config.example.php config.php
   ```

   Fill in the database credentials, a random 64 hex character `app.key`
   (`php -r "echo bin2hex(random_bytes(32));"`), the base URL, and SMTP
   settings if email should really send. Set `https` to true in production
   so session cookies are marked Secure. With `mail.enabled` false, messages
   append to `storage/logs/mail.log` instead of being sent.

4. Point the web server document root at `public/` (see Web server below),
   then make `storage/` writable by the web server user.

5. Schedule the certification reminder job to run daily (see Scheduled job).

## Web server

The application is a single front controller. Every request that is not a
real file under `public/` must reach `public/index.php`.

### Apache

The included `public/.htaccess` does this, but Apache must be allowed to
read it and must have the needed modules. In your virtual host or directory
block:

```
<Directory "/path/to/meridian/public">
    AllowOverride All
    Require all granted
</Directory>
```

`AllowOverride All` is essential. If it is `None`, the `.htaccess` is
ignored, routing does not work, and writes appear to do nothing. Enable
these modules (`a2enmod` on Debian, or the `LoadModule` lines in
`httpd.conf` on XAMPP): `mod_rewrite`, `mod_headers`, `mod_dir`.

The `.htaccess` sets `DirectorySlash Off` on purpose. Some routes, notably
`/assets` (the asset register), share a name with a real directory under
`public/` (`public/assets/`). With the default `DirectorySlash On`, Apache
answers a POST to `/assets` with a 301 redirect to `/assets/`, and a 301
turns the POST into a GET and drops the body, so the create never reaches
the controller. `DirectorySlash Off` stops that. Do not remove it.

### nginx

```
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
}
```

### Quick local run

```
php -S localhost:8080 -t public
```

The PHP built-in server needs a small router script for pretty URLs; any
request that is not a real file must include `public/index.php`. Note that
the built-in server has no `mod_dir`, so it will not show the Apache
redirect issue described above; test writes on your real Apache setup.

## Running on XAMPP (Windows)

1. Copy the project under `C:\xampp\htdocs\`, for example
   `C:\xampp\htdocs\meridian`. Make sure hidden files are copied too, so
   `public\.htaccess` is present; it is easy to miss because it is a dotfile.
2. Point a virtual host or the document root at the `public` folder, with
   `AllowOverride All` as shown above, and confirm `mod_rewrite`,
   `mod_headers` and `mod_dir` are enabled in `httpd.conf`.
3. Start Apache and MySQL from the XAMPP control panel. Create the database
   and run the schema and migrations through phpMyAdmin or the `mysql`
   client in `C:\xampp\mysql\bin`.
4. After pulling new code, restart Apache from the control panel so the PHP
   opcode cache does not keep serving old files, and hard refresh the
   browser (Ctrl and F5) so it loads updated scripts.

## First sign in

The seed creates one administrator, username `admin`, password
`ChangeMe!12345`. The first sign in forces a password change. Create real
accounts from Admin, Users and Access, or through the onboarding wizard in
the People directory.

## Scheduled job

The certification reminder job reconciles expiry statuses and emails holders
at the configured day marks before expiry.

On Linux, add a daily cron entry:

```
0 7 * * * php /path/to/meridian/cron/certification_reminders.php
```

On Windows, add a daily task in Task Scheduler that runs:

```
C:\xampp\php\php.exe C:\xampp\htdocs\meridian\cron\certification_reminders.php
```

## Roles, permissions, visibility

Three built-in roles ship seeded: Administrator, Manager, Staff. What a
user may do is a permission set: role defaults plus per-person grant or
revoke overrides. What a user sees (navigation areas and dashboard
widgets) is module visibility: role defaults plus per-person overrides.
Both are edited under Admin, Roles and Permissions. Hiding a module also
blocks direct URL access because the same resolution runs server-side in
the middleware.

## Two-factor authentication

Two-factor is opt-in per user from the account menu, Two-factor
authentication. A user scans a QR code with an authenticator app, confirms a
code, and receives ten one-time recovery codes shown once. After that, sign
in takes a password step and then a code step. Recovery codes are stored
only as bcrypt hashes and each works once. Disabling re-checks the password.

Administrators can be required to use two-factor: turn on the setting under
Admin, Settings, Require two-factor authentication for administrator
accounts. Any admin who has not enrolled is then sent to the enrolment page
on their next request until they enrol. The QR code is generated locally in
the browser, so the secret is never sent to a third party.

## Database note

The migrations under `database/migrations/` use MariaDB clauses such as
`ADD COLUMN IF NOT EXISTS` and `DROP INDEX IF EXISTS`, which make them safe
to re-run. MySQL 8 does not support those clauses, so on MySQL 8 you would
apply each migration once and remove the `IF [NOT] EXISTS` guards, or use
MariaDB, which is the tested target. The baseline `schema.sql` is standard
SQL and loads on both.

## Security notes

Single front controller with a declarative middleware pipeline (auth,
CSRF on every write, rbac, module visibility, two-factor enforcement for
admins, and login throttling with progressive lockout). Hardened session
cookies with idle and absolute timeouts, and the session id regenerates at
login and at the two-factor step. Bcrypt passwords with a forced first-login
change and hashed single-use reset tokens. Optional TOTP two-factor with
bcrypt-hashed one-time recovery codes. Every query is a prepared statement;
every rendered value passes the `e()` escaper, and rendered pages are sent
with a no-store cache header so a page and its CSRF token are never served
stale. Uploads are validated by extension, detected MIME and size, stored
under random names outside the document root, and served only through
short-lived signed tokens with the permission re-checked at download time.
Material state changes write an audit row.

## Troubleshooting

* A saved record (an asset, for example) shows a success message but does
  not appear. On Apache this is almost always the `DirectorySlash` redirect
  described under Web server: confirm `DirectorySlash Off` is in the served
  `.htaccess` and that `AllowOverride All` lets Apache read it. Admin,
  Settings has a System check panel that runs a live write test and reports
  the connected database, so you can confirm writes persist.
* A change to the code or styles does not take effect. Restart the web
  server so the PHP opcode cache clears, and hard refresh the browser
  (Ctrl and F5). Rendered pages are already sent no-store, and scripts are
  version-stamped, so a plain reload is usually enough after a restart.
* Writes report a security token mismatch. The page was loaded from cache
  with an old token; reload it. Pages are sent no-store to prevent this.
* Two-factor or email features error about a missing class. Run
  `composer install` so `vendor/` is present.
