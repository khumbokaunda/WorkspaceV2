<?php
// Company profile and the compliance document library. The profile is the
// single company identity captured at setup and edited under Settings; the
// library holds the official documents and past-performance references a
// company reuses across tenders, each with expiry tracking. Files use the
// gated storage and download path, never a direct web path.

declare(strict_types=1);

// Document types offered in the library, mirroring the migration enum.
function company_doc_types(): array
{
    return [
        'Registration Certificate', 'Tax Compliance', 'Business Permit',
        'Audited Accounts', 'Insurance', 'Company Profile', 'Reference Letter', 'Other',
    ];
}

// ---------------------------------------------------------------------------
// Company profile (edited in the admin Settings area)
// ---------------------------------------------------------------------------

function profile_form(): void
{
    render('company/profile', [
        'pageTitle' => 'Company profile',
        'breadcrumbs' => ['Admin' => null, 'Settings' => '/admin/settings', 'Company profile' => null],
        'profile' => company_profile(),
    ]);
}

function profile_save(): void
{
    $legalName = in_str('legal_name');
    if ($legalName === '') {
        json_err('The company legal name is required.', 422, ['legal_name' => 'Required.']);
    }
    $email = in_str('email');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        json_err('Enter a valid company email address.', 422, ['email' => 'Invalid email.']);
    }
    $tradingName = in_str('trading_name') ?: $legalName;

    // Optional logo, stored web reachable since it is public branding, not
    // sensitive. Validated by extension and detected MIME, same as setup.
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

    db_query(
        'UPDATE company_profile SET legal_name = ?, trading_name = ?, reg_number = ?, tax_id = ?,
                phys_address = ?, postal_address = ?, phone = ?, email = ?, overview = ?, mission = ?'
            . ($logoPath !== null ? ', logo_path = ?' : '')
            . ', updated_by = ? WHERE id = 1',
        array_merge(
            [
                $legalName, $tradingName, in_str('reg_number') ?: null, in_str('tax_id') ?: null,
                in_str('phys_address') ?: null, in_str('postal_address') ?: null,
                in_str('phone') ?: null, $email ?: null,
                in_str('overview') ?: null, in_str('mission') ?: null,
            ],
            $logoPath !== null ? [$logoPath] : [],
            [(int)current_user()['id']]
        )
    );

    // Mirror the identity into settings so the shell name, page titles and the
    // values setup captured stay consistent with the profile.
    $set = function (string $key, ?string $value): void {
        db_query(
            'INSERT INTO settings (setting_key, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, (string)$value]
        );
    };
    $set('org_name', $tradingName);
    $set('legal_name', $legalName);
    $set('trading_name', $tradingName);
    $set('reg_number', in_str('reg_number'));
    $set('tax_id', in_str('tax_id'));
    $set('phys_address', in_str('phys_address'));
    $set('postal_address', in_str('postal_address'));
    $set('company_phone', in_str('phone'));
    $set('company_email', $email);
    if ($logoPath !== null) {
        $set('logo_path', $logoPath);
    }

    audit('company_profile.update', 'company_profile', 1, ['legal_name' => $legalName]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Compliance library view
// ---------------------------------------------------------------------------

function library(): void
{
    render('company/library', [
        'pageTitle' => 'Compliance Library',
        'breadcrumbs' => ['Company' => null, 'Compliance Library' => null],
        'profile' => company_profile(),
        'canManage' => user_can('company_docs.manage'),
        'docTypes' => company_doc_types(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

// ---------------------------------------------------------------------------
// Compliance documents
// ---------------------------------------------------------------------------

function documents_json(): void
{
    $rows = db_all(
        'SELECT d.id, d.doc_type, d.title, d.original_name, d.mime, d.size_bytes, d.issue_date,
                d.expiry_date, d.issuing_authority, d.reference_number, d.notes, d.uploaded_at,
                u.username AS uploader
         FROM company_documents d LEFT JOIN users u ON u.id = d.uploaded_by
         ORDER BY d.expiry_date IS NULL, d.expiry_date, d.doc_type'
    );
    foreach ($rows as &$d) {
        $d['expiry_state'] = expiry_status($d['expiry_date']);
        $d['days_left'] = $d['expiry_date'] !== null
            ? (int)floor((strtotime($d['expiry_date']) - strtotime(date('Y-m-d'))) / 86400)
            : null;
    }
    json_out(['ok' => true, 'documents' => $rows]);
}

function upload_document(): void
{
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        json_err('A document title is required.', 422, ['title' => 'Required.']);
    }
    $docType = in_array($_POST['doc_type'] ?? '', company_doc_types(), true) ? $_POST['doc_type'] : 'Other';
    foreach (['issue_date', 'expiry_date'] as $dateField) {
        $v = trim((string)($_POST[$dateField] ?? ''));
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            json_err('Enter a valid date.', 422, [$dateField => 'Invalid date.']);
        }
    }
    if (empty($_FILES['file'])) {
        json_err('No file received or the upload failed.', 422);
    }
    try {
        $stored = store_upload($_FILES['file']);
    } catch (RuntimeException $ex) {
        json_err($ex->getMessage(), 422);
    }
    db_query(
        'INSERT INTO company_documents
            (doc_type, title, stored_name, original_name, mime, size_bytes, issue_date, expiry_date,
             issuing_authority, reference_number, notes, uploaded_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $docType, mb_substr($title, 0, 200), $stored['stored_name'], $stored['original_name'],
            $stored['mime'], $stored['size_bytes'],
            trim((string)($_POST['issue_date'] ?? '')) ?: null,
            trim((string)($_POST['expiry_date'] ?? '')) ?: null,
            trim((string)($_POST['issuing_authority'] ?? '')) ?: null,
            trim((string)($_POST['reference_number'] ?? '')) ?: null,
            trim((string)($_POST['notes'] ?? '')) ?: null,
            (int)current_user()['id'],
        ]
    );
    $docId = db_insert_id();
    audit('company_document.upload', 'company_document', $docId, ['type' => $docType, 'title' => $title]);
    json_ok(['document_id' => $docId]);
}

function update_document(string $id): void
{
    $docId = (int)$id;
    if (!db_val('SELECT id FROM company_documents WHERE id = ?', [$docId])) {
        json_err('That document does not exist.', 404);
    }
    $title = in_str('title');
    if ($title === '') {
        json_err('A document title is required.', 422, ['title' => 'Required.']);
    }
    $docType = in_array(in_str('doc_type'), company_doc_types(), true) ? in_str('doc_type') : 'Other';
    foreach (['issue_date', 'expiry_date'] as $dateField) {
        $v = in_str($dateField);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            json_err('Enter a valid date.', 422, [$dateField => 'Invalid date.']);
        }
    }
    db_query(
        'UPDATE company_documents SET doc_type = ?, title = ?, issue_date = ?, expiry_date = ?,
                issuing_authority = ?, reference_number = ?, notes = ? WHERE id = ?',
        [
            $docType, mb_substr($title, 0, 200),
            in_str('issue_date') ?: null, in_str('expiry_date') ?: null,
            in_str('issuing_authority') ?: null, in_str('reference_number') ?: null,
            in_str('notes') ?: null, $docId,
        ]
    );
    audit('company_document.update', 'company_document', $docId, ['title' => $title]);
    json_ok();
}

function delete_document(string $id): void
{
    $docId = (int)$id;
    $doc = db_row('SELECT * FROM company_documents WHERE id = ?', [$docId]);
    if (!$doc) {
        json_err('That document does not exist.', 404);
    }
    $path = rtrim((string)config('uploads.dir'), '/') . '/' . $doc['stored_name'];
    db_query('DELETE FROM company_documents WHERE id = ?', [$docId]);
    if (is_file($path)) {
        unlink($path);
    }
    audit('company_document.delete', 'company_document', $docId, ['title' => $doc['title']]);
    json_ok();
}

function document_link(string $id): void
{
    $docId = (int)$id;
    if (!db_val('SELECT id FROM company_documents WHERE id = ?', [$docId])) {
        json_err('That document does not exist.', 404);
    }
    $token = sign_download($docId, (int)current_user()['id'], 300);
    json_ok(['url' => '/company/files/' . $token, 'expires_in' => 300]);
}

function download_document(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$docId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $doc = db_row('SELECT * FROM company_documents WHERE id = ?', [$docId]);
    if (!$doc) {
        render_error(404, 'Not found', 'That document no longer exists.');
    }
    audit('company_document.download', 'company_document', $docId, ['title' => $doc['title']]);
    stream_stored_file($doc['stored_name'], $doc['original_name'], $doc['mime']);
}

// ---------------------------------------------------------------------------
// Past-performance references
// ---------------------------------------------------------------------------

function references_json(): void
{
    $rows = db_all(
        'SELECT r.*, u.username AS creator FROM company_references r
         LEFT JOIN users u ON u.id = r.created_by
         ORDER BY r.end_date IS NULL, r.end_date DESC, r.id DESC'
    );
    foreach ($rows as &$r) {
        $r['has_certificate'] = $r['cert_stored_name'] !== null;
        unset($r['cert_stored_name']);
    }
    json_out(['ok' => true, 'references' => $rows]);
}

// Read the reference fields from either a JSON body or a multipart form, so an
// optional completion certificate can accompany a create or an edit.
function reference_fields(): array
{
    $clientName = in_str('client_name');
    $projectTitle = in_str('project_title');
    $errors = [];
    if ($clientName === '') {
        $errors['client_name'] = 'Required.';
    }
    if ($projectTitle === '') {
        $errors['project_title'] = 'Required.';
    }
    $value = in_str('contract_value');
    if ($value !== '' && !is_numeric($value)) {
        $errors['contract_value'] = 'Enter a number.';
    }
    foreach (['start_date', 'end_date'] as $dateField) {
        $v = in_str($dateField);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $errors[$dateField] = 'Invalid date.';
        }
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'client_name' => mb_substr($clientName, 0, 200),
        'project_title' => mb_substr($projectTitle, 0, 250),
        'contract_value' => $value !== '' ? (float)$value : null,
        'currency' => strtoupper(in_str('currency')) ?: null,
        'start_date' => in_str('start_date') ?: null,
        'end_date' => in_str('end_date') ?: null,
        'scope_summary' => in_str('scope_summary') ?: null,
        'ref_contact_name' => in_str('ref_contact_name') ?: null,
        'ref_contact_detail' => in_str('ref_contact_detail') ?: null,
    ];
}

function create_reference(): void
{
    $f = reference_fields();
    $cert = null;
    if (!empty($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        try {
            $cert = store_upload($_FILES['certificate'], [
                'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            ]);
        } catch (RuntimeException $ex) {
            json_err($ex->getMessage(), 422, ['certificate' => $ex->getMessage()]);
        }
    }
    db_query(
        'INSERT INTO company_references
            (client_name, project_title, contract_value, currency, start_date, end_date, scope_summary,
             ref_contact_name, ref_contact_detail, cert_stored_name, cert_original_name, cert_mime, cert_size_bytes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $f['client_name'], $f['project_title'], $f['contract_value'], $f['currency'],
            $f['start_date'], $f['end_date'], $f['scope_summary'],
            $f['ref_contact_name'], $f['ref_contact_detail'],
            $cert['stored_name'] ?? null, $cert['original_name'] ?? null,
            $cert['mime'] ?? null, $cert['size_bytes'] ?? null,
            (int)current_user()['id'],
        ]
    );
    $refId = db_insert_id();
    audit('company_reference.create', 'company_reference', $refId, ['project' => $f['project_title']]);
    json_ok(['reference_id' => $refId]);
}

function update_reference(string $id): void
{
    $refId = (int)$id;
    $ref = db_row('SELECT * FROM company_references WHERE id = ?', [$refId]);
    if (!$ref) {
        json_err('That reference does not exist.', 404);
    }
    $f = reference_fields();

    // An optional new certificate replaces the old one, which is then removed.
    $certSql = '';
    $certParams = [];
    if (!empty($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        try {
            $cert = store_upload($_FILES['certificate'], [
                'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            ]);
        } catch (RuntimeException $ex) {
            json_err($ex->getMessage(), 422, ['certificate' => $ex->getMessage()]);
        }
        $certSql = ', cert_stored_name = ?, cert_original_name = ?, cert_mime = ?, cert_size_bytes = ?';
        $certParams = [$cert['stored_name'], $cert['original_name'], $cert['mime'], $cert['size_bytes']];
        if ($ref['cert_stored_name']) {
            $old = rtrim((string)config('uploads.dir'), '/') . '/' . $ref['cert_stored_name'];
            if (is_file($old)) {
                unlink($old);
            }
        }
    }

    db_query(
        'UPDATE company_references SET client_name = ?, project_title = ?, contract_value = ?, currency = ?,
                start_date = ?, end_date = ?, scope_summary = ?, ref_contact_name = ?, ref_contact_detail = ?'
            . $certSql . ' WHERE id = ?',
        array_merge(
            [
                $f['client_name'], $f['project_title'], $f['contract_value'], $f['currency'],
                $f['start_date'], $f['end_date'], $f['scope_summary'],
                $f['ref_contact_name'], $f['ref_contact_detail'],
            ],
            $certParams,
            [$refId]
        )
    );
    audit('company_reference.update', 'company_reference', $refId, ['project' => $f['project_title']]);
    json_ok();
}

function delete_reference(string $id): void
{
    $refId = (int)$id;
    $ref = db_row('SELECT * FROM company_references WHERE id = ?', [$refId]);
    if (!$ref) {
        json_err('That reference does not exist.', 404);
    }
    db_query('DELETE FROM company_references WHERE id = ?', [$refId]);
    if ($ref['cert_stored_name']) {
        $path = rtrim((string)config('uploads.dir'), '/') . '/' . $ref['cert_stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    audit('company_reference.delete', 'company_reference', $refId, ['project' => $ref['project_title']]);
    json_ok();
}

function reference_cert_link(string $id): void
{
    $refId = (int)$id;
    if (!db_val('SELECT cert_stored_name FROM company_references WHERE id = ? AND cert_stored_name IS NOT NULL', [$refId])) {
        json_err('That reference has no completion certificate.', 404);
    }
    $token = sign_download($refId, (int)current_user()['id'], 300);
    json_ok(['url' => '/company/references/file/' . $token, 'expires_in' => 300]);
}

function download_reference_cert(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$refId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $ref = db_row('SELECT * FROM company_references WHERE id = ?', [$refId]);
    if (!$ref || !$ref['cert_stored_name']) {
        render_error(404, 'Not found', 'That certificate no longer exists.');
    }
    audit('company_reference.download', 'company_reference', $refId, ['project' => $ref['project_title']]);
    stream_stored_file($ref['cert_stored_name'], $ref['cert_original_name'], $ref['cert_mime']);
}
