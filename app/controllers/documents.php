<?php
// Document vault. Files live in storage/uploads outside the document root
// under random names. Uploads are validated by extension, finfo MIME and
// size. Downloads go through a short-lived signed token that is checked
// against the requester's permissions; direct paths are never exposed.

declare(strict_types=1);

function documents_can_access(int $personId): bool
{
    if (user_can('documents.view_all')) {
        return true;
    }
    $me = current_user();
    return user_can('documents.view') && (int)($me['person_id'] ?? 0) === $personId;
}

function upload(string $id): void
{
    $personId = (int)$id;
    if (!db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('That person does not exist.', 404);
    }
    if (!documents_can_access($personId)) {
        json_err('You may only upload documents to your own profile.', 403);
    }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_err('No file received or the upload failed.', 422);
    }

    $file = $_FILES['file'];
    $maxBytes = (int)config('uploads.max_bytes', 10485760);
    if ($file['size'] > $maxBytes || $file['size'] <= 0) {
        json_err('The file exceeds the maximum size of ' . round($maxBytes / 1048576) . ' MB.', 422);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, config('uploads.allowed_ext', []), true)) {
        json_err('That file type is not allowed.', 422);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, config('uploads.allowed_mime', []), true)) {
        json_err('The file content does not match an allowed type.', 422);
    }
    // The detected content type must also agree with the extension, so a
    // renamed file cannot slip through under a benign extension.
    $extMime = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'odt'  => ['application/vnd.oasis.opendocument.text'],
        'txt'  => ['text/plain'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];
    if (!isset($extMime[$ext]) || !in_array($mime, $extMime[$ext], true)) {
        json_err('The file content does not match its extension.', 422);
    }

    $docType = in_array($_POST['doc_type'] ?? '', ['CV', 'Contract', 'Other'], true) ? $_POST['doc_type'] : 'Other';
    $storedName = bin2hex(random_bytes(20)) . '.' . $ext;
    $dir = rtrim((string)config('uploads.dir'), '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $storedName)) {
        json_err('Could not store the file. Check the uploads directory permissions.', 500);
    }

    db_query(
        'INSERT INTO documents (person_id, doc_type, version_label, stored_name, original_name, mime, size_bytes, uploaded_by, note)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $personId,
            $docType,
            trim((string)($_POST['version_label'] ?? '')) ?: null,
            $storedName,
            mb_substr($file['name'], 0, 200),
            $mime,
            (int)$file['size'],
            (int)current_user()['id'],
            trim((string)($_POST['note'] ?? '')) ?: null,
        ]
    );
    $docId = db_insert_id();
    audit('document.upload', 'document', $docId, ['person_id' => $personId, 'type' => $docType, 'name' => $file['name']]);
    json_ok(['document_id' => $docId]);
}

function list_json(string $id): void
{
    $personId = (int)$id;
    if (!documents_can_access($personId)) {
        json_err('You do not have permission to see these documents.', 403);
    }
    $docs = db_all(
        'SELECT d.id, d.doc_type, d.version_label, d.original_name, d.mime, d.size_bytes, d.uploaded_at, d.note,
                u.username AS uploader
         FROM documents d JOIN users u ON u.id = d.uploaded_by
         WHERE d.person_id = ? ORDER BY d.uploaded_at DESC',
        [$personId]
    );
    json_out(['ok' => true, 'documents' => $docs]);
}

// Issue a short-lived signed link for one document, permission checked here.
function issue_link(string $id): void
{
    $docId = (int)$id;
    $doc = db_row('SELECT * FROM documents WHERE id = ?', [$docId]);
    if (!$doc) {
        json_err('That document does not exist.', 404);
    }
    if (!documents_can_access((int)$doc['person_id'])) {
        json_err('You do not have permission to download this document.', 403);
    }
    $token = sign_download($docId, (int)current_user()['id'], 300);
    json_ok(['url' => '/files/' . $token, 'expires_in' => 300]);
}

// Gated download. The token binds document, user and expiry; the permission
// check runs again at download time in case access changed since issue.
function download(string $token): void
{
    $verified = verify_download($token);
    if (!$verified) {
        render_error(403, 'Link expired', 'This download link is not valid or has expired. Request a fresh one.');
    }
    [$docId, $tokenUserId] = $verified;
    if ($tokenUserId !== (int)current_user()['id']) {
        render_error(403, 'Access denied', 'This download link belongs to a different account.');
    }
    $doc = db_row('SELECT * FROM documents WHERE id = ?', [$docId]);
    if (!$doc) {
        render_error(404, 'Not found', 'That document no longer exists.');
    }
    if (!documents_can_access((int)$doc['person_id'])) {
        render_error(403, 'Access denied', 'You do not have permission to download this document.');
    }
    $path = rtrim((string)config('uploads.dir'), '/') . '/' . $doc['stored_name'];
    if (!is_file($path)) {
        render_error(404, 'Not found', 'The stored file is missing.');
    }

    audit('document.download', 'document', $docId, ['person_id' => (int)$doc['person_id']]);
    header('Content-Type: ' . $doc['mime']);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $doc['original_name']) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function destroy(string $id): void
{
    $docId = (int)$id;
    $doc = db_row('SELECT * FROM documents WHERE id = ?', [$docId]);
    if (!$doc) {
        json_err('That document does not exist.', 404);
    }
    $path = rtrim((string)config('uploads.dir'), '/') . '/' . $doc['stored_name'];
    db_query('DELETE FROM documents WHERE id = ?', [$docId]);
    if (is_file($path)) {
        unlink($path);
    }
    audit('document.delete', 'document', $docId, ['person_id' => (int)$doc['person_id'], 'name' => $doc['original_name']]);
    json_ok();
}
