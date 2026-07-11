<?php
// Contracts and renewals. Agreements tracked with start and end dates, value,
// a renewal reminder window and the signed contract file. Expiring contracts
// surface on the dashboard. Files use the gated storage and download path.

declare(strict_types=1);

function contract_types(): array { return ['Client', 'Supplier', 'Employment', 'Service', 'Lease', 'Other']; }
function contract_statuses(): array { return ['Draft', 'Active', 'Expired', 'Renewed', 'Terminated']; }

function index(): void
{
    render('contracts/index', [
        'pageTitle' => 'Contracts',
        'breadcrumbs' => ['Company' => null, 'Contracts' => null],
        'canManage' => user_can('contracts.manage'),
        'types' => contract_types(),
        'statuses' => contract_statuses(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function list_json(): void
{
    $rows = db_all('SELECT * FROM contracts ORDER BY end_date IS NULL, end_date');
    foreach ($rows as &$c) {
        $c['expiry_state'] = expiry_status($c['end_date'], (int)$c['renewal_reminder_days']);
        $c['days_left'] = $c['end_date'] !== null
            ? (int)floor((strtotime($c['end_date']) - strtotime(date('Y-m-d'))) / 86400)
            : null;
        $c['has_file'] = $c['stored_name'] !== null;
        unset($c['stored_name'], $c['mime'], $c['size_bytes']);
    }
    json_out(['ok' => true, 'contracts' => $rows]);
}

function contract_input(): array
{
    $title = in_str('title');
    $errors = [];
    if ($title === '') {
        $errors['title'] = 'Required.';
    }
    $type = in_array(in_str('contract_type'), contract_types(), true) ? in_str('contract_type') : 'Client';
    $status = in_array(in_str('status'), contract_statuses(), true) ? in_str('status') : 'Active';
    foreach (['start_date', 'end_date'] as $df) {
        if (in_str($df) !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str($df))) {
            $errors[$df] = 'Invalid date.';
        }
    }
    if (in_str('value') !== '' && !is_numeric(in_str('value'))) {
        $errors['value'] = 'Enter a number.';
    }
    if (in_str('renewal_reminder_days') !== '' && !ctype_digit(in_str('renewal_reminder_days'))) {
        $errors['renewal_reminder_days'] = 'Whole days.';
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'title' => mb_substr($title, 0, 200),
        'counterparty' => in_str('counterparty') ?: null,
        'contract_type' => $type,
        'reference' => in_str('reference') ?: null,
        'start_date' => in_str('start_date') ?: null,
        'end_date' => in_str('end_date') ?: null,
        'value' => in_str('value') !== '' ? (float)in_str('value') : null,
        'currency' => strtoupper(in_str('currency')) ?: null,
        'renewal_reminder_days' => in_str('renewal_reminder_days') !== '' ? (int)in_str('renewal_reminder_days') : 60,
        'status' => $status,
        'tender_id' => in_int('tender_id') ?: null,
        'notes' => in_str('notes') ?: null,
    ];
}

function create(): void
{
    if (!user_can('contracts.manage')) {
        json_err('You do not have permission to add contracts.', 403);
    }
    $c = contract_input();
    $file = contracts_take_file();
    db_query(
        'INSERT INTO contracts (title, counterparty, contract_type, reference, start_date, end_date, value, currency, renewal_reminder_days, status, tender_id, stored_name, original_name, mime, size_bytes, notes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $c['title'], $c['counterparty'], $c['contract_type'], $c['reference'], $c['start_date'], $c['end_date'],
            $c['value'], $c['currency'], $c['renewal_reminder_days'], $c['status'], $c['tender_id'],
            $file['stored_name'] ?? null, $file['original_name'] ?? null, $file['mime'] ?? null, $file['size_bytes'] ?? null,
            $c['notes'], (int)current_user()['id'],
        ]
    );
    $id = db_insert_id();
    audit('contract.create', 'contract', $id, ['title' => $c['title']]);
    json_ok(['contract_id' => $id]);
}

function update(string $id): void
{
    if (!user_can('contracts.manage')) {
        json_err('You do not have permission to edit contracts.', 403);
    }
    $existing = db_row('SELECT * FROM contracts WHERE id = ?', [(int)$id]);
    if (!$existing) {
        json_err('That contract does not exist.', 404);
    }
    $c = contract_input();
    $file = contracts_take_file();
    $fileSql = '';
    $fileParams = [];
    if ($file) {
        $fileSql = ', stored_name = ?, original_name = ?, mime = ?, size_bytes = ?';
        $fileParams = [$file['stored_name'], $file['original_name'], $file['mime'], $file['size_bytes']];
        if ($existing['stored_name']) {
            $old = rtrim((string)config('uploads.dir'), '/') . '/' . $existing['stored_name'];
            if (is_file($old)) {
                unlink($old);
            }
        }
    }
    db_query(
        'UPDATE contracts SET title = ?, counterparty = ?, contract_type = ?, reference = ?, start_date = ?, end_date = ?, value = ?, currency = ?, renewal_reminder_days = ?, status = ?, tender_id = ?, notes = ?'
            . $fileSql . ' WHERE id = ?',
        array_merge(
            [$c['title'], $c['counterparty'], $c['contract_type'], $c['reference'], $c['start_date'], $c['end_date'], $c['value'], $c['currency'], $c['renewal_reminder_days'], $c['status'], $c['tender_id'], $c['notes']],
            $fileParams,
            [(int)$id]
        )
    );
    audit('contract.update', 'contract', (int)$id, ['title' => $c['title']]);
    json_ok();
}

function destroy(string $id): void
{
    if (!user_can('contracts.manage')) {
        json_err('You do not have permission to delete contracts.', 403);
    }
    $c = db_row('SELECT * FROM contracts WHERE id = ?', [(int)$id]);
    if (!$c) {
        json_err('That contract does not exist.', 404);
    }
    db_query('DELETE FROM contracts WHERE id = ?', [(int)$id]);
    if ($c['stored_name']) {
        $path = rtrim((string)config('uploads.dir'), '/') . '/' . $c['stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    audit('contract.delete', 'contract', (int)$id, ['title' => $c['title']]);
    json_ok();
}

// Validate and store an optional signed contract file, returning its metadata
// or null when none was uploaded.
function contracts_take_file(): ?array
{
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    try {
        return store_upload($_FILES['file'], [
            'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        ]);
    } catch (RuntimeException $ex) {
        json_err($ex->getMessage(), 422, ['file' => $ex->getMessage()]);
    }
}

function file_link(string $id): void
{
    $c = db_row('SELECT stored_name FROM contracts WHERE id = ? AND stored_name IS NOT NULL', [(int)$id]);
    if (!$c) {
        json_err('That contract has no attached file.', 404);
    }
    $token = sign_download((int)$id, (int)current_user()['id'], 300);
    json_ok(['url' => '/contracts/file/' . $token, 'expires_in' => 300]);
}

function download(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$contractId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $c = db_row('SELECT * FROM contracts WHERE id = ?', [$contractId]);
    if (!$c || !$c['stored_name']) {
        render_error(404, 'Not found', 'That file no longer exists.');
    }
    audit('contract.download', 'contract', $contractId, ['title' => $c['title']]);
    stream_stored_file($c['stored_name'], $c['original_name'], $c['mime']);
}
