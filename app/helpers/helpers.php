<?php
// Shared helper functions. Procedural by design. Every controller, view and
// middleware relies on these; there is one way to escape output, one way to
// return JSON, one way to run a query, one way to audit.

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Config and database
// ---------------------------------------------------------------------------

function config(string $path, $default = null)
{
    $node = $GLOBALS['config'];
    foreach (explode('.', $path) as $seg) {
        if (!is_array($node) || !array_key_exists($seg, $node)) {
            return $default;
        }
        $node = $node[$seg];
    }
    return $node;
}

function db(): mysqli
{
    return $GLOBALS['db'];
}

// Run a prepared statement. Returns mysqli_result for SELECT-like statements,
// true for writes. Types are inferred: int -> i, float -> d, everything else -> s.
function db_query(string $sql, array $params = [])
{
    try {
        $stmt = db()->prepare($sql);
        if ($params) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result === false ? true : $result;
    } catch (mysqli_sql_exception $ex) {
        // Name the failing statement in the log so a rejected write (for
        // example a strict-mode date or an enum mismatch) is easy to trace.
        error_log('SQL failed: ' . $ex->getMessage() . ' | ' . preg_replace('/\s+/', ' ', trim($sql)));
        throw $ex;
    }
}

function db_row(string $sql, array $params = []): ?array
{
    $res = db_query($sql, $params);
    $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function db_all(string $sql, array $params = []): array
{
    $res = db_query($sql, $params);
    return $res instanceof mysqli_result ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_val(string $sql, array $params = [], $default = null)
{
    $row = db_row($sql, $params);
    return $row ? array_values($row)[0] : $default;
}

function db_exec(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return db()->affected_rows;
}

function db_insert_id(): int
{
    return (int) db()->insert_id;
}

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Append a version stamp to a local asset URL based on the file's last
// modified time, so browsers fetch a fresh copy whenever the file changes
// and never serve a stale cached script or stylesheet after a deploy.
function asset_url(string $path): string
{
    $full = APP_ROOT . '/public' . $path;
    $version = is_file($full) ? filemtime($full) : time();
    return $path . '?v=' . $version;
}

function json_out($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $message, int $status = 400, array $fields = []): never
{
    json_out(['ok' => false, 'error' => $message, 'fields' => (object)$fields], $status);
}

function json_ok(array $extra = []): never
{
    json_out(array_merge(['ok' => true], $extra));
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_api_request(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    return $xhr || str_contains($accept, 'application/json');
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// Render a view inside the application shell. $layout false renders bare
// (login and other unauthenticated pages).
function render(string $view, array $data = [], bool $layout = true): never
{
    // HTML pages carry a per-session CSRF token and live data, so they must
    // never be served from the browser or a proxy cache. A cached page would
    // submit a stale token and every write would be rejected as a security
    // token mismatch. These headers force a fresh page and a fresh token on
    // every visit.
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    extract($data, EXTR_SKIP);
    $viewFile = APP_ROOT . '/app/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit('Missing view: ' . e($view));
    }
    if ($layout) {
        require APP_ROOT . '/app/partials/layout_top.php';
        require $viewFile;
        require APP_ROOT . '/app/partials/layout_bottom.php';
    } else {
        require $viewFile;
    }
    exit;
}

function render_error(int $status, string $title, string $message): never
{
    http_response_code($status);
    if (is_api_request()) {
        json_err($message, $status);
    }
    $inShell = !empty($_SESSION['user_id']);
    render(
        'errors/error',
        ['pageTitle' => $title, 'errTitle' => $title, 'errMessage' => $message, 'errStatus' => $status],
        $inShell
    );
}

// ---------------------------------------------------------------------------
// Request helpers
// ---------------------------------------------------------------------------

function request_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Body for AJAX writes: JSON body preferred, form fallback.
function input(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    if ($raw !== '' && str_contains($ct, 'application/json')) {
        $decoded = json_decode($raw, true);
        $cached = is_array($decoded) ? $decoded : [];
    } else {
        $cached = $_POST;
    }
    return $cached;
}

function in_str(string $key, string $default = ''): string
{
    $v = input()[$key] ?? $default;
    return trim(is_scalar($v) ? (string)$v : $default);
}

function in_int(string $key, ?int $default = null): ?int
{
    $v = input()[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return (int)$v;
}

// ---------------------------------------------------------------------------
// Validation. Rules: required, email, date, time, int, numeric, min:n, max:n
// (string length), in:a;b;c, after:field. Returns [field => message].
// ---------------------------------------------------------------------------

function validate(array $data, array $rules): array
{
    $errors = [];
    foreach ($rules as $field => $ruleStr) {
        $value = trim((string)($data[$field] ?? ''));
        foreach (explode('|', $ruleStr) as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            if ($name !== 'required' && $value === '') {
                continue;
            }
            $fail = match ($name) {
                'required' => $value === '' ? 'This field is required.' : null,
                'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Enter a valid email address.' : null,
                'date'     => !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !checkdate((int)substr($value,5,2), (int)substr($value,8,2), (int)substr($value,0,4)) ? 'Enter a valid date.' : null,
                'time'     => !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? 'Enter a valid time.' : null,
                'int'      => !preg_match('/^-?\d+$/', $value) ? 'Enter a whole number.' : null,
                'numeric'  => !is_numeric($value) ? 'Enter a number.' : null,
                'min'      => mb_strlen($value) < (int)$arg ? 'Must be at least ' . $arg . ' characters.' : null,
                'max'      => mb_strlen($value) > (int)$arg ? 'Must be at most ' . $arg . ' characters.' : null,
                'in'       => !in_array($value, explode(';', (string)$arg), true) ? 'Invalid value.' : null,
                'after'    => ($data[$arg] ?? '') !== '' && $value < $data[$arg] ? 'Must be on or after ' . str_replace('_', ' ', (string)$arg) . '.' : null,
                default    => null,
            };
            if ($fail !== null) {
                $errors[$field] = $fail;
                break;
            }
        }
    }
    return $errors;
}

// ---------------------------------------------------------------------------
// Settings (database backed, cached per request)
// ---------------------------------------------------------------------------

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

// Single-row company profile, the identity the tender module and branding
// draw from. Loaded once per request. Returns an empty-ish row when the
// migration has run but the row is somehow absent, so callers never null out.
function company_profile(): array
{
    static $row = null;
    if ($row === null) {
        $row = db_row('SELECT * FROM company_profile WHERE id = 1') ?? [];
    }
    return $row;
}

// ---------------------------------------------------------------------------
// Auth, permissions, module visibility
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return $user = null;
    }
    $user = db_row(
        'SELECT u.*, r.role_key, r.display_name AS role_name,
                p.first_name, p.last_name, p.job_title, p.department
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN people p ON p.id = u.person_id
         WHERE u.id = ? AND u.is_active = 1',
        [(int)$_SESSION['user_id']]
    );
    return $user;
}

function user_display_name(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) {
        return '';
    }
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    return $name !== '' ? $name : $user['username'];
}

// Effective permission: role defaults, then per-user overrides on top.
function user_permissions(?array $user = null): array
{
    static $cache = [];
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    $uid = (int)$user['id'];
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }
    $set = [];
    foreach (db_all(
        'SELECT p.permission_key FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
        [(int)$user['role_id']]
    ) as $row) {
        $set[$row['permission_key']] = true;
    }
    foreach (db_all(
        'SELECT p.permission_key, o.effect FROM user_permission_overrides o
         JOIN permissions p ON p.id = o.permission_id WHERE o.user_id = ?',
        [$uid]
    ) as $row) {
        if ($row['effect'] === 'grant') {
            $set[$row['permission_key']] = true;
        } else {
            unset($set[$row['permission_key']]);
        }
    }
    return $cache[$uid] = $set;
}

function user_can(string $permissionKey, ?array $user = null): bool
{
    return isset(user_permissions($user)[$permissionKey]);
}

// Module catalog, loaded once.
function module_catalog(): array
{
    static $catalog = null;
    return $catalog ??= require APP_ROOT . '/app/config/modules.php';
}

// Instance-level module enablement (the modules table). This is the hard gate
// that sits above role and per-user visibility: a disabled module does not
// exist for anyone. A key that is not in the table (dashboard widgets, for
// example) defaults to enabled, since only navigation modules are gated here.
function module_enabled(string $moduleKey): bool
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db_all('SELECT module_key, is_enabled FROM modules') as $row) {
            $map[$row['module_key']] = (bool)$row['is_enabled'];
        }
    }
    return $map[$moduleKey] ?? true;
}

// Map a permission key to the optional module it belongs to, so a route with
// only an rbac permission can still be gated by instance enablement. Returns
// null for permissions that belong to a core module (always enabled) so no
// gate is injected for them.
function module_for_permission(string $permissionKey): ?string
{
    static $map = [
        'attendance'     => 'attendance',
        'leave'          => 'leave',
        'projects'       => 'projects',
        'tasks'          => 'projects',
        'assets'         => 'assets',
        'certifications' => 'certifications',
        'company_docs'   => 'company_docs',
        'clients'        => 'clients',
        'suppliers'      => 'suppliers',
    ];
    $prefix = explode('.', $permissionKey, 2)[0];
    return $map[$prefix] ?? null;
}

// Whether first-run setup has been completed.
function setup_completed(): bool
{
    return setting('setup_completed', '0') === '1';
}

// Effective visibility: instance enablement first, then role default row,
// overridden by a per-user row. A module with no visibility row defaults to
// visible (permission still gates it), but a disabled module never appears.
function visible_modules(?array $user = null): array
{
    static $cache = [];
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    $uid = (int)$user['id'];
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }
    $vis = [];
    foreach (db_all(
        "SELECT module_key, is_visible FROM module_visibility WHERE scope = 'role' AND scope_id = ?",
        [(int)$user['role_id']]
    ) as $row) {
        $vis[$row['module_key']] = (bool)$row['is_visible'];
    }
    foreach (db_all(
        "SELECT module_key, is_visible FROM module_visibility WHERE scope = 'user' AND scope_id = ?",
        [$uid]
    ) as $row) {
        $vis[$row['module_key']] = (bool)$row['is_visible'];
    }
    $result = [];
    foreach (module_catalog() as $key => $meta) {
        if (!module_enabled($key)) {
            continue; // instance gate: disabled modules do not exist for anyone
        }
        $visible = $vis[$key] ?? true;
        $permitted = empty($meta['permission']) || user_can($meta['permission'], $user);
        if ($visible && $permitted) {
            $result[$key] = $meta;
        }
    }
    return $cache[$uid] = $result;
}

function module_visible(string $moduleKey, ?array $user = null): bool
{
    return isset(visible_modules($user)[$moduleKey]);
}

// ---------------------------------------------------------------------------
// Audit and notifications
// ---------------------------------------------------------------------------

function audit(string $action, string $entity, ?int $entityId = null, array $detail = []): void
{
    $user = defined('MERIDIAN_CLI') ? null : current_user();
    db_query(
        'INSERT INTO audit_log (user_id, action, entity, entity_id, detail, ip_address) VALUES (?,?,?,?,?,?)',
        [
            $user['id'] ?? null,
            $action,
            $entity,
            $entityId,
            json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            defined('MERIDIAN_CLI') ? 'cli' : request_ip(),
        ]
    );
}

function notify(int $userId, string $body, ?string $link = null, ?string $moduleKey = null): void
{
    db_query(
        'INSERT INTO notifications (user_id, body, link, module_key) VALUES (?,?,?,?)',
        [$userId, $body, $link, $moduleKey]
    );
}

// Broadcast to every active user holding a role. Rows are materialized per
// user so read state stays a simple flag; role_id records the provenance.
function notify_role(string $roleKey, string $body, ?string $link = null, ?string $moduleKey = null): void
{
    $roleId = db_val('SELECT id FROM roles WHERE role_key = ?', [$roleKey]);
    if ($roleId === null) {
        return;
    }
    foreach (db_all('SELECT id FROM users WHERE role_id = ? AND is_active = 1', [(int)$roleId]) as $u) {
        db_query(
            'INSERT INTO notifications (user_id, role_id, body, link, module_key) VALUES (?,?,?,?,?)',
            [(int)$u['id'], (int)$roleId, $body, $link, $moduleKey]
        );
    }
}

// Notify the user account linked to a person, if any.
function notify_person(int $personId, string $body, ?string $link = null, ?string $moduleKey = null): void
{
    $uid = db_val('SELECT id FROM users WHERE person_id = ? AND is_active = 1', [$personId]);
    if ($uid !== null) {
        notify((int)$uid, $body, $link, $moduleKey);
    }
}

// ---------------------------------------------------------------------------
// Mail. When mail.enabled is false, messages append to storage/logs/mail.log
// so development runs never send real email.
// ---------------------------------------------------------------------------

function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    if (setting('mail_notifications', '1') !== '1') {
        return true;
    }
    $org = setting('org_name', config('app.name', 'Meridian'));
    $wrapped = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#0F172A">'
        . '<h2 style="color:#4F46E5;margin-top:0">' . e($org) . '</h2>'
        . $htmlBody
        . '<p style="color:#64748B;font-size:12px;margin-top:32px">This is an automated message from ' . e($org) . '. Please do not reply.</p>'
        . '</div>';

    if (!config('mail.enabled', false) || !class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $line = sprintf("[%s] to=%s <%s> subject=%s\n%s\n----\n", date('c'), $toName, $toEmail, $subject, strip_tags($htmlBody));
        @file_put_contents(APP_ROOT . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = config('mail.host');
        $mail->Port       = (int)config('mail.port', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = config('mail.username');
        $mail->Password   = config('mail.password');
        $mail->SMTPSecure = config('mail.encryption', 'tls');
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(config('mail.from_email'), config('mail.from_name', $org));
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $wrapped;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Throwable $ex) {
        error_log('Mail send failed: ' . $ex->getMessage());
        return false;
    }
}

function mail_person(int $personId, string $subject, string $htmlBody): void
{
    $p = db_row('SELECT first_name, last_name, email FROM people WHERE id = ?', [$personId]);
    if ($p && $p['email']) {
        send_mail($p['email'], trim($p['first_name'] . ' ' . $p['last_name']), $subject, $htmlBody);
    }
}

// ---------------------------------------------------------------------------
// Dates
// ---------------------------------------------------------------------------

// Working days between two dates inclusive, excluding Saturday and Sunday.
function working_days(string $startDate, string $endDate): float
{
    $start = new DateTimeImmutable($startDate);
    $end   = new DateTimeImmutable($endDate);
    if ($end < $start) {
        return 0.0;
    }
    $days = 0;
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $dow = (int)$d->format('N');
        if ($dow <= 5) {
            $days++;
        }
    }
    return (float)$days;
}

// ---------------------------------------------------------------------------
// Signed, short-lived file download tokens
// ---------------------------------------------------------------------------

function sign_download(int $documentId, int $userId, int $ttlSeconds = 300): string
{
    $payload = $documentId . '.' . $userId . '.' . (time() + $ttlSeconds);
    $sig = hash_hmac('sha256', $payload, config('app.key', ''));
    return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
}

// Returns [document_id, user_id] or null when invalid or expired.
function verify_download(string $token): ?array
{
    $decoded = base64_decode(strtr($token, '-_', '+/'), true);
    if ($decoded === false) {
        return null;
    }
    $parts = explode('.', $decoded);
    if (count($parts) !== 4) {
        return null;
    }
    [$docId, $userId, $exp, $sig] = $parts;
    $payload = $docId . '.' . $userId . '.' . $exp;
    $expected = hash_hmac('sha256', $payload, config('app.key', ''));
    if (!hash_equals($expected, $sig) || (int)$exp < time()) {
        return null;
    }
    return [(int)$docId, (int)$userId];
}

// Validate and store an uploaded file under a random name in storage/uploads,
// the same gated path the document vault uses. Validation is by extension,
// finfo detected MIME, agreement between the two so a renamed file cannot slip
// through, and size. $allowedExtMime maps an allowed extension to its accepted
// MIME types; pass a narrower map to restrict a field to, say, PDF and images.
// Returns [stored_name, original_name, mime, size_bytes]. Throws a
// RuntimeException carrying a message safe to show the user on any failure.
function store_upload(array $file, ?array $allowedExtMime = null, ?int $maxBytes = null): array
{
    $allowedExtMime = $allowedExtMime ?? [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'odt'  => ['application/vnd.oasis.opendocument.text'],
        'txt'  => ['text/plain'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];
    $maxBytes = $maxBytes ?? (int)config('uploads.max_bytes', 10485760);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No file received or the upload failed.');
    }
    if ($file['size'] > $maxBytes || $file['size'] <= 0) {
        throw new RuntimeException('The file exceeds the maximum size of ' . round($maxBytes / 1048576) . ' MB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowedExtMime[$ext])) {
        throw new RuntimeException('That file type is not allowed.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedExtMime[$ext], true)) {
        throw new RuntimeException('The file content does not match its extension.');
    }
    $storedName = bin2hex(random_bytes(20)) . '.' . $ext;
    $dir = rtrim((string)config('uploads.dir'), '/');
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not prepare the uploads directory.');
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $storedName)) {
        throw new RuntimeException('Could not store the file. Check the uploads directory permissions.');
    }
    return [
        'stored_name'   => $storedName,
        'original_name' => mb_substr((string)$file['name'], 0, 200),
        'mime'          => $mime,
        'size_bytes'    => (int)$file['size'],
    ];
}

// Stream a stored file from storage/uploads as a gated download and exit.
// The permission decision is the caller's; this only serves the bytes once
// the caller has authorized the request. $storedName and $originalName come
// from the owning table row.
function stream_stored_file(string $storedName, string $originalName, string $mime): never
{
    $path = rtrim((string)config('uploads.dir'), '/') . '/' . $storedName;
    if (!is_file($path)) {
        render_error(404, 'Not found', 'The stored file is missing.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $originalName) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

// ---------------------------------------------------------------------------
// Leave balances
// ---------------------------------------------------------------------------

function ensure_leave_balance(int $personId, int $leaveTypeId, int $year): array
{
    $row = db_row(
        'SELECT * FROM leave_balances WHERE person_id = ? AND leave_type_id = ? AND year = ?',
        [$personId, $leaveTypeId, $year]
    );
    if ($row) {
        return $row;
    }
    $alloc = (float)db_val('SELECT default_annual_allocation FROM leave_types WHERE id = ?', [$leaveTypeId], 0);
    db_query(
        'INSERT INTO leave_balances (person_id, leave_type_id, year, allocated, used) VALUES (?,?,?,?,0)',
        [$personId, $leaveTypeId, $year, $alloc]
    );
    return db_row('SELECT * FROM leave_balances WHERE id = ?', [db_insert_id()]);
}

// Derived certification status: expiry always wins over the stored value.
function cert_effective_status(array $cert): string
{
    if (!empty($cert['expires_on']) && $cert['expires_on'] < date('Y-m-d') && $cert['status'] !== 'In Progress') {
        return 'Expired';
    }
    return $cert['status'];
}

// Generic expiry classification for anything that lapses: compliance
// documents, manufacturer authorizations, securities. Returns 'None' when no
// date is set, 'Expired' once past, 'Expiring' inside the warning window, and
// 'Valid' otherwise. A lapsed compliance document can disqualify a bid, so
// this drives the same chip treatment certifications get.
function expiry_status(?string $expiryDate, int $warnDays = 60): string
{
    if (empty($expiryDate)) {
        return 'None';
    }
    $today = new DateTimeImmutable('today');
    $expiry = new DateTimeImmutable($expiryDate);
    if ($expiry < $today) {
        return 'Expired';
    }
    if ($expiry <= $today->modify('+' . $warnDays . ' days')) {
        return 'Expiring';
    }
    return 'Valid';
}
