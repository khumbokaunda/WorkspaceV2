<?php
// Admin: device audit and shared-device detection. A detection and audit tool,
// never a lock. It surfaces the pattern of one device acting for many people,
// which is the signature of buddy punching, for an administrator to review and
// act on through normal management. It records and it displays; it never blocks
// a login or a check-in, and it asserts no wrongdoing.

declare(strict_types=1);

// How far back the shared-device analysis looks, in days.
const DEVICE_AUDIT_LOOKBACK_DAYS = 30;

function index(): void
{
    // Viewing staff device data is itself an access event worth recording.
    audit('device_audit.view', 'device_events', null);

    render('admin/device_audit', [
        'pageTitle' => 'Device Audit',
        'breadcrumbs' => ['Admin' => null, 'Device Audit' => null],
        'clusters' => device_shared_clusters(),
        'users' => db_all(
            "SELECT u.id, u.username,
                    TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) AS name
             FROM users u LEFT JOIN people p ON p.id = u.person_id ORDER BY u.username"
        ),
        'captureLocation' => setting('attendance_capture_location', '0') === '1',
        'retentionDays' => (int)setting('device_audit_retention_days', '180'),
    ]);
}

// Panel 6.1: clusters where one device_hash produced check_in events for two or
// more distinct users on the same calendar day, most suspicious first (largest
// and tightest clusters at the top).
function device_shared_clusters(): array
{
    $rows = db_all(
        "SELECT device_hash, DATE(created_at) AS day,
                COUNT(DISTINCT user_id) AS users,
                MIN(created_at) AS first_at, MAX(created_at) AS last_at,
                COUNT(*) AS events
         FROM device_events
         WHERE event_type = 'check_in' AND user_id IS NOT NULL
           AND created_at >= (NOW() - INTERVAL ? DAY)
         GROUP BY device_hash, DATE(created_at)
         HAVING users >= 2
         ORDER BY users DESC, TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) ASC
         LIMIT 100",
        [DEVICE_AUDIT_LOOKBACK_DAYS]
    );

    $clusters = [];
    foreach ($rows as $r) {
        $hash = (string)$r['device_hash'];
        $day = (string)$r['day'];
        $members = db_all(
            "SELECT de.user_id, u.username, MIN(de.created_at) AS at,
                    TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) AS name
             FROM device_events de
             JOIN users u ON u.id = de.user_id
             LEFT JOIN people p ON p.id = u.person_id
             WHERE de.device_hash = ? AND de.event_type = 'check_in' AND DATE(de.created_at) = ?
             GROUP BY de.user_id, u.username
             ORDER BY at",
            [$hash, $day]
        );
        $rep = db_row(
            'SELECT confidence, user_agent, platform, screen, language, ip_address
             FROM device_events WHERE device_hash = ? ORDER BY created_at DESC LIMIT 1',
            [$hash]
        ) ?: [];
        $clusters[] = [
            'device_hash' => $hash,
            'label' => device_label($hash),
            'day' => $day,
            'user_count' => (int)$r['users'],
            'first_at' => (string)$r['first_at'],
            'last_at' => (string)$r['last_at'],
            'span_seconds' => max(0, strtotime((string)$r['last_at']) - strtotime((string)$r['first_at'])),
            'confidence' => (string)($rep['confidence'] ?? 'weak'),
            'user_agent' => (string)($rep['user_agent'] ?? ''),
            'platform' => (string)($rep['platform'] ?? ''),
            'screen' => (string)($rep['screen'] ?? ''),
            'language' => (string)($rep['language'] ?? ''),
            'members' => $members,
        ];
    }
    return $clusters;
}

// The label an administrator gave a device, if any.
function device_label(string $hash): ?string
{
    $v = db_val('SELECT label FROM device_labels WHERE device_hash = ?', [$hash]);
    return $v !== null ? (string)$v : null;
}

// Panel 6.2: every device a chosen user has acted from, newest first, with how
// many distinct users each of those devices has served.
function user_history(string $id): void
{
    audit('device_audit.user_history', 'user', (int)$id);
    $userId = (int)$id;
    $devices = db_all(
        "SELECT de.device_hash,
                MAX(de.created_at) AS last_at,
                MIN(de.created_at) AS first_at,
                COUNT(*) AS events,
                MAX(de.confidence = 'strong') AS any_strong,
                (SELECT COUNT(DISTINCT d2.user_id) FROM device_events d2
                   WHERE d2.device_hash = de.device_hash AND d2.user_id IS NOT NULL) AS distinct_users,
                (SELECT user_agent FROM device_events d3 WHERE d3.device_hash = de.device_hash ORDER BY created_at DESC LIMIT 1) AS user_agent,
                (SELECT platform FROM device_events d4 WHERE d4.device_hash = de.device_hash ORDER BY created_at DESC LIMIT 1) AS platform
         FROM device_events de
         WHERE de.user_id = ?
         GROUP BY de.device_hash
         ORDER BY last_at DESC
         LIMIT 200",
        [$userId]
    );
    foreach ($devices as &$d) {
        $d['label'] = device_label((string)$d['device_hash']);
        $d['distinct_users'] = (int)$d['distinct_users'];
        $d['events'] = (int)$d['events'];
        $d['confidence'] = (int)$d['any_strong'] === 1 ? 'strong' : 'weak';
        unset($d['any_strong']);
    }
    unset($d);
    json_out(['ok' => true, 'devices' => $devices]);
}

// Panel 6.3: recent device events for a filterable table.
function events_json(): void
{
    $where = [];
    $params = [];
    if (!empty($_GET['type']) && in_array($_GET['type'], ['login', 'check_in', 'check_out'], true)) {
        $where[] = 'de.event_type = ?';
        $params[] = $_GET['type'];
    }
    if (!empty($_GET['confidence']) && in_array($_GET['confidence'], ['strong', 'weak'], true)) {
        $where[] = 'de.confidence = ?';
        $params[] = $_GET['confidence'];
    }
    if (!empty($_GET['user_id']) && ctype_digit((string)$_GET['user_id'])) {
        $where[] = 'de.user_id = ?';
        $params[] = (int)$_GET['user_id'];
    }
    $sql = "SELECT de.id, de.user_id, u.username, de.event_type, de.device_hash, de.confidence,
                   de.ip_address, de.in_range, de.created_at
            FROM device_events de LEFT JOIN users u ON u.id = de.user_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY de.id DESC LIMIT 500';
    json_out(['ok' => true, 'events' => db_all($sql, $params)]);
}

// Name or rename a recognised device. Cosmetic only.
function save_label(): void
{
    $hash = in_str('device_hash');
    $label = in_str('label');
    if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
        json_err('That device is not recognised.', 422);
    }
    if ($label === '') {
        db_query('DELETE FROM device_labels WHERE device_hash = ?', [$hash]);
        audit('device_label.clear', 'device_labels', null, ['device' => substr($hash, 0, 12)]);
        json_ok(['label' => null]);
    }
    $label = mb_substr($label, 0, 120);
    db_query(
        'INSERT INTO device_labels (device_hash, label, noted_by) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), noted_by = VALUES(noted_by), noted_at = NOW()',
        [$hash, $label, (int)(current_user()['id'] ?? 0) ?: null]
    );
    audit('device_label.save', 'device_labels', null, ['device' => substr($hash, 0, 12), 'label' => $label]);
    json_ok(['label' => $label]);
}
