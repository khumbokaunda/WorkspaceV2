<?php
// Payroll runs: the monthly registers, each computed then approved then paid.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
$chip = fn($s) => ['Draft' => 'mx-chip-plain', 'Approved' => 'mx-chip-info', 'Paid' => 'mx-chip-success'][$s] ?? 'mx-chip-plain';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Payroll runs</h1>
    <div class="d-flex gap-2">
        <a href="/payroll" class="btn btn-subtle">Payroll home</a>
<?php if ($canManage): ?>
        <button class="btn btn-primary" onclick="mxNewRun()"><i class="fa-solid fa-plus me-2"></i>New run</button>
<?php endif; ?>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Runs</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$runs): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-money-check-dollar"></i><p class="mb-0">No payroll runs yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Period</th><th>Label</th><th>Payslips</th><th class="text-end">Net total</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ($runs as $r): ?>
                <tr>
                    <td><a href="/payroll/runs/<?= (int)$r['id'] ?>" class="mx-mono"><?= e($r['period']) ?></a></td>
                    <td><?= e($r['label'] ?: '') ?></td>
                    <td class="mx-tabular"><?= (int)$r['payslip_count'] ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($r['net_total'])) ?></td>
                    <td><span class="mx-chip <?= $chip($r['status']) ?>"><?= e($r['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<script>
function mxNewRun() {
    var now = new Date();
    var period = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    var body = '<form id="run-form">' +
        '<div class="mb-3"><label class="form-label">Period (YYYY-MM)</label><input class="form-control mx-mono" name="period" required pattern="\\d{4}-\\d{2}" value="' + period + '"></div>' +
        '<div class="mb-3"><label class="form-label">Label</label><input class="form-control" name="label" maxlength="120" placeholder="e.g. July 2026 salaries"></div>' +
        '</form>';
    MX.drawer.open({ title: 'New payroll run', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitRun()">Create</button>' });
}
function mxSubmitRun() {
    var form = document.getElementById('run-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/payroll/runs', MX.formData(form))
        .then(function (d) { MX.drawer.close(); if (d.run_id) location.href = '/payroll/runs/' + d.run_id; })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
