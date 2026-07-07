<?php
// Admin: application settings and leave types. Every tunable lives here,
// nothing an administrator should adjust is hardcoded.

declare(strict_types=1);

function index(): void
{
    $settings = [];
    foreach (db_all('SELECT setting_key, value FROM settings') as $row) {
        $settings[$row['setting_key']] = $row['value'];
    }
    render('admin/settings', [
        'pageTitle' => 'Settings',
        'breadcrumbs' => ['Admin' => null, 'Settings' => null],
        'settings' => $settings,
        'leaveTypes' => db_all('SELECT * FROM leave_types ORDER BY id'),
    ]);
}

function save(): void
{
    $in = input();
    $editable = [
        'org_name' => fn($v) => $v !== '' && mb_strlen($v) <= 100,
        'late_threshold' => fn($v) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v),
        'timezone' => fn($v) => in_array($v, DateTimeZone::listIdentifiers(), true),
        'leave_year_start' => fn($v) => (bool)preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $v),
        'cert_reminder_days' => fn($v) => (bool)preg_match('/^\d{1,3}(,\d{1,3})*$/', $v),
        'cert_notify_manager' => fn($v) => in_array($v, ['0', '1'], true),
        'mail_notifications' => fn($v) => in_array($v, ['0', '1'], true),
        'totp_required_admin' => fn($v) => in_array($v, ['0', '1'], true),
    ];
    $changed = [];
    foreach ($editable as $key => $isValid) {
        if (!array_key_exists($key, $in)) {
            continue;
        }
        $value = trim((string)$in[$key]);
        if (!$isValid($value)) {
            json_err('The value for ' . str_replace('_', ' ', $key) . ' is not valid.', 422, [$key => 'Not valid.']);
        }
        db_query(
            'INSERT INTO settings (setting_key, value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
        $changed[] = $key;
    }
    audit('settings.update', 'settings', null, ['keys' => $changed]);
    json_ok();
}

// Create or update a leave type. Existing balances keep their allocation;
// the default applies to balances created after the change.
function save_leave_type(): void
{
    $in = input();
    $errors = validate($in, [
        'name' => 'required|max:80',
        'default_annual_allocation' => 'required|numeric',
    ]);
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $alloc = (float)$in['default_annual_allocation'];
    if ($alloc < 0 || $alloc > 366) {
        json_err('The allocation must be between 0 and 366.', 422, ['default_annual_allocation' => 'Out of range.']);
    }
    $isPaid = (int)(bool)($in['is_paid'] ?? 1);
    $typeId = in_int('id');

    if ($typeId) {
        if (!db_val('SELECT id FROM leave_types WHERE id = ?', [$typeId])) {
            json_err('That leave type does not exist.', 404);
        }
        $clash = db_val('SELECT id FROM leave_types WHERE name = ? AND id <> ?', [in_str('name'), $typeId]);
        if ($clash) {
            json_err('A leave type with that name already exists.', 422, ['name' => 'Already exists.']);
        }
        db_query(
            'UPDATE leave_types SET name = ?, is_paid = ?, default_annual_allocation = ? WHERE id = ?',
            [in_str('name'), $isPaid, $alloc, $typeId]
        );
        audit('leave_type.update', 'leave_type', $typeId, ['name' => in_str('name'), 'allocation' => $alloc]);
    } else {
        if (db_val('SELECT id FROM leave_types WHERE name = ?', [in_str('name')])) {
            json_err('A leave type with that name already exists.', 422, ['name' => 'Already exists.']);
        }
        db_query(
            'INSERT INTO leave_types (name, is_paid, default_annual_allocation) VALUES (?,?,?)',
            [in_str('name'), $isPaid, $alloc]
        );
        $typeId = db_insert_id();
        audit('leave_type.create', 'leave_type', $typeId, ['name' => in_str('name'), 'allocation' => $alloc]);
    }
    json_ok(['leave_type_id' => $typeId]);
}
