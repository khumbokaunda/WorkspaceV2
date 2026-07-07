<?php
// Admin: read-only, filterable audit log.

declare(strict_types=1);

function index(): void
{
    render('admin/audit', [
        'pageTitle' => 'Audit Log',
        'breadcrumbs' => ['Admin' => null, 'Audit Log' => null],
        'entities' => db_all('SELECT DISTINCT entity FROM audit_log ORDER BY entity'),
        'users' => db_all('SELECT id, username FROM users ORDER BY username'),
    ]);
}

function list_json(): void
{
    $where = [];
    $params = [];
    if (!empty($_GET['entity'])) {
        $where[] = 'a.entity = ?';
        $params[] = (string)$_GET['entity'];
    }
    if (!empty($_GET['user_id']) && ctype_digit((string)$_GET['user_id'])) {
        $where[] = 'a.user_id = ?';
        $params[] = (int)$_GET['user_id'];
    }
    if (!empty($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['from'])) {
        $where[] = 'a.created_at >= ?';
        $params[] = $_GET['from'] . ' 00:00:00';
    }
    if (!empty($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['to'])) {
        $where[] = 'a.created_at <= ?';
        $params[] = $_GET['to'] . ' 23:59:59';
    }
    $sql = 'SELECT a.id, a.action, a.entity, a.entity_id, a.detail, a.ip_address, a.created_at,
                   u.username
            FROM audit_log a LEFT JOIN users u ON u.id = a.user_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY a.id DESC LIMIT 500';
    json_out(['ok' => true, 'entries' => db_all($sql, $params)]);
}
