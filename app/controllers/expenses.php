<?php
// Expenses and petty cash. An employee submits an expense claim with lines and
// attached receipts; it moves through approval and, once approved, is marked
// reimbursed. Petty cash is a float with issues and replenishments and a
// running balance. Receipts use the gated storage and download path.
//
// Access: a claimant manages and sees their own claims; expenses.view_all sees
// everyone's; expenses.approve decides and reimburses; pettycash.manage runs
// the float.

declare(strict_types=1);

function expense_statuses(): array { return ['Draft', 'Submitted', 'Approved', 'Rejected', 'Reimbursed']; }
function expense_categories(): array { return ['Travel', 'Meals', 'Accommodation', 'Fuel', 'Supplies', 'Communication', 'Other']; }

function expenses_can_approve(): bool { return user_can('expenses.approve'); }

function expenses_can_view(array $claim): bool
{
    if (user_can('expenses.view_all') || expenses_can_approve()) {
        return true;
    }
    return (int)$claim['claimant_id'] === (int)current_user()['id'];
}

// A claim is editable by its owner only while still a draft.
function expenses_can_edit(array $claim): bool
{
    return $claim['status'] === 'Draft' && (int)$claim['claimant_id'] === (int)current_user()['id'];
}

function claim_recalc_total(int $claimId): void
{
    $total = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM expense_lines WHERE claim_id = ?', [$claimId], 0);
    db_query('UPDATE expense_claims SET total = ? WHERE id = ?', [$total, $claimId]);
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

function index(): void
{
    $uid = (int)current_user()['id'];
    $canViewAll = user_can('expenses.view_all') || expenses_can_approve();

    render('expenses/index', [
        'pageTitle' => 'Expenses',
        'breadcrumbs' => ['Finance' => null, 'Expenses' => null],
        'myClaims' => db_all('SELECT * FROM expense_claims WHERE claimant_id = ? ORDER BY created_at DESC', [$uid]),
        'allClaims' => $canViewAll
            ? db_all(
                "SELECT ec.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS claimant
                 FROM expense_claims ec LEFT JOIN users u ON u.id = ec.claimant_id LEFT JOIN people p ON p.id = u.person_id
                 WHERE ec.status <> 'Draft' ORDER BY ec.created_at DESC",
                []
              )
            : [],
        'canViewAll' => $canViewAll,
        'canApprove' => expenses_can_approve(),
        'canPettyCash' => user_can('pettycash.manage'),
        'currency' => setting('currency', 'MWK'),
    ]);
}

// ---------------------------------------------------------------------------
// Claims
// ---------------------------------------------------------------------------

function claim(string $id): void
{
    $claimId = (int)$id;
    $claim = db_row(
        "SELECT ec.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS claimant,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(ap.first_name,''),' ',COALESCE(ap.last_name,''))),''), au.username) AS approver
         FROM expense_claims ec
         LEFT JOIN users u ON u.id = ec.claimant_id LEFT JOIN people p ON p.id = u.person_id
         LEFT JOIN users au ON au.id = ec.approved_by LEFT JOIN people ap ON ap.id = au.person_id
         WHERE ec.id = ?",
        [$claimId]
    );
    if (!$claim) {
        render_error(404, 'Not found', 'That claim does not exist.');
    }
    if (!expenses_can_view($claim)) {
        render_error(403, 'Access denied', 'You do not have permission to see this claim.');
    }
    $lines = db_all('SELECT * FROM expense_lines WHERE claim_id = ? ORDER BY expense_date, id', [$claimId]);
    foreach ($lines as &$l) {
        $l['has_receipt'] = $l['receipt_stored_name'] !== null;
        unset($l['receipt_stored_name'], $l['receipt_mime'], $l['receipt_size_bytes']);
    }
    render('expenses/claim', [
        'pageTitle' => $claim['title'],
        'breadcrumbs' => ['Finance' => null, 'Expenses' => '/expenses', $claim['title'] => null],
        'claim' => $claim,
        'lines' => $lines,
        'canEdit' => expenses_can_edit($claim),
        'canApprove' => expenses_can_approve(),
        'categories' => expense_categories(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function create_claim(): void
{
    $title = in_str('title');
    if ($title === '') {
        json_err('A claim title is required.', 422, ['title' => 'Required.']);
    }
    db_query(
        'INSERT INTO expense_claims (claimant_id, title, status) VALUES (?,?,?)',
        [(int)current_user()['id'], mb_substr($title, 0, 200), 'Draft']
    );
    $id = db_insert_id();
    audit('expense_claim.create', 'expense_claim', $id, ['title' => $title]);
    json_ok(['claim_id' => $id]);
}

function claim_or_403(int $claimId, bool $needEdit = false): array
{
    $claim = db_row('SELECT * FROM expense_claims WHERE id = ?', [$claimId]);
    if (!$claim) {
        json_err('That claim does not exist.', 404);
    }
    if ($needEdit && !expenses_can_edit($claim)) {
        json_err('That claim can no longer be edited.', 403);
    }
    if (!$needEdit && !expenses_can_view($claim)) {
        json_err('You do not have permission for this claim.', 403);
    }
    return $claim;
}

function update_claim(string $id): void
{
    $claimId = (int)$id;
    claim_or_403($claimId, true);
    $title = in_str('title');
    if ($title === '') {
        json_err('A claim title is required.', 422, ['title' => 'Required.']);
    }
    db_query('UPDATE expense_claims SET title = ? WHERE id = ?', [mb_substr($title, 0, 200), $claimId]);
    audit('expense_claim.update', 'expense_claim', $claimId, []);
    json_ok();
}

function delete_claim(string $id): void
{
    $claimId = (int)$id;
    claim_or_403($claimId, true);
    // Remove any stored receipt files before the cascade drops the lines.
    foreach (db_all('SELECT receipt_stored_name FROM expense_lines WHERE claim_id = ? AND receipt_stored_name IS NOT NULL', [$claimId]) as $l) {
        $path = rtrim((string)config('uploads.dir'), '/') . '/' . $l['receipt_stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    db_query('DELETE FROM expense_claims WHERE id = ?', [$claimId]);
    audit('expense_claim.delete', 'expense_claim', $claimId, []);
    json_ok();
}

function submit_claim(string $id): void
{
    $claimId = (int)$id;
    $claim = claim_or_403($claimId, true);
    if (!db_val('SELECT COUNT(*) FROM expense_lines WHERE claim_id = ?', [$claimId])) {
        json_err('Add at least one line before submitting.', 422);
    }
    db_query("UPDATE expense_claims SET status = 'Submitted', submitted_at = NOW() WHERE id = ?", [$claimId]);
    notify_role('admin', 'An expense claim is awaiting approval: ' . $claim['title'], '/expenses/' . $claimId, 'expenses');
    audit('expense_claim.submit', 'expense_claim', $claimId, []);
    json_ok();
}

function decide_claim(string $id): void
{
    if (!expenses_can_approve()) {
        json_err('You do not have permission to approve claims.', 403);
    }
    $claimId = (int)$id;
    $claim = db_row('SELECT * FROM expense_claims WHERE id = ?', [$claimId]);
    if (!$claim) {
        json_err('That claim does not exist.', 404);
    }
    if ($claim['status'] !== 'Submitted') {
        json_err('Only a submitted claim can be decided.', 409);
    }
    $decision = in_str('decision');
    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        json_err('Choose approve or reject.', 422);
    }
    db_query(
        'UPDATE expense_claims SET status = ?, approved_by = ?, approved_at = NOW(), approval_note = ? WHERE id = ?',
        [$decision, (int)current_user()['id'], in_str('approval_note') ?: null, $claimId]
    );
    if ($claim['claimant_id']) {
        notify((int)$claim['claimant_id'], 'Your expense claim "' . $claim['title'] . '" was ' . strtolower($decision) . '.', '/expenses/' . $claimId, 'expenses');
    }
    audit('expense_claim.decide', 'expense_claim', $claimId, ['decision' => $decision]);
    json_ok();
}

function reimburse_claim(string $id): void
{
    if (!expenses_can_approve()) {
        json_err('You do not have permission to reimburse claims.', 403);
    }
    $claimId = (int)$id;
    $claim = db_row('SELECT * FROM expense_claims WHERE id = ?', [$claimId]);
    if (!$claim) {
        json_err('That claim does not exist.', 404);
    }
    if ($claim['status'] !== 'Approved') {
        json_err('Only an approved claim can be marked reimbursed.', 409);
    }
    db_query("UPDATE expense_claims SET status = 'Reimbursed', reimbursed_at = NOW() WHERE id = ?", [$claimId]);
    if ($claim['claimant_id']) {
        notify((int)$claim['claimant_id'], 'Your expense claim "' . $claim['title'] . '" has been reimbursed.', '/expenses/' . $claimId, 'expenses');
    }
    audit('expense_claim.reimburse', 'expense_claim', $claimId, ['total' => (float)$claim['total']]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Claim lines and receipts
// ---------------------------------------------------------------------------

function line_input(): array
{
    $desc = in_str('description');
    $errors = [];
    if ($desc === '') {
        $errors['description'] = 'Required.';
    }
    if (in_str('amount') === '' || !is_numeric(in_str('amount'))) {
        $errors['amount'] = 'Enter a number.';
    }
    if (in_str('expense_date') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str('expense_date'))) {
        $errors['expense_date'] = 'Invalid date.';
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'description' => mb_substr($desc, 0, 500),
        'amount' => (float)in_str('amount'),
        'category' => in_str('category') ?: null,
        'expense_date' => in_str('expense_date') ?: null,
    ];
}

function add_line(string $id): void
{
    $claimId = (int)$id;
    claim_or_403($claimId, true);
    $f = line_input();
    $receipt = null;
    if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        try {
            $receipt = store_upload($_FILES['receipt'], [
                'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            ]);
        } catch (RuntimeException $ex) {
            json_err($ex->getMessage(), 422, ['receipt' => $ex->getMessage()]);
        }
    }
    db_query(
        'INSERT INTO expense_lines (claim_id, expense_date, category, description, amount, receipt_stored_name, receipt_original_name, receipt_mime, receipt_size_bytes)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $claimId, $f['expense_date'], $f['category'], $f['description'], $f['amount'],
            $receipt['stored_name'] ?? null, $receipt['original_name'] ?? null, $receipt['mime'] ?? null, $receipt['size_bytes'] ?? null,
        ]
    );
    $lineId = db_insert_id();
    claim_recalc_total($claimId);
    audit('expense_line.add', 'expense_claim', $claimId, ['line_id' => $lineId]);
    json_ok(['line_id' => $lineId]);
}

function update_line(string $id): void
{
    $lineId = (int)$id;
    $line = db_row('SELECT * FROM expense_lines WHERE id = ?', [$lineId]);
    if (!$line) {
        json_err('That line does not exist.', 404);
    }
    claim_or_403((int)$line['claim_id'], true);
    $f = line_input();
    // An optional new receipt replaces the old one.
    $rSql = '';
    $rParams = [];
    if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        try {
            $receipt = store_upload($_FILES['receipt'], [
                'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            ]);
        } catch (RuntimeException $ex) {
            json_err($ex->getMessage(), 422, ['receipt' => $ex->getMessage()]);
        }
        $rSql = ', receipt_stored_name = ?, receipt_original_name = ?, receipt_mime = ?, receipt_size_bytes = ?';
        $rParams = [$receipt['stored_name'], $receipt['original_name'], $receipt['mime'], $receipt['size_bytes']];
        if ($line['receipt_stored_name']) {
            $old = rtrim((string)config('uploads.dir'), '/') . '/' . $line['receipt_stored_name'];
            if (is_file($old)) {
                unlink($old);
            }
        }
    }
    db_query(
        'UPDATE expense_lines SET expense_date = ?, category = ?, description = ?, amount = ?' . $rSql . ' WHERE id = ?',
        array_merge([$f['expense_date'], $f['category'], $f['description'], $f['amount']], $rParams, [$lineId])
    );
    claim_recalc_total((int)$line['claim_id']);
    audit('expense_line.update', 'expense_claim', (int)$line['claim_id'], ['line_id' => $lineId]);
    json_ok();
}

function delete_line(string $id): void
{
    $lineId = (int)$id;
    $line = db_row('SELECT * FROM expense_lines WHERE id = ?', [$lineId]);
    if (!$line) {
        json_err('That line does not exist.', 404);
    }
    claim_or_403((int)$line['claim_id'], true);
    db_query('DELETE FROM expense_lines WHERE id = ?', [$lineId]);
    if ($line['receipt_stored_name']) {
        $path = rtrim((string)config('uploads.dir'), '/') . '/' . $line['receipt_stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    claim_recalc_total((int)$line['claim_id']);
    audit('expense_line.delete', 'expense_claim', (int)$line['claim_id'], ['line_id' => $lineId]);
    json_ok();
}

function receipt_link(string $id): void
{
    $lineId = (int)$id;
    $line = db_row('SELECT el.*, ec.claimant_id FROM expense_lines el JOIN expense_claims ec ON ec.id = el.claim_id WHERE el.id = ?', [$lineId]);
    if (!$line || !$line['receipt_stored_name']) {
        json_err('That line has no receipt.', 404);
    }
    if (!expenses_can_view($line)) {
        json_err('You do not have permission to download this receipt.', 403);
    }
    $token = sign_download($lineId, (int)current_user()['id'], 300);
    json_ok(['url' => '/expenses/receipts/file/' . $token, 'expires_in' => 300]);
}

function download_receipt(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$lineId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $line = db_row('SELECT el.*, ec.claimant_id FROM expense_lines el JOIN expense_claims ec ON ec.id = el.claim_id WHERE el.id = ?', [$lineId]);
    if (!$line || !$line['receipt_stored_name']) {
        render_error(404, 'Not found', 'That receipt no longer exists.');
    }
    if (!expenses_can_view($line)) {
        render_error(403, 'Access denied', 'You do not have permission to download this receipt.');
    }
    audit('expense_receipt.download', 'expense_claim', (int)$line['claim_id'], ['line_id' => $lineId]);
    stream_stored_file($line['receipt_stored_name'], $line['receipt_original_name'], $line['receipt_mime']);
}

// ---------------------------------------------------------------------------
// Petty cash
// ---------------------------------------------------------------------------

function petty_cash(): void
{
    // Running balance computed in date and id order.
    $entries = db_all(
        "SELECT pc.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS recorder
         FROM petty_cash pc LEFT JOIN users u ON u.id = pc.created_by LEFT JOIN people p ON p.id = u.person_id
         ORDER BY pc.entry_date, pc.id"
    );
    $balance = 0.0;
    foreach ($entries as &$e) {
        $balance += $e['entry_type'] === 'Replenishment' ? (float)$e['amount'] : -(float)$e['amount'];
        $e['balance_after'] = $balance;
    }
    // Show newest first.
    $entries = array_reverse($entries);
    render('expenses/petty_cash', [
        'pageTitle' => 'Petty cash',
        'breadcrumbs' => ['Finance' => null, 'Expenses' => '/expenses', 'Petty cash' => null],
        'entries' => $entries,
        'balance' => $balance,
        'canManage' => user_can('pettycash.manage'),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function add_petty_entry(): void
{
    if (!user_can('pettycash.manage')) {
        json_err('You do not have permission to manage petty cash.', 403);
    }
    $type = in_str('entry_type');
    if (!in_array($type, ['Replenishment', 'Disbursement'], true)) {
        json_err('Choose replenishment or disbursement.', 422, ['entry_type' => 'Invalid.']);
    }
    if (in_str('amount') === '' || !is_numeric(in_str('amount')) || (float)in_str('amount') <= 0) {
        json_err('Enter a positive amount.', 422, ['amount' => 'Enter a positive number.']);
    }
    $date = in_str('entry_date');
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_err('Enter a valid date.', 422, ['entry_date' => 'Invalid date.']);
    }
    $itemRequestId = in_int('item_request_id');
    if ($itemRequestId && !db_val('SELECT id FROM item_requests WHERE id = ?', [$itemRequestId])) {
        $itemRequestId = null;
    }
    db_query(
        'INSERT INTO petty_cash (entry_type, amount, entry_date, purpose, item_request_id, created_by) VALUES (?,?,?,?,?,?)',
        [$type, (float)in_str('amount'), $date, in_str('purpose') ?: null, $itemRequestId ?: null, (int)current_user()['id']]
    );
    audit('petty_cash.add', 'petty_cash', db_insert_id(), ['type' => $type, 'amount' => (float)in_str('amount')]);
    json_ok(['entry_id' => db_insert_id()]);
}

function delete_petty_entry(string $id): void
{
    if (!user_can('pettycash.manage')) {
        json_err('You do not have permission to manage petty cash.', 403);
    }
    if (!db_val('SELECT id FROM petty_cash WHERE id = ?', [(int)$id])) {
        json_err('That entry does not exist.', 404);
    }
    db_query('DELETE FROM petty_cash WHERE id = ?', [(int)$id]);
    audit('petty_cash.delete', 'petty_cash', (int)$id, []);
    json_ok();
}
