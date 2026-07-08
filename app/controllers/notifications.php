<?php
// Notification center. Two-state read model, one row per recipient user:
//   seen_at IS NULL  means unseen, and drives the bell badge count.
//   read_at IS NULL  means unread, and drives the per-item highlight.
// Every query is scoped to the authenticated user. Ownership and identity are
// never taken from the client.

declare(strict_types=1);

function notifications_relative_time(string $when): string
{
    $diff = time() - strtotime($when);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' h ago';
    }
    return date('j M Y', strtotime($when));
}

// Panel contents: the user's recent notifications, newest first. read_at
// drives the unread highlight, exposed to the client as is_read.
function list_json(): void
{
    $user = current_user();
    $items = db_all(
        'SELECT id, body, link, module_key, read_at, created_at
         FROM notifications WHERE user_id = ?
         ORDER BY created_at DESC, id DESC LIMIT 20',
        [(int)$user['id']]
    );
    foreach ($items as &$n) {
        $n['id'] = (int)$n['id'];
        $n['is_read'] = $n['read_at'] !== null ? 1 : 0;
        $n['when'] = notifications_relative_time($n['created_at']);
        unset($n['read_at'], $n['created_at']);
    }
    unset($n);
    json_out(['ok' => true, 'items' => $items]);
}

// Bell badge count: the user's unseen rows.
function unseen_count(): void
{
    $user = current_user();
    $count = (int)db_val(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND seen_at IS NULL',
        [(int)$user['id']]
    );
    json_out(['ok' => true, 'unseen' => $count]);
}

// Opening the panel marks everything seen. Seeing is not reading: unread rows
// stay highlighted.
function mark_seen(): void
{
    $user = current_user();
    db_query(
        'UPDATE notifications SET seen_at = NOW() WHERE user_id = ? AND seen_at IS NULL',
        [(int)$user['id']]
    );
    json_ok();
}

// Clicking one item marks that single row read, only if it belongs to the
// current user.
function mark_one_read(string $id): void
{
    $user = current_user();
    db_query(
        'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL',
        [(int)$id, (int)$user['id']]
    );
    json_ok();
}

// Mark all of the user's unread rows read.
function mark_all_read(): void
{
    $user = current_user();
    db_query(
        'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
        [(int)$user['id']]
    );
    json_ok();
}
