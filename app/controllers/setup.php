<?php
// First-run setup wizard. Runs once on a fresh install, before normal login
// is possible, gated by the setup_completed setting. Captures the company
// profile, the first administrator, the set of enabled optional modules, and
// the basic operating settings, then marks setup complete so the wizard is no
// longer reachable. The router redirects here until setup is done.

declare(strict_types=1);

// Optional modules a company can enable, with a one-line description each.
function setup_optional_modules(): array
{
    return [
        'attendance'     => 'Daily check in and out, a team board, and timesheets.',
        'leave'          => 'Leave requests, balances, approvals and a shared calendar.',
        'projects'       => 'Projects and tasks with a Kanban board and comments.',
        'assets'         => 'An asset register with an assignment workflow and history.',
        'certifications' => 'Staff certifications with a skills matrix and expiry reminders.',
        'company_docs'   => 'A compliance library of company documents and references with expiry tracking.',
        'clients'        => 'A register of clients and procuring entities with an opportunity pipeline.',
        'suppliers'      => 'A register of suppliers, manufacturers and their authorizations.',
        'tenders'        => 'The full tender response life, assembled from the library, with a compliance matrix, pricing and outcomes.',
        'procurement'    => 'Item requests, consolidated requisitions, approvals and fund release, purchase orders and goods receipt.',
        'budgets'        => 'Department budgets that requisitions and purchase orders draw against.',
        'expenses'       => 'Expense claims with receipts and approval, and a petty cash float with a running balance.',
        'payroll'        => 'Personal records, a configurable pay component engine, salary structures, payroll runs, payslips and staff loans.',
        'contracts'      => 'Contracts and agreements with renewal alerts and the signed contract file.',
    ];
}

function index(): void
{
    render('setup/wizard', [
        'pageTitle' => 'Set up ' . e(config('app.name', 'Meridian')),
        'optionalModules' => setup_optional_modules(),
        'timezones' => DateTimeZone::listIdentifiers(),
    ], false);
}

function submit(): void
{
    if (setup_completed()) {
        json_err('Setup has already been completed.', 409);
    }

    // 1. Company profile.
    $legalName = trim((string)($_POST['legal_name'] ?? ''));
    $companyEmail = trim((string)($_POST['company_email'] ?? ''));
    if ($legalName === '') {
        json_err('The company legal name is required.', 422, ['legal_name' => 'Required.']);
    }
    if ($companyEmail !== '' && filter_var($companyEmail, FILTER_VALIDATE_EMAIL) === false) {
        json_err('Enter a valid company email address.', 422, ['company_email' => 'Invalid email.']);
    }

    // 2. Administrator account.
    $adminUser = trim((string)($_POST['admin_username'] ?? ''));
    $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
    $adminPass = (string)($_POST['admin_password'] ?? '');
    if (mb_strlen($adminUser) < 3) {
        json_err('The administrator username must be at least 3 characters.', 422, ['admin_username' => 'At least 3 characters.']);
    }
    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
        json_err('Enter a valid administrator email address.', 422, ['admin_email' => 'Invalid email.']);
    }
    require_once APP_ROOT . '/app/controllers/auth.php';
    $why = [];
    if (!auth_password_ok($adminPass, $why)) {
        json_err(implode(' ', $why), 422, ['admin_password' => implode(' ', $why)]);
    }

    // 4. Basics (validated before any write).
    $timezone = (string)($_POST['timezone'] ?? '');
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        json_err('Choose a valid timezone.', 422, ['timezone' => 'Invalid timezone.']);
    }
    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'MWK')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        json_err('Enter a three letter currency code, for example MWK.', 422, ['currency' => 'Three letters.']);
    }
    $fyStart = trim((string)($_POST['financial_year_start'] ?? '01-01'));
    if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $fyStart)) {
        json_err('The financial year start must be in MM-DD form.', 422, ['financial_year_start' => 'Use MM-DD.']);
    }
    $lateThreshold = trim((string)($_POST['late_threshold'] ?? '08:30'));
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $lateThreshold)) {
        json_err('The late threshold must be a valid time.', 422, ['late_threshold' => 'Invalid time.']);
    }

    // Optional logo, stored web reachable since it is public branding, not
    // sensitive. Validated by extension and detected MIME.
    $logoPath = null;
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime || $file['size'] > 2 * 1024 * 1024) {
            json_err('The logo must be a PNG or JPG image under 2 MB.', 422, ['logo' => 'PNG or JPG, under 2 MB.']);
        }
        $imgDir = APP_ROOT . '/public/assets/img';
        if (!is_dir($imgDir)) {
            mkdir($imgDir, 0755, true);
        }
        $stored = 'brand-logo.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (move_uploaded_file($file['tmp_name'], $imgDir . '/' . $stored)) {
            $logoPath = '/assets/img/' . $stored;
        }
    }

    // 3. Module enablement.
    $selected = is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [];
    $valid = array_keys(setup_optional_modules());

    // Everything validated. Apply.
    $tradingName = trim((string)($_POST['trading_name'] ?? '')) ?: $legalName;
    $set = function (string $key, ?string $value): void {
        db_query(
            'INSERT INTO settings (setting_key, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, (string)$value]
        );
    };
    $set('org_name', $tradingName);
    $set('legal_name', $legalName);
    $set('trading_name', $tradingName);
    $set('reg_number', trim((string)($_POST['reg_number'] ?? '')));
    $set('tax_id', trim((string)($_POST['tax_id'] ?? '')));
    $set('phys_address', trim((string)($_POST['phys_address'] ?? '')));
    $set('postal_address', trim((string)($_POST['postal_address'] ?? '')));
    $set('company_phone', trim((string)($_POST['company_phone'] ?? '')));
    $set('company_email', $companyEmail);
    if ($logoPath !== null) {
        $set('logo_path', $logoPath);
    }
    $set('timezone', $timezone);
    $set('currency', $currency);
    $set('financial_year_start', $fyStart);
    $set('late_threshold', $lateThreshold);

    // Mirror the captured identity into the company_profile row so the
    // compliance library and tender modules read a populated profile from the
    // start. Guarded because the profile table arrives with a later migration
    // and an install may not have applied it yet.
    if (db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'company_profile'")) {
        db_query(
            'INSERT INTO company_profile (id, legal_name, trading_name, reg_number, tax_id, phys_address, postal_address, phone, email, logo_path)
             VALUES (1,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE legal_name = VALUES(legal_name), trading_name = VALUES(trading_name),
                 reg_number = VALUES(reg_number), tax_id = VALUES(tax_id), phys_address = VALUES(phys_address),
                 postal_address = VALUES(postal_address), phone = VALUES(phone), email = VALUES(email),
                 logo_path = COALESCE(VALUES(logo_path), logo_path)',
            [
                $legalName, $tradingName,
                trim((string)($_POST['reg_number'] ?? '')) ?: null,
                trim((string)($_POST['tax_id'] ?? '')) ?: null,
                trim((string)($_POST['phys_address'] ?? '')) ?: null,
                trim((string)($_POST['postal_address'] ?? '')) ?: null,
                trim((string)($_POST['company_phone'] ?? '')) ?: null,
                $companyEmail ?: null,
                $logoPath,
            ]
        );
    }

    // Repurpose the seeded administrator as the company's first admin.
    $adminRoleId = (int)db_val("SELECT id FROM roles WHERE role_key = 'admin'");
    $existingAdmin = db_row('SELECT id, person_id FROM users WHERE role_id = ? ORDER BY id LIMIT 1', [$adminRoleId]);
    $hash = password_hash($adminPass, PASSWORD_BCRYPT);
    if ($existingAdmin) {
        db_query(
            'UPDATE users SET username = ?, email = ?, password_hash = ?, must_change_password = 0, is_active = 1 WHERE id = ?',
            [$adminUser, $adminEmail, $hash, (int)$existingAdmin['id']]
        );
        if ($existingAdmin['person_id']) {
            db_query('UPDATE people SET email = ? WHERE id = ?', [$adminEmail, (int)$existingAdmin['person_id']]);
        }
        $adminId = (int)$existingAdmin['id'];
    } else {
        db_query(
            'INSERT INTO users (username, email, password_hash, role_id, is_active, must_change_password) VALUES (?,?,?,?,1,0)',
            [$adminUser, $adminEmail, $hash, $adminRoleId]
        );
        $adminId = db_insert_id();
    }

    // Module enablement: core stays on, optional set to the selection.
    foreach ($valid as $key) {
        db_query(
            'UPDATE modules SET is_enabled = ? WHERE module_key = ? AND is_core = 0',
            [in_array($key, $selected, true) ? 1 : 0, $key]
        );
    }

    date_default_timezone_set($timezone);
    $set('setup_completed', '1');
    audit('setup.completed', 'settings', null, ['company' => $legalName, 'modules' => array_values(array_intersect($selected, $valid))]);

    json_ok(['redirect' => '/login']);
}
