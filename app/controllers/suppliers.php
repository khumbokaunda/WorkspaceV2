<?php
// Suppliers, manufacturers and their authorizations. Suppliers are the
// companies you buy from or represent; a manufacturer authorization is the
// standard tender requirement proving you are an authorized reseller of a
// brand. Authorizations carry a gated file and their expiry is tracked like
// other compliance documents.

declare(strict_types=1);

function supplier_categories(): array
{
    return ['Distributor', 'Manufacturer', 'Service Provider', 'Contractor'];
}

function authorization_statuses(): array
{
    return ['Active', 'Expired', 'Revoked'];
}

// Expiry always wins over a stored Active value, the same rule certifications
// use. A Revoked authorization stays revoked regardless of dates.
function authorization_effective_status(array $auth): string
{
    if ($auth['status'] === 'Revoked') {
        return 'Revoked';
    }
    if (!empty($auth['expiry_date']) && $auth['expiry_date'] < date('Y-m-d')) {
        return 'Expired';
    }
    return 'Active';
}

function suppliers_rules(): array
{
    return [
        'name' => 'required|max:200',
        'category' => 'in:Distributor;Manufacturer;Service Provider;Contractor',
        'email' => 'email',
        'phone' => 'max:60',
        'rating' => 'int',
    ];
}

function index(): void
{
    render('suppliers/index', [
        'pageTitle' => 'Suppliers',
        'breadcrumbs' => ['Procurement' => null, 'Suppliers' => null],
        'canManage' => user_can('suppliers.manage'),
        'categories' => supplier_categories(),
    ]);
}

function list_json(): void
{
    $rows = db_all(
        "SELECT s.*,
                (SELECT COUNT(*) FROM manufacturer_authorizations ma WHERE ma.supplier_id = s.id) AS authorization_count
         FROM suppliers s ORDER BY s.name"
    );
    json_out(['ok' => true, 'suppliers' => $rows]);
}

function show(string $id): void
{
    $supplierId = (int)$id;
    $supplier = db_row('SELECT * FROM suppliers WHERE id = ?', [$supplierId]);
    if (!$supplier) {
        render_error(404, 'Not found', 'That supplier does not exist.');
    }
    $auths = db_all('SELECT * FROM manufacturer_authorizations WHERE supplier_id = ? ORDER BY expiry_date IS NULL, expiry_date', [$supplierId]);
    foreach ($auths as &$a) {
        $a['effective_status'] = authorization_effective_status($a);
        $a['has_file'] = $a['stored_name'] !== null;
        $a['days_left'] = $a['expiry_date'] !== null
            ? (int)floor((strtotime($a['expiry_date']) - strtotime(date('Y-m-d'))) / 86400)
            : null;
        unset($a['stored_name'], $a['original_name'], $a['mime'], $a['size_bytes']);
    }
    render('suppliers/show', [
        'pageTitle' => $supplier['name'],
        'breadcrumbs' => ['Procurement' => null, 'Suppliers' => '/suppliers', $supplier['name'] => null],
        'supplier' => $supplier,
        'authorizations' => $auths,
        'canManage' => user_can('suppliers.manage'),
        'categories' => supplier_categories(),
        'statuses' => authorization_statuses(),
    ]);
}

function create(): void
{
    $in = input();
    $errors = validate($in, suppliers_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $category = in_array(in_str('category'), supplier_categories(), true) ? in_str('category') : 'Distributor';
    $rating = in_int('rating');
    if ($rating !== null && ($rating < 1 || $rating > 5)) {
        json_err('Rating must be between 1 and 5.', 422, ['rating' => '1 to 5.']);
    }
    db_query(
        'INSERT INTO suppliers (name, category, product_lines, contact_name, phone, email, lead_time_notes, rating, notes)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            in_str('name'), $category, in_str('product_lines') ?: null, in_str('contact_name') ?: null,
            in_str('phone') ?: null, in_str('email') ?: null, in_str('lead_time_notes') ?: null,
            $rating, in_str('notes') ?: null,
        ]
    );
    $supplierId = db_insert_id();
    audit('supplier.create', 'supplier', $supplierId, ['name' => in_str('name')]);
    json_ok(['supplier_id' => $supplierId]);
}

function update(string $id): void
{
    $supplierId = (int)$id;
    if (!db_val('SELECT id FROM suppliers WHERE id = ?', [$supplierId])) {
        json_err('That supplier does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, suppliers_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $category = in_array(in_str('category'), supplier_categories(), true) ? in_str('category') : 'Distributor';
    $rating = in_int('rating');
    if ($rating !== null && ($rating < 1 || $rating > 5)) {
        json_err('Rating must be between 1 and 5.', 422, ['rating' => '1 to 5.']);
    }
    db_query(
        'UPDATE suppliers SET name = ?, category = ?, product_lines = ?, contact_name = ?, phone = ?, email = ?, lead_time_notes = ?, rating = ?, notes = ? WHERE id = ?',
        [
            in_str('name'), $category, in_str('product_lines') ?: null, in_str('contact_name') ?: null,
            in_str('phone') ?: null, in_str('email') ?: null, in_str('lead_time_notes') ?: null,
            $rating, in_str('notes') ?: null, $supplierId,
        ]
    );
    audit('supplier.update', 'supplier', $supplierId, ['name' => in_str('name')]);
    json_ok();
}

function destroy(string $id): void
{
    $supplierId = (int)$id;
    $supplier = db_row('SELECT * FROM suppliers WHERE id = ?', [$supplierId]);
    if (!$supplier) {
        json_err('That supplier does not exist.', 404);
    }
    // Remove any stored authorization files before the cascade drops the rows.
    foreach (db_all('SELECT stored_name FROM manufacturer_authorizations WHERE supplier_id = ? AND stored_name IS NOT NULL', [$supplierId]) as $a) {
        $path = rtrim((string)config('uploads.dir'), '/') . '/' . $a['stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    db_query('DELETE FROM suppliers WHERE id = ?', [$supplierId]);
    audit('supplier.delete', 'supplier', $supplierId, ['name' => $supplier['name']]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Manufacturer authorizations
// ---------------------------------------------------------------------------

function authorization_fields(): array
{
    $productLine = in_str('product_line');
    $errors = [];
    if ($productLine === '') {
        $errors['product_line'] = 'Required.';
    }
    $status = in_str('status');
    if ($status !== '' && !in_array($status, authorization_statuses(), true)) {
        $errors['status'] = 'Invalid status.';
    }
    foreach (['issue_date', 'expiry_date'] as $dateField) {
        $v = in_str($dateField);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $errors[$dateField] = 'Invalid date.';
        }
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'product_line' => mb_substr($productLine, 0, 200),
        'authorization_ref' => in_str('authorization_ref') ?: null,
        'issue_date' => in_str('issue_date') ?: null,
        'expiry_date' => in_str('expiry_date') ?: null,
        'status' => $status ?: 'Active',
        'notes' => in_str('notes') ?: null,
    ];
}

function add_authorization(string $id): void
{
    $supplierId = (int)$id;
    if (!db_val('SELECT id FROM suppliers WHERE id = ?', [$supplierId])) {
        json_err('That supplier does not exist.', 404);
    }
    $f = authorization_fields();
    $file = null;
    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        try {
            $file = store_upload($_FILES['file'], [
                'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            ]);
        } catch (RuntimeException $ex) {
            json_err($ex->getMessage(), 422, ['file' => $ex->getMessage()]);
        }
    }
    db_query(
        'INSERT INTO manufacturer_authorizations
            (supplier_id, product_line, authorization_ref, issue_date, expiry_date, stored_name, original_name, mime, size_bytes, status, notes, uploaded_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $supplierId, $f['product_line'], $f['authorization_ref'], $f['issue_date'], $f['expiry_date'],
            $file['stored_name'] ?? null, $file['original_name'] ?? null, $file['mime'] ?? null, $file['size_bytes'] ?? null,
            $f['status'], $f['notes'], (int)current_user()['id'],
        ]
    );
    $authId = db_insert_id();
    audit('manufacturer_authorization.create', 'manufacturer_authorization', $authId, ['supplier_id' => $supplierId, 'product_line' => $f['product_line']]);
    json_ok(['authorization_id' => $authId]);
}

function update_authorization(string $id): void
{
    $authId = (int)$id;
    $auth = db_row('SELECT * FROM manufacturer_authorizations WHERE id = ?', [$authId]);
    if (!$auth) {
        json_err('That authorization does not exist.', 404);
    }
    $f = authorization_fields();
    $fileSql = '';
    $fileParams = [];
    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        try {
            $file = store_upload($_FILES['file'], [
                'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            ]);
        } catch (RuntimeException $ex) {
            json_err($ex->getMessage(), 422, ['file' => $ex->getMessage()]);
        }
        $fileSql = ', stored_name = ?, original_name = ?, mime = ?, size_bytes = ?';
        $fileParams = [$file['stored_name'], $file['original_name'], $file['mime'], $file['size_bytes']];
        if ($auth['stored_name']) {
            $old = rtrim((string)config('uploads.dir'), '/') . '/' . $auth['stored_name'];
            if (is_file($old)) {
                unlink($old);
            }
        }
    }
    db_query(
        'UPDATE manufacturer_authorizations SET product_line = ?, authorization_ref = ?, issue_date = ?, expiry_date = ?, status = ?, notes = ?'
            . $fileSql . ' WHERE id = ?',
        array_merge(
            [$f['product_line'], $f['authorization_ref'], $f['issue_date'], $f['expiry_date'], $f['status'], $f['notes']],
            $fileParams,
            [$authId]
        )
    );
    audit('manufacturer_authorization.update', 'manufacturer_authorization', $authId, ['product_line' => $f['product_line']]);
    json_ok();
}

function delete_authorization(string $id): void
{
    $authId = (int)$id;
    $auth = db_row('SELECT * FROM manufacturer_authorizations WHERE id = ?', [$authId]);
    if (!$auth) {
        json_err('That authorization does not exist.', 404);
    }
    db_query('DELETE FROM manufacturer_authorizations WHERE id = ?', [$authId]);
    if ($auth['stored_name']) {
        $path = rtrim((string)config('uploads.dir'), '/') . '/' . $auth['stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    audit('manufacturer_authorization.delete', 'manufacturer_authorization', $authId, ['product_line' => $auth['product_line']]);
    json_ok();
}

function authorization_link(string $id): void
{
    $authId = (int)$id;
    if (!db_val('SELECT stored_name FROM manufacturer_authorizations WHERE id = ? AND stored_name IS NOT NULL', [$authId])) {
        json_err('That authorization has no attached file.', 404);
    }
    $token = sign_download($authId, (int)current_user()['id'], 300);
    json_ok(['url' => '/suppliers/authorizations/file/' . $token, 'expires_in' => 300]);
}

function download_authorization(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$authId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $auth = db_row('SELECT * FROM manufacturer_authorizations WHERE id = ?', [$authId]);
    if (!$auth || !$auth['stored_name']) {
        render_error(404, 'Not found', 'That file no longer exists.');
    }
    audit('manufacturer_authorization.download', 'manufacturer_authorization', $authId, ['product_line' => $auth['product_line']]);
    stream_stored_file($auth['stored_name'], $auth['original_name'], $auth['mime']);
}
