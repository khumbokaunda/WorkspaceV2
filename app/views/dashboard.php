<?php
// Role-aware dashboard. $widgets keys exist only when the widget is visible
// to this user, so rendering is purely presentational here.
$att = $widgets['attendance'] ?? null;
$hasAttWidget = array_key_exists('attendance', $widgets);

function mx_task_chip(string $priority): string
{
    return match ($priority) {
        'Critical' => 'mx-chip-danger',
        'High'     => 'mx-chip-warning',
        'Medium'   => 'mx-chip-info',
        default    => 'mx-chip-plain',
    };
}
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 style="font-size:20px" class="mb-1">Good <?= e((int)date('G') < 12 ? 'morning' : ((int)date('G') < 17 ? 'afternoon' : 'evening')) ?>, <?= e(explode(' ', user_display_name())[0]) ?></h1>
        <div class="text-muted"><?= e(date('l, j F Y')) ?></div>
    </div>
</div>

<?php if (isset($widgets['org_overview'])): $o = $widgets['org_overview']; ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value"><?= (int)$o['headcount'] ?></span><span class="mx-stat-label">Active headcount</span></div></div></div>
<?php if (isset($o['present'])): ?>
    <div class="col-6 col-md"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-success)"><?= (int)$o['present'] ?></span><span class="mx-stat-label">Present today</span></div></div></div>
<?php endif; ?>
<?php if (isset($o['on_leave'])): ?>
    <div class="col-6 col-md"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-info)"><?= (int)$o['on_leave'] ?></span><span class="mx-stat-label">On leave today</span></div></div></div>
<?php endif; ?>
<?php if (isset($o['cert_expiring'])): ?>
    <div class="col-6 col-md"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-warning)"><?= (int)$o['cert_expiring'] ?></span><span class="mx-stat-label">Certs expiring in 90 days</span></div></div></div>
<?php endif; ?>
<?php if (isset($o['warranty_expiring'])): ?>
    <div class="col-6 col-md"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-danger)"><?= (int)$o['warranty_expiring'] ?></span><span class="mx-stat-label">Warranties ending in 60 days</span></div></div></div>
<?php endif; ?>
<?php if (isset($o['compliance_expiring'])): ?>
    <div class="col-6 col-md"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-warning)"><?= (int)$o['compliance_expiring'] ?></span><span class="mx-stat-label">Compliance docs ending in 60 days</span></div></div></div>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3">
<?php if ($hasAttWidget): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>My attendance</h2><span class="text-muted" style="font-size:12px">Late after <?= e($lateThreshold) ?></span></div>
            <div class="mx-card-body text-center" id="mx-att-widget">
<?php if ($att && $att['check_in'] && !$att['check_out']): ?>
                <div class="mb-2"><span class="mx-chip <?= $att['state'] === 'Late' ? 'mx-chip-warning' : 'mx-chip-success' ?>"><?= e($att['state']) ?></span></div>
                <p class="text-muted mb-3">Checked in at <strong class="mx-mono"><?= e(substr((string)$att['check_in'], 0, 5)) ?></strong> (<?= e($att['mode']) ?>)</p>
                <button class="btn btn-outline-primary w-100" onclick="mxCheckOut()"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Check out</button>
<?php elseif ($att && $att['check_out']): ?>
                <div class="mb-2"><span class="mx-chip <?= $att['state'] === 'Late' ? 'mx-chip-warning' : 'mx-chip-success' ?>"><?= e($att['state']) ?></span></div>
                <p class="text-muted mb-0">Done for today. In <strong class="mx-mono"><?= e(substr((string)$att['check_in'], 0, 5)) ?></strong>, out <strong class="mx-mono"><?= e(substr((string)$att['check_out'], 0, 5)) ?></strong>.</p>
<?php else: ?>
                <p class="text-muted mb-3">You have not checked in today.</p>
                <div class="d-flex gap-2 justify-content-center mb-3" role="group" aria-label="Work mode">
                    <input type="radio" class="btn-check" name="att-mode" id="mode-onsite" value="On-site" checked>
                    <label class="btn btn-outline-primary btn-sm" for="mode-onsite">On-site</label>
                    <input type="radio" class="btn-check" name="att-mode" id="mode-remote" value="Remote">
                    <label class="btn btn-outline-primary btn-sm" for="mode-remote">Remote</label>
                </div>
                <button class="btn btn-primary w-100" onclick="mxCheckIn()"><i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Check in</button>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['my_tasks'])): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>My tasks</h2><a href="/projects" class="btn btn-subtle btn-sm">All tasks</a></div>
            <div class="mx-card-body mx-flush">
<?php if (!$widgets['my_tasks']): ?>
                <div class="mx-empty"><i class="fa-regular fa-circle-check"></i><p class="mb-0">Nothing assigned to you is open.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($widgets['my_tasks'] as $t): $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <span class="mx-chip <?= mx_task_chip($t['priority']) ?>"><?= e($t['priority']) ?></span>
                        <a href="/projects?task=<?= (int)$t['id'] ?>" class="text-truncate flex-grow-1" style="color:var(--mx-text)"><?= e($t['title']) ?></a>
<?php if ($t['due_date']): ?>
                        <small class="mx-tabular" style="color:<?= $overdue ? 'var(--mx-danger)' : 'var(--mx-muted)' ?>"><?= e(date('j M', strtotime($t['due_date']))) ?></small>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['approvals'])): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Approvals queue</h2><a href="/leave" class="btn btn-subtle btn-sm">Leave area</a></div>
            <div class="mx-card-body mx-flush">
<?php if (!$widgets['approvals']): ?>
                <div class="mx-empty"><i class="fa-regular fa-thumbs-up"></i><p class="mb-0">No pending approvals.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($widgets['approvals'] as $a): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <span class="mx-avatar"><?= e(strtoupper(mb_substr($a['first_name'], 0, 1))) ?></span>
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                            <small class="text-muted"><?= e($a['type_name']) ?>, <?= e(date('j M', strtotime($a['start_date']))) ?> to <?= e(date('j M', strtotime($a['end_date']))) ?> (<?= e(rtrim(rtrim((string)$a['working_days'], '0'), '.')) ?> d)</small>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="mxQuickReview(<?= (int)$a['id'] ?>, 'approve')" aria-label="Approve"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-subtle btn-sm" onclick="mxQuickReview(<?= (int)$a['id'] ?>, 'reject')" aria-label="Reject"><i class="fa-solid fa-xmark"></i></button>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['my_leave'])): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>My leave</h2><a href="/leave" class="btn btn-subtle btn-sm">Request leave</a></div>
            <div class="mx-card-body mx-flush">
<?php if (!$widgets['my_leave']): ?>
                <div class="mx-empty"><i class="fa-regular fa-calendar"></i><p class="mb-0">No upcoming or pending leave.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($widgets['my_leave'] as $l):
        $chip = match ($l['status']) { 'Approved' => 'mx-chip-success', 'Pending' => 'mx-chip-warning', 'Rejected' => 'mx-chip-danger', default => 'mx-chip-plain' }; ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <span class="mx-chip <?= $chip ?>"><?= e($l['status']) ?></span>
                        <div class="flex-grow-1" style="font-size:13px"><?= e($l['type_name']) ?></div>
                        <small class="text-muted mx-tabular"><?= e(date('j M', strtotime($l['start_date']))) ?> to <?= e(date('j M', strtotime($l['end_date']))) ?></small>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['cert_expiry'])): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>My certifications</h2><a href="/certifications" class="btn btn-subtle btn-sm">All</a></div>
            <div class="mx-card-body mx-flush">
<?php if (!$widgets['cert_expiry']): ?>
                <div class="mx-empty"><i class="fa-solid fa-certificate"></i><p class="mb-0">Nothing expires in the next 90 days.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($widgets['cert_expiry'] as $c): $days = (int)$c['days_left']; ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <code><?= e($c['code']) ?></code>
                        <div class="flex-grow-1 text-truncate" style="font-size:13px"><?= e($c['name']) ?></div>
                        <span class="mx-chip <?= $days < 0 ? 'mx-chip-danger' : ($days <= 30 ? 'mx-chip-warning' : 'mx-chip-info') ?>">
                            <?= $days < 0 ? 'Expired' : $days . ' days' ?>
                        </span>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['compliance_expiry'])): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Compliance documents</h2><a href="/company/documents" class="btn btn-subtle btn-sm">Library</a></div>
            <div class="mx-card-body mx-flush">
<?php if (!$widgets['compliance_expiry']): ?>
                <div class="mx-empty"><i class="fa-solid fa-folder-open"></i><p class="mb-0">Nothing expires in the next 60 days.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($widgets['compliance_expiry'] as $c): $days = (int)$c['days_left']; ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <span class="mx-chip mx-chip-plain"><?= e($c['kind']) ?></span>
                        <div class="flex-grow-1 text-truncate" style="font-size:13px"><?= e($c['label']) ?></div>
                        <span class="mx-chip <?= $days < 0 ? 'mx-chip-danger' : ($days <= 30 ? 'mx-chip-warning' : 'mx-chip-info') ?>">
                            <?= $days < 0 ? 'Expired' : $days . ' days' ?>
                        </span>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['task_throughput'])): $tt = $widgets['task_throughput']; $total = max(1, array_sum($tt)); ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Task throughput</h2></div>
            <div class="mx-card-body">
<?php foreach ($tt as $status => $count):
        $color = match ($status) { 'Done' => 'var(--mx-success)', 'In Progress' => 'var(--mx-info)', 'Blocked' => 'var(--mx-danger)', default => 'var(--mx-muted)' }; ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px">
                    <span><?= e($status) ?></span><span class="mx-tabular"><?= (int)$count ?></span>
                </div>
                <div class="mb-3" style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden">
                    <div style="width:<?= (int)round($count / $total * 100) ?>%;height:100%;background:<?= $color ?>"></div>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($widgets['asset_utilization'])): $au = $widgets['asset_utilization']; $total = max(1, array_sum($au)); ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Asset utilization</h2><a href="/assets" class="btn btn-subtle btn-sm">Register</a></div>
            <div class="mx-card-body">
<?php foreach ($au as $status => $count):
        $color = match ($status) { 'Assigned' => 'var(--mx-primary)', 'Available' => 'var(--mx-success)', 'In Repair' => 'var(--mx-warning)', default => 'var(--mx-muted)' }; ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px">
                    <span><?= e($status) ?></span><span class="mx-tabular"><?= (int)$count ?></span>
                </div>
                <div class="mb-3" style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden">
                    <div style="width:<?= (int)round($count / $total * 100) ?>%;height:100%;background:<?= $color ?>"></div>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
function mxCheckIn() {
    var mode = document.querySelector('input[name="att-mode"]:checked');
    MX.api('POST', '/attendance/check-in', { mode: mode ? mode.value : 'On-site' })
        .then(function () { MX.ok('Checked in.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxCheckOut() {
    MX.api('POST', '/attendance/check-out', {})
        .then(function () { MX.ok('Checked out.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxQuickReview(id, action) {
    var title = action === 'approve' ? 'Approve this leave request?' : 'Reject this leave request?';
    Swal.fire({
        title: title,
        input: 'text',
        inputPlaceholder: 'Review note (optional)',
        showCancelButton: true,
        confirmButtonText: action === 'approve' ? 'Approve' : 'Reject',
        reverseButtons: true
    }).then(function (r) {
        if (!r.isConfirmed) return;
        MX.api('POST', '/leave/' + id + '/' + action, { review_note: MX.clean(r.value || '') })
            .then(function () { MX.ok('Done.'); setTimeout(function () { location.reload(); }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}
</script>
