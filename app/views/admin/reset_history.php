<?php
// Read-only reset history. Durable across resets because reset_log is on the
// preserve list.
function reset_scope_chip(string $scope): string
{
    return $scope === 'scoped'
        ? '<span class="mx-chip mx-chip-info">Scoped</span>'
        : '<span class="mx-chip mx-chip-warning">Full</span>';
}
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Reset History</h1>
    <a class="btn btn-outline-primary btn-sm" href="/admin/reset"><i class="fa-solid fa-arrow-left me-1"></i>Back to reset</a>
</div>

<div class="mx-card">
    <div class="mx-card-body mx-flush">
<?php if (!$entries): ?>
        <div class="mx-empty"><i class="fa-solid fa-clock-rotate-left"></i><p>No resets have been recorded.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>When</th><th>By</th><th>Scope</th><th>Reason</th><th>Archive</th><th>Result</th></tr></thead>
            <tbody>
<?php foreach ($entries as $r): ?>
                <tr>
                    <td class="mx-tabular" style="white-space:nowrap"><?= e(date('j M Y H:i', strtotime($r['reset_at']))) ?></td>
                    <td>
                        <?= e($r['initiated_username']) ?>
<?php if (!empty($r['initiated_email'])): ?>
                        <span class="text-muted d-block" style="font-size:11px"><?= e($r['initiated_email']) ?></span>
<?php endif; ?>
                    </td>
                    <td><?= reset_scope_chip($r['scope']) ?></td>
                    <td>
                        <strong><?= e($r['reason_category']) ?></strong>
                        <span class="text-muted d-block" style="font-size:12px"><?= e($r['reason_text']) ?></span>
                    </td>
                    <td>
<?php if (!empty($r['archive_filename'])): ?>
                        <code style="font-size:11px"><?= e($r['archive_filename']) ?></code>
                        <span class="text-muted d-block" style="font-size:11px" title="<?= e($r['archive_checksum'] ?? '') ?>">
                            checksum <?= e(substr((string)$r['archive_checksum'], 0, 16)) ?>...
                            <?php if ($r['archive_size_bytes']): ?>, <?= e(number_format((int)$r['archive_size_bytes'] / 1024, 0)) ?> KB<?php endif; ?>
                        </span>
<?php else: ?>
                        <span class="text-muted">None</span>
<?php endif; ?>
                    </td>
                    <td>
<?php if ((int)$r['success'] === 1): ?>
                        <span class="mx-chip mx-chip-success">Completed</span>
<?php else: ?>
                        <span class="mx-chip mx-chip-danger">Failed or incomplete</span>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
