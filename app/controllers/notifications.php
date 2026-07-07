<?php
// Notification center: the bell dropdown reads from here.

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

function list_json(): void
{
    $user = current_user();
    $items = db_all(
        'SELECT id, body, link, module_key, is_read, created_at
         FROM notifications WHERE user_id = ?
         ORDER BY created_at DESC, id DESC LIMIT 20',
        [(int)$user['id']]
    );
    foreach ($items as &$n) {
        $n['is_read'] = (int)$n['is_read'];
        $n['when'] = notifications_relative_time($n['created_at']);
        unset($n['created_at']);
    }
    $unread = (int)db_val(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [(int)$user['id']]
    );
    json_out(['ok' => true, 'items' => $items, 'unread' => $unread]);
}

function mark_read(): void
{
    $user = current_user();
    db_query('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [(int)$user['id']]);
    json_ok();
}
