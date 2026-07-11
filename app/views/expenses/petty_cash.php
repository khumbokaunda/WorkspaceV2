<?php
// Petty cash ledger with a running balance. Replenishments add to the float,
// disbursements draw it down.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Petty cash</h1>
    <a href="/expenses" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-2"></i>Expenses</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="mx-card mx-card-body p-3">
            <div class="mx-stat">
                <span class="mx-stat-value" style="color:<?= $balance < 0 ? 'var(--mx-danger)' : 'var(--mx-success)' ?>"><?= e($fmt($balance)) ?></span>
                <span class="mx-stat-label">Current balance</span>
            </div>
        </div>
    </div>
<?php if ($canManage): ?>
    <div class="col-12 col-md-8 d-flex align-items-center gap-2">
        <button class="btn btn-primary" onclick="mxNewEntry('Replenishment')"><i class="fa-solid fa-plus me-2"></i>Replenish float</button>
        <button class="btn btn-outline-primary" onclick="mxNewEntry('Disbursement')"><i class="fa-solid fa-minus me-2"></i>Record disbursement</button>
    </div>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Ledger</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$entries): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-coins"></i><p class="mb-0">No petty cash entries yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Date</th><th>Type</th><th>Purpose</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th><th>By</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
<?php foreach ($entries as $en): $isIn = $en['entry_type'] === 'Replenishment'; ?>
                <tr>
                    <td class="mx-tabular"><?= e($en['entry_date']) ?></td>
                    <td><span class="mx-chip <?= $isIn ? 'mx-chip-success' : 'mx-chip-warning' ?>"><?= e($en['entry_type']) ?></span></td>
                    <td><?= e($en['purpose'] ?: '') ?></td>
                    <td class="text-end mx-tabular"><?= $isIn ? e($fmt($en['amount'])) : '' ?></td>
                    <td class="text-end mx-tabular"><?= $isIn ? '' : e($fmt($en['amount'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($en['balance_after'])) ?></td>
                    <td><?= e($en['recorder'] ?: '') ?></td>
<?php if ($canManage): ?>
                    <td><button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteEntry(<?= (int)$en['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<script>
function mxNewEntry(type) {
    var body = '<form id="pc-form"><input type="hidden" name="entry_type" value="' + type + '">' +
        '<p class="text-muted" style="font-size:13px">' + (type === 'Replenishment' ? 'Adding funds to the petty cash float.' : 'Recording money paid out of the float.') + '</p>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" class="form-control" name="amount" required></div>' +
        '<div class="col"><label class="form-label">Date</label><input type="date" class="form-control" name="entry_date" required value="' + MX.today() + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Purpose</label><input class="form-control" name="purpose" maxlength="300"></div>' +
        '</form>';
    MX.drawer.open({ title: type === 'Replenishment' ? 'Replenish float' : 'Record disbursement', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveEntry()">Save</button>' });
}
function mxSaveEntry() {
    var form = document.getElementById('pc-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/expenses/petty-cash', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Recorded.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteEntry(id) {
    MX.confirm('Delete this entry?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/expenses/petty-cash/' + id).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
