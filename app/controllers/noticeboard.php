<?php
// Company noticeboard and policies. Internal announcements everyone can read,
// and a read-only library of company policies and procedures. Policy files use
// the gated storage and download path.

declare(strict_types=1);

function nb_can_manage(): bool { return user_can('noticeboard.manage'); }

function index(): void
{
    render('noticeboard/index', [
        'pageTitle' => 'Noticeboard',
        'breadcrumbs' => ['Company' => null, 'Noticeboard' => null],
        'canManage' => nb_can_manage(),
        'announcements' => db_all(
            "SELECT a.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS author
             FROM announcements a LEFT JOIN users u ON u.id = a.published_by LEFT JOIN people p ON p.id = u.person_id
             ORDER BY a.is_pinned DESC, a.created_at DESC"
        ),
        'policies' => db_all('SELECT id, title, category, version_label, effective_date, original_name, notes, created_at FROM policies ORDER BY category, title'),
    ]);
}

// ---------------------------------------------------------------------------
// Announcements
// ---------------------------------------------------------------------------

function create_announcement(): void
{
    if (!nb_can_manage()) {
        json_err('You do not have permission to post announcements.', 403);
    }
    $title = in_str('title');
    if ($title === '') {
        json_err('A title is required.', 422, ['title' => 'Required.']);
    }
    db_query(
        'INSERT INTO announcements (title, body, is_pinned, published_by) VALUES (?,?,?,?)',
        [mb_substr($title, 0, 200), in_str('body') ?: null, in_int('is_pinned') ? 1 : 0, (int)current_user()['id']]
    );
    $id = db_insert_id();
    audit('announcement.create', 'announcement', $id, ['title' => $title]);
    json_ok(['announcement_id' => $id]);
}

function update_announcement(string $id): void
{
    if (!nb_can_manage()) {
        json_err('You do not have permission to edit announcements.', 403);
    }
    if (!db_val('SELECT id FROM announcements WHERE id = ?', [(int)$id])) {
        json_err('That announcement does not exist.', 404);
    }
    $title = in_str('title');
    if ($title === '') {
        json_err('A title is required.', 422, ['title' => 'Required.']);
    }
    db_query(
        'UPDATE announcements SET title = ?, body = ?, is_pinned = ? WHERE id = ?',
        [mb_substr($title, 0, 200), in_str('body') ?: null, in_int('is_pinned') ? 1 : 0, (int)$id]
    );
    audit('announcement.update', 'announcement', (int)$id, []);
    json_ok();
}

function delete_announcement(string $id): void
{
    if (!nb_can_manage()) {
        json_err('You do not have permission to delete announcements.', 403);
    }
    if (!db_val('SELECT id FROM announcements WHERE id = ?', [(int)$id])) {
        json_err('That announcement does not exist.', 404);
    }
    db_query('DELETE FROM announcements WHERE id = ?', [(int)$id]);
    audit('announcement.delete', 'announcement', (int)$id, []);
    json_ok();
}

// ---------------------------------------------------------------------------
// Policy library
// ---------------------------------------------------------------------------

function upload_policy(): void
{
    if (!nb_can_manage()) {
        json_err('You do not have permission to manage policies.', 403);
    }
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        json_err('A policy title is required.', 422, ['title' => 'Required.']);
    }
    if (empty($_FILES['file'])) {
        json_err('No file received or the upload failed.', 422);
    }
    try {
        $stored = store_upload($_FILES['file']);
    } catch (RuntimeException $ex) {
        json_err($ex->getMessage(), 422, ['file' => $ex->getMessage()]);
    }
    $effective = trim((string)($_POST['effective_date'] ?? ''));
    if ($effective !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) {
        json_err('Enter a valid effective date.', 422, ['effective_date' => 'Invalid date.']);
    }
    db_query(
        'INSERT INTO policies (title, category, version_label, effective_date, stored_name, original_name, mime, size_bytes, notes, uploaded_by)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [
            mb_substr($title, 0, 200), trim((string)($_POST['category'] ?? '')) ?: null,
            trim((string)($_POST['version_label'] ?? '')) ?: null, $effective ?: null,
            $stored['stored_name'], $stored['original_name'], $stored['mime'], $stored['size_bytes'],
            trim((string)($_POST['notes'] ?? '')) ?: null, (int)current_user()['id'],
        ]
    );
    $id = db_insert_id();
    audit('policy.upload', 'policy', $id, ['title' => $title]);
    json_ok(['policy_id' => $id]);
}

function delete_policy(string $id): void
{
    if (!nb_can_manage()) {
        json_err('You do not have permission to manage policies.', 403);
    }
    $policy = db_row('SELECT * FROM policies WHERE id = ?', [(int)$id]);
    if (!$policy) {
        json_err('That policy does not exist.', 404);
    }
    db_query('DELETE FROM policies WHERE id = ?', [(int)$id]);
    $path = rtrim((string)config('uploads.dir'), '/') . '/' . $policy['stored_name'];
    if (is_file($path)) {
        unlink($path);
    }
    audit('policy.delete', 'policy', (int)$id, ['title' => $policy['title']]);
    json_ok();
}

function policy_link(string $id): void
{
    if (!db_val('SELECT id FROM policies WHERE id = ?', [(int)$id])) {
        json_err('That policy does not exist.', 404);
    }
    $token = sign_download((int)$id, (int)current_user()['id'], 300);
    json_ok(['url' => '/noticeboard/policies/file/' . $token, 'expires_in' => 300]);
}

function download_policy(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$policyId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $policy = db_row('SELECT * FROM policies WHERE id = ?', [$policyId]);
    if (!$policy) {
        render_error(404, 'Not found', 'That policy no longer exists.');
    }
    audit('policy.download', 'policy', $policyId, ['title' => $policy['title']]);
    stream_stored_file($policy['stored_name'], $policy['original_name'], $policy['mime']);
}
