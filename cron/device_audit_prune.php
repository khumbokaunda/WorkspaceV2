<?php
// Scheduled job: prune device audit events older than the configured retention
// window, so the table does not grow without bound and personal data is not
// kept longer than useful. Run daily from cron:
//   30 2 * * * php /path/to/meridian/cron/device_audit_prune.php
// Safe to re-run. Does nothing when the module has never been used.

declare(strict_types=1);

define('MERIDIAN_CLI', true);

require dirname(__DIR__) . '/app/bootstrap.php';

$days = (int)setting('device_audit_retention_days', '180');
if ($days < 1) {
    $days = 180;
}

// Count first so the audit record is meaningful, then delete.
$cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
$toPrune = (int)db_val('SELECT COUNT(*) FROM device_events WHERE created_at < ?', [$cutoff]);

if ($toPrune > 0) {
    db_query('DELETE FROM device_events WHERE created_at < ?', [$cutoff]);
    audit('device_audit.prune', 'device_events', null, ['retention_days' => $days, 'pruned' => $toPrune]);
}

echo 'Device events pruned: ' . $toPrune . ' (older than ' . $days . ' days)' . PHP_EOL;
