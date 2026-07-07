<?php
// Scheduled job: reconcile stored certification statuses with expiry dates,
// then email holders (and optionally their managers) at the configured
// day marks before expiry. Run daily from cron:
//   0 7 * * * php /path/to/meridian/cron/certification_reminders.php
// Safe to re-run: each certification and day mark pairs at most one
// reminder per day, tracked in the audit log.

declare(strict_types=1);

define('MERIDIAN_CLI', true);

require dirname(__DIR__) . '/app/bootstrap.php';

// 1. Sync: anything past expiry that still claims Active becomes Expired.
$expired = db_all(
    "SELECT id, person_id, name, code FROM certifications
     WHERE expires_on IS NOT NULL AND expires_on < CURDATE() AND status = 'Active'"
);
foreach ($expired as $c) {
    db_query("UPDATE certifications SET status = 'Expired' WHERE id = ?", [(int)$c['id']]);
    audit('certification.expired', 'certification', (int)$c['id'], ['code' => $c['code']]);
    notify_person(
        (int)$c['person_id'],
        'Your certification ' . $c['code'] . ' (' . $c['name'] . ') has expired.',
        '/certifications',
        'certifications'
    );
}
echo 'Marked expired: ' . count($expired) . PHP_EOL;

// 2. Reminders at the configured day marks (default 90, 30, 7).
$marks = array_filter(array_map('intval', explode(',', setting('cert_reminder_days', '90,30,7'))));
$notifyManager = setting('cert_notify_manager', '1') === '1';
$sent = 0;

foreach ($marks as $mark) {
    $due = db_all(
        "SELECT c.id, c.person_id, c.name, c.code, c.expires_on,
                p.first_name, p.last_name, p.email, p.manager_id
         FROM certifications c
         JOIN people p ON p.id = c.person_id AND p.employment_status = 'Active'
         WHERE c.expires_on = DATE_ADD(CURDATE(), INTERVAL ? DAY)
           AND c.status <> 'In Progress'",
        [$mark]
    );
    foreach ($due as $c) {
        // Skip when a reminder for this certification and mark went out today.
        $already = db_val(
            "SELECT COUNT(*) FROM audit_log
             WHERE action = 'certification.reminder' AND entity = 'certification'
               AND entity_id = ? AND DATE(created_at) = CURDATE()
               AND JSON_EXTRACT(detail, '$.mark') = ?",
            [(int)$c['id'], $mark]
        );
        if ((int)$already > 0) {
            continue;
        }
        $subject = 'Certification ' . $c['code'] . ' expires in ' . $mark . ' days';
        $bodyHtml = '<p>' . e($c['first_name']) . ', your certification '
            . e($c['code']) . ' (' . e($c['name']) . ') expires on '
            . e($c['expires_on']) . '. Plan the renewal now.</p>';
        send_mail($c['email'], $c['first_name'] . ' ' . $c['last_name'], $subject, $bodyHtml);
        notify_person(
            (int)$c['person_id'],
            'Your certification ' . $c['code'] . ' expires in ' . $mark . ' days (' . $c['expires_on'] . ').',
            '/certifications',
            'certifications'
        );
        if ($notifyManager && $c['manager_id'] !== null) {
            $mgr = db_row('SELECT first_name, last_name, email FROM people WHERE id = ?', [(int)$c['manager_id']]);
            if ($mgr) {
                send_mail(
                    $mgr['email'],
                    $mgr['first_name'] . ' ' . $mgr['last_name'],
                    'Team certification expiring: ' . $c['code'],
                    '<p>' . e($c['first_name'] . ' ' . $c['last_name']) . ' holds ' . e($c['code'])
                    . ' (' . e($c['name']) . ') which expires on ' . e($c['expires_on']) . '.</p>'
                );
            }
        }
        audit('certification.reminder', 'certification', (int)$c['id'], ['mark' => $mark, 'expires_on' => $c['expires_on']]);
        $sent++;
    }
}
echo 'Reminders sent: ' . $sent . PHP_EOL;
