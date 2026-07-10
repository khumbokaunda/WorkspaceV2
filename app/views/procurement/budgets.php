<?php
// Department budgets with committed and remaining, drawn from approved
// requisitions and purchase orders.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Budgets</h1>
<?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="mxNewBudget()"><i class="fa-solid fa-plus me-2"></i>Add budget</button>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Department budgets</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$budgets): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-money-bill-trend-up"></i><p class="mb-0">No budgets set. Add one per department and period.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Department</th><th>Period</th><th class="text-end">Budget</th><th class="text-end">Committed</th><th class="text-end">Remaining</th><th style="width:30%">Used</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
<?php foreach ($budgets as $b):
        $pct = (float)$b['amount'] > 0 ? min(100, round($b['committed'] / (float)$b['amount'] * 100)) : 0;
        $barCol = $pct >= 100 ? 'var(--mx-danger)' : ($pct >= 80 ? 'var(--mx-warning)' : 'var(--mx-success)'); ?>
                <tr>
                    <td><?= e($b['department']) ?></td>
                    <td><?= e($b['period']) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($b['amount'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($b['committed'])) ?></td>
                    <td class="text-end mx-tabular" style="color:<?= $b['remaining'] < 0 ? 'var(--mx-danger)' : 'inherit' ?>"><?= e($fmt($b['remaining'])) ?></td>
                    <td>
                        <div style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden"><div style="width:<?= $pct ?>%;height:100%;background:<?= $barCol ?>"></div></div>
                        <small class="text-muted"><?= $pct ?>% committed</small>
                    </td>
<?php if ($canManage): ?>
                    <td class="text-nowrap">
                        <button class="btn btn-subtle btn-sm" onclick='mxEditBudget(<?= json_encode(["id"=>(int)$b["id"],"department"=>$b["department"],"period"=>$b["period"],"amount"=>$b["amount"],"notes"=>$b["notes"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteBudget(<?= (int)$b['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
                    </td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<script>
function mxBudgetForm(b) {
    b = b || {};
    return '<form id="budget-form">' +
        (b.id ? '' : '') +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Department</label><input class="form-control" name="department" required maxlength="120" value="' + MX.escape(b.department || '') + '"' + (b.id ? ' readonly' : '') + '></div>' +
        '<div class="col"><label class="form-label">Period</label><input class="form-control" name="period" required maxlength="60" placeholder="e.g. 2026" value="' + MX.escape(b.period || '') + '"' + (b.id ? ' readonly' : '') + '></div></div>' +
        '<div class="mb-3"><label class="form-label">Budget amount</label><input type="number" step="0.01" min="0" class="form-control" name="amount" required value="' + (b.amount != null ? b.amount : '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(b.notes || '') + '"></div>' +
        (b.id ? '<div class="form-text">Department and period identify the budget and are fixed. Delete and re-add to change them.</div>' : '') +
        '</form>';
}
function mxNewBudget() {
    MX.drawer.open({ title: 'Add budget', body: mxBudgetForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveBudget()">Save</button>' });
}
function mxEditBudget(b) {
    MX.drawer.open({ title: 'Edit budget', body: mxBudgetForm(b),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveBudget()">Save</button>' });
}
function mxSaveBudget() {
    var form = document.getElementById('budget-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/budgets', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteBudget(id) {
    MX.confirm('Delete this budget?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/budgets/' + id).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
