<?php
// Payroll run detail: the register of payslips, with compute, approve and pay
// actions. Payslips are visible to employees only once the run is approved.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
$chip = ['Draft' => 'mx-chip-plain', 'Approved' => 'mx-chip-info', 'Paid' => 'mx-chip-success'][$run['status']] ?? 'mx-chip-plain';
$netTotal = 0.0;
foreach ($payslips as $p) { $netTotal += (float)$p['net_pay']; }
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0">Payroll <?= e($run['period']) ?></h1>
                <span class="mx-chip <?= $chip ?>"><?= e($run['status']) ?></span>
            </div>
            <div class="text-muted mt-1"><?= e($run['label'] ?: '') ?><?= $run['approved_at'] ? ' &middot; approved ' . e(date('j M Y', strtotime($run['approved_at']))) : '' ?><?= $run['paid_at'] ? ' &middot; paid ' . e(date('j M Y', strtotime($run['paid_at']))) : '' ?></div>
        </div>
        <div class="text-end">
            <div class="mx-stat-value" style="font-size:22px"><?= e($fmt($netTotal)) ?></div>
            <div class="mx-stat-label">Net total</div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/payroll/runs" class="btn btn-subtle btn-sm">All runs</a>
<?php if ($canManage && $run['status'] === 'Draft'): ?>
    <button class="btn btn-outline-primary btn-sm" onclick="mxCompute()"><i class="fa-solid fa-calculator me-1"></i>Compute register</button>
    <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteRun()"><i class="fa-regular fa-trash-can me-1"></i>Delete</button>
<?php endif; ?>
<?php if ($canApprove && $run['status'] === 'Draft'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxApprove()"><i class="fa-solid fa-check me-1"></i>Approve run</button>
<?php endif; ?>
<?php if ($canApprove && $run['status'] === 'Approved'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxPay()"><i class="fa-solid fa-money-bill-wave me-1"></i>Mark paid</button>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Register</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$payslips): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-table-list"></i><p class="mb-0">No payslips computed yet. Compute the register to build it from every active employee's salary structure.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Employee</th><th class="text-end">Basic</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net pay</th><th></th></tr></thead>
            <tbody>
<?php foreach ($payslips as $ps): ?>
                <tr>
                    <td><?= e($ps['person_name']) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($ps['basic'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($ps['gross'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($ps['total_deductions'])) ?></td>
                    <td class="text-end mx-tabular"><strong><?= e($fmt($ps['net_pay'])) ?></strong></td>
                    <td class="text-end text-nowrap">
                        <a href="/payroll/payslips/<?= (int)$ps['id'] ?>" target="_blank" rel="noopener" class="btn btn-subtle btn-sm" aria-label="View payslip"><i class="fa-solid fa-file-lines"></i></a>
<?php if (in_array($run['status'], ['Approved', 'Paid'], true)): ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxEmailPayslip(<?= (int)$ps['id'] ?>)" aria-label="Email payslip"><i class="fa-solid fa-envelope"></i></button>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<script>
var mxRunId = <?= (int)$run['id'] ?>;
function mxCompute() {
    MX.api('POST', '/payroll/runs/' + mxRunId + '/compute', {})
        .then(function (d) { MX.ok('Computed ' + d.payslips + ' payslip(s).'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxApprove() {
    MX.confirm('Approve this run?', 'Payslips become visible to employees and loan balances are reduced.').then(function (go) {
        if (!go) return;
        MX.api('POST', '/payroll/runs/' + mxRunId + '/approve', {}).then(function () { MX.ok('Approved.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxPay() {
    MX.confirm('Mark this run as paid?').then(function (go) {
        if (!go) return;
        MX.api('POST', '/payroll/runs/' + mxRunId + '/pay', {}).then(function () { MX.ok('Marked paid.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxDeleteRun() {
    MX.confirm('Delete this draft run?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/payroll/runs/' + mxRunId).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.href = '/payroll/runs'; }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxEmailPayslip(id) {
    MX.api('POST', '/payroll/payslips/' + id + '/email', {}).then(function () { MX.ok('Payslip emailed.'); }).catch(function (e) { MX.fail(e.message); });
}
</script>
