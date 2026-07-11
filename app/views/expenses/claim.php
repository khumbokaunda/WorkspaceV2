<?php
// Expense claim detail: lines with receipts, and the approval and reimbursement
// controls.
$fmt = fn($v) => $v === null || $v === '' ? '' : $currency . ' ' . number_format((float)$v, 2);
$chip = ['Draft' => 'mx-chip-plain', 'Submitted' => 'mx-chip-info', 'Approved' => 'mx-chip-success', 'Rejected' => 'mx-chip-danger', 'Reimbursed' => 'mx-chip-success'][$claim['status']] ?? 'mx-chip-plain';
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($claim['title']) ?></h1>
                <span class="mx-chip <?= $chip ?>"><?= e($claim['status']) ?></span>
            </div>
            <div class="text-muted mt-1">
                by <?= e($claim['claimant'] ?: 'unknown') ?>
                <?= $claim['submitted_at'] ? ' &middot; submitted ' . e(date('j M Y', strtotime($claim['submitted_at']))) : '' ?>
            </div>
<?php if ($claim['approved_at']): ?>
            <div class="mt-1" style="font-size:13px"><?= e($claim['status'] === 'Rejected' ? 'Rejected' : 'Approved') ?> by <?= e($claim['approver'] ?: '') ?> on <?= e(date('j M Y', strtotime($claim['approved_at']))) ?><?= $claim['approval_note'] ? '. ' . e($claim['approval_note']) : '' ?><?= $claim['reimbursed_at'] ? '. Reimbursed ' . e(date('j M Y', strtotime($claim['reimbursed_at']))) . '.' : '' ?></div>
<?php endif; ?>
        </div>
        <div class="text-end">
            <div class="mx-stat-value" style="font-size:22px"><?= e($fmt($claim['total'])) ?></div>
            <div class="mx-stat-label">Claim total</div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/expenses" class="btn btn-subtle btn-sm">All expenses</a>
<?php if ($canEdit): ?>
    <button class="btn btn-outline-primary btn-sm" onclick="mxAddLine()"><i class="fa-solid fa-plus me-1"></i>Add line</button>
    <button class="btn btn-primary btn-sm" onclick="mxSubmit()"><i class="fa-solid fa-paper-plane me-1"></i>Submit for approval</button>
    <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteClaim()"><i class="fa-regular fa-trash-can me-1"></i>Delete</button>
<?php endif; ?>
<?php if ($canApprove && $claim['status'] === 'Submitted'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxDecide('Approved')"><i class="fa-solid fa-check me-1"></i>Approve</button>
    <button class="btn btn-outline-primary btn-sm" onclick="mxDecide('Rejected')"><i class="fa-solid fa-xmark me-1"></i>Reject</button>
<?php endif; ?>
<?php if ($canApprove && $claim['status'] === 'Approved'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxReimburse()"><i class="fa-solid fa-money-bill-wave me-1"></i>Mark reimbursed</button>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Lines</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$lines): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-list"></i><p class="mb-0">No lines yet. Add each expense with its receipt.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th>Receipt</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
<?php foreach ($lines as $l): ?>
                <tr>
                    <td class="mx-tabular"><?= e($l['expense_date'] ?: '') ?></td>
                    <td><?= e($l['category'] ?: '') ?></td>
                    <td><?= e($l['description']) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($l['amount'])) ?></td>
                    <td>
<?php if ($l['has_receipt']): ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxDownloadReceipt(<?= (int)$l['id'] ?>)" aria-label="Download receipt"><i class="fa-solid fa-download"></i></button>
<?php else: ?>
                        <span class="text-muted" style="font-size:12px">none</span>
<?php endif; ?>
                    </td>
<?php if ($canEdit): ?>
                    <td class="text-nowrap">
                        <button class="btn btn-subtle btn-sm" onclick='mxEditLine(<?= json_encode(["id"=>(int)$l["id"],"expense_date"=>$l["expense_date"],"category"=>$l["category"],"description"=>$l["description"],"amount"=>$l["amount"],"has_receipt"=>$l["has_receipt"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteLine(<?= (int)$l['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
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
var mxClaimId = <?= (int)$claim['id'] ?>;
var mxExpCategories = <?= json_encode(array_values($categories)) ?>;

function mxLineForm(l) {
    l = l || {};
    var catOpts = '<option value="">Uncategorised</option>' + mxExpCategories.map(function (c) {
        return '<option' + (l.category === c ? ' selected' : '') + '>' + MX.escape(c) + '</option>';
    }).join('');
    return '<form id="line-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Date</label><input type="date" class="form-control" name="expense_date" value="' + (l.expense_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Category</label><select class="form-select" name="category">' + catOpts + '</select></div></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><input class="form-control" name="description" required maxlength="500" value="' + MX.escape(l.description || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" min="0" class="form-control" name="amount" required value="' + (l.amount != null ? l.amount : '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Receipt' + (l.has_receipt ? ' (replace)' : '') + '</label><input type="file" class="form-control" id="line-receipt" accept=".pdf,.png,.jpg,.jpeg"></div>' +
        '</form>';
}
function mxAddLine() {
    MX.drawer.open({ title: 'Add line', body: mxLineForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveLine(null)">Add</button>' });
}
function mxEditLine(l) {
    MX.drawer.open({ title: 'Edit line', body: mxLineForm(l),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveLine(' + l.id + ')">Save</button>' });
}
function mxSaveLine(id) {
    var form = document.getElementById('line-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    ['expense_date', 'category', 'description', 'amount'].forEach(function (k) { fd.append(k, MX.clean(form[k].value)); });
    var file = document.getElementById('line-receipt');
    if (file.files[0]) fd.append('receipt', file.files[0]);
    var url = '/expenses/' + mxClaimId + '/lines';
    if (id) { url = '/expenses/lines/' + id; fd.append('_method', 'PATCH'); }
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: fd, redirect: 'manual' })
        .then(function (r) { return r.json(); }).then(function (d) {
            if (d.ok === false) { MX.fail(d.error); MX.showFieldErrors(form, d.fields); return; }
            MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500);
        }).catch(function () { MX.fail(); });
}
function mxDeleteLine(id) {
    MX.confirm('Remove this line?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/expenses/lines/' + id).then(function () { MX.ok('Removed.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxDownloadReceipt(id) {
    MX.api('POST', '/expenses/lines/' + id + '/receipt-link', {})
        .then(function (d) { window.location.href = d.url; }).catch(function (e) { MX.fail(e.message); });
}
function mxSubmit() {
    MX.confirm('Submit this claim for approval?', 'You will not be able to edit it after submitting.').then(function (go) {
        if (!go) return;
        MX.api('POST', '/expenses/' + mxClaimId + '/submit', {}).then(function () { MX.ok('Submitted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxDeleteClaim() {
    MX.confirm('Delete this claim?', 'All lines and receipts are removed.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/expenses/' + mxClaimId).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.href = '/expenses'; }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxDecide(decision) {
    var body = '<form id="dec-form"><input type="hidden" name="decision" value="' + decision + '">' +
        '<div class="mb-3"><label class="form-label">Note</label><textarea class="form-control" name="approval_note" rows="2" maxlength="500"></textarea></div></form>';
    MX.drawer.open({ title: decision === 'Approved' ? 'Approve claim' : 'Reject claim', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitDecide()">' + decision + '</button>' });
}
function mxSubmitDecide() {
    var form = document.getElementById('dec-form');
    MX.api('POST', '/expenses/' + mxClaimId + '/decide', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Recorded.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
}
function mxReimburse() {
    MX.confirm('Mark this claim reimbursed?').then(function (go) {
        if (!go) return;
        MX.api('POST', '/expenses/' + mxClaimId + '/reimburse', {}).then(function () { MX.ok('Marked reimbursed.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
