<?php
// Staff loans and advances. An approved loan becomes a recurring deduction on
// the employee's payslip until the outstanding balance is cleared.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
$chip = fn($s) => ['Pending' => 'mx-chip-warning', 'Active' => 'mx-chip-info', 'Cleared' => 'mx-chip-success', 'Rejected' => 'mx-chip-danger'][$s] ?? 'mx-chip-plain';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Staff loans</h1>
    <div class="d-flex gap-2">
        <a href="/payroll" class="btn btn-subtle">Payroll home</a>
        <button class="btn btn-primary" onclick="mxNewLoan()"><i class="fa-solid fa-plus me-2"></i>New loan</button>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Loans and advances</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$loans): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-hand-holding-dollar"></i><p class="mb-0">No loans recorded.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Employee</th><th class="text-end">Principal</th><th class="text-end">Installment</th><th class="text-end">Outstanding</th><th>Status</th><th></th></tr></thead>
            <tbody>
<?php foreach ($loans as $l): ?>
                <tr>
                    <td><?= e($l['person_name']) ?><?= $l['reason'] ? '<span class="text-muted d-block" style="font-size:11px">' . e($l['reason']) . '</span>' : '' ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($l['principal'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($l['installment'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($l['outstanding_balance'])) ?></td>
                    <td><span class="mx-chip <?= $chip($l['status']) ?>"><?= e($l['status']) ?></span></td>
                    <td class="text-end text-nowrap">
<?php if ($l['status'] === 'Pending' && $canApprove): ?>
                        <button class="btn btn-outline-primary btn-sm" onclick="mxDecideLoan(<?= (int)$l['id'] ?>, 'Active')" aria-label="Approve"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-subtle btn-sm" onclick="mxDecideLoan(<?= (int)$l['id'] ?>, 'Rejected')" aria-label="Reject"><i class="fa-solid fa-xmark"></i></button>
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
var mxLoanPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;

function mxNewLoan() {
    var opts = '<option value="">Choose a person</option>' + mxLoanPeople.map(function (p) { return '<option value="' + p.id + '">' + MX.escape(p.name) + '</option>'; }).join('');
    var body = '<form id="loan-form">' +
        '<div class="mb-3"><label class="form-label">Employee</label><select class="form-select" name="person_id" required>' + opts + '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Principal</label><input type="number" step="0.01" min="0.01" class="form-control" name="principal" required></div>' +
        '<div class="col"><label class="form-label">Monthly installment</label><input type="number" step="0.01" min="0.01" class="form-control" name="installment" required></div></div>' +
        '<div class="mb-3"><label class="form-label">Reason</label><input class="form-control" name="reason" maxlength="500"></div>' +
        '<div class="form-text">The loan is created pending. Once approved it becomes a recurring payslip deduction until cleared.</div>' +
        '</form>';
    MX.drawer.open({ title: 'New staff loan', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitLoan()">Create</button>' });
}
function mxSubmitLoan() {
    var form = document.getElementById('loan-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/payroll/loans', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Loan created.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDecideLoan(id, decision) {
    MX.confirm(decision === 'Active' ? 'Approve this loan?' : 'Reject this loan?').then(function (go) {
        if (!go) return;
        MX.api('POST', '/payroll/loans/' + id + '/decide', { decision: decision })
            .then(function () { MX.ok('Recorded.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
