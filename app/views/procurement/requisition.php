<?php
// Requisition detail: the consolidated items, the approval decision, fund
// releases, and any purchase orders raised against it.
$fmt = fn($v) => $v === null || $v === '' ? '' : $currency . ' ' . number_format((float)$v, 2);
$rqChip = ['Draft' => 'mx-chip-plain', 'Submitted' => 'mx-chip-info', 'Approved' => 'mx-chip-success', 'Rejected' => 'mx-chip-danger'][$req['status']] ?? 'mx-chip-plain';
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($req['purpose']) ?></h1>
                <span class="mx-chip <?= $rqChip ?>"><?= e($req['status']) ?></span>
            </div>
            <div class="text-muted mt-1">
                <?= $req['reference'] ? '<span class="mx-mono">' . e($req['reference']) . '</span> &middot; ' : '' ?>
                <?= e($req['department'] ?: 'No department') ?><?= $req['period'] ? ' &middot; ' . e($req['period']) : '' ?>
                &middot; raised by <?= e($req['creator'] ?: 'unknown') ?>
            </div>
<?php if ($req['approved_at']): ?>
            <div class="mt-1" style="font-size:13px"><?= e($req['status']) ?> by <?= e($req['approver'] ?: '') ?> on <?= e($req['approved_at']) ?><?= $req['approval_note'] ? '. ' . e($req['approval_note']) : '' ?></div>
<?php endif; ?>
        </div>
        <div class="text-end">
            <div class="mx-stat-value" style="font-size:22px"><?= e($fmt($req['total_estimate'])) ?></div>
            <div class="mx-stat-label">Estimated total</div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/procurement" class="btn btn-subtle btn-sm">All procurement</a>
<?php if ($canManage && $req['status'] === 'Draft'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxSubmitReq()"><i class="fa-solid fa-paper-plane me-1"></i>Submit for approval</button>
<?php endif; ?>
<?php if ($canApprove && in_array($req['status'], ['Submitted', 'Draft'], true)): ?>
    <button class="btn btn-primary btn-sm" onclick="mxDecide('Approved')"><i class="fa-solid fa-check me-1"></i>Approve</button>
    <button class="btn btn-outline-primary btn-sm" onclick="mxDecide('Rejected')"><i class="fa-solid fa-xmark me-1"></i>Reject</button>
<?php endif; ?>
<?php if ($canApprove && $req['status'] === 'Approved'): ?>
    <button class="btn btn-outline-primary btn-sm" onclick="mxFundRelease()"><i class="fa-solid fa-money-bill-transfer me-1"></i>Release funds</button>
<?php endif; ?>
<?php if ($canManage && $req['status'] === 'Approved'): ?>
    <button class="btn btn-outline-primary btn-sm" onclick="mxOrderFromReq()"><i class="fa-solid fa-file-invoice me-1"></i>Raise purchase order</button>
<?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Consolidated items</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$items): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-boxes-stacked"></i><p class="mb-0">No items on this requisition.</p></div>
<?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
                    <thead><tr><th>Item</th><th>Requester</th><th class="text-end">Qty</th><th class="text-end">Est. cost</th><th>Status</th></tr></thead>
                    <tbody>
<?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['item_description']) ?></td>
                            <td><?= e($it['requester']) ?></td>
                            <td class="text-end mx-tabular"><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?></td>
                            <td class="text-end mx-tabular"><?= e($fmt($it['estimated_unit_cost'])) ?></td>
                            <td><?= e($it['status']) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table></div>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Fund releases</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$fundReleases): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-money-bill-transfer"></i><p class="mb-0">No funds released yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($fundReleases as $fr): ?>
                    <li class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($fmt($fr['amount'])) ?></strong><?= $fr['payment_method'] ? ' &middot; ' . e($fr['payment_method']) : '' ?></div>
                            <small class="text-muted"><?= e($fr['account'] ?: '') ?> &middot; by <?= e($fr['authoriser'] ?: '') ?> on <?= e(date('j M Y', strtotime($fr['released_at']))) ?><?= $fr['note'] ? ' &middot; ' . e($fr['note']) : '' ?></small>
                        </div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header"><h2>Purchase orders</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$orders): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-file-invoice"></i><p class="mb-0">No purchase orders raised.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($orders as $po): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <a href="/procurement/orders/<?= (int)$po['id'] ?>" class="mx-mono"><?= e($po['po_number'] ?: ('#' . $po['id'])) ?></a>
                        <div class="flex-grow-1" style="font-size:13px"><?= e($po['supplier_name'] ?: '') ?></div>
                        <span class="mx-tabular"><?= e($fmt($po['total'])) ?></span>
                        <span class="mx-chip mx-chip-plain"><?= e($po['status']) ?></span>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var mxReqId = <?= (int)$req['id'] ?>;
var mxReqEstimate = <?= (float)$req['total_estimate'] ?>;
var mxReqCurrency = <?= json_encode($currency) ?>;

function mxSubmitReq() {
    MX.api('POST', '/procurement/requisitions/' + mxReqId + '/submit', {})
        .then(function () { MX.ok('Submitted for approval.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxDecide(decision) {
    var body = '<form id="dec-form"><input type="hidden" name="decision" value="' + decision + '">' +
        '<p style="font-size:13px">' + (decision === 'Approved' ? 'Approve this requisition? Its items will be marked approved.' : 'Reject this requisition? Its items return to the open pool.') + '</p>' +
        '<div class="mb-3"><label class="form-label">Note</label><textarea class="form-control" name="approval_note" rows="2" maxlength="500"></textarea></div></form>';
    MX.drawer.open({ title: decision === 'Approved' ? 'Approve requisition' : 'Reject requisition', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitDecide()">' + decision + '</button>' });
}
function mxSubmitDecide() {
    var form = document.getElementById('dec-form');
    MX.api('POST', '/procurement/requisitions/' + mxReqId + '/decide', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Recorded.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxFundRelease() {
    var body = '<form id="fr-form">' +
        '<div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" min="0" class="form-control" name="amount" required value="' + mxReqEstimate + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Payment method</label><input class="form-control" name="payment_method" maxlength="80" placeholder="Transfer, cheque"></div>' +
        '<div class="col"><label class="form-label">Account</label><input class="form-control" name="account" maxlength="120"></div></div>' +
        '<div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note" maxlength="500"></div></form>';
    MX.drawer.open({ title: 'Release funds', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitFund()">Authorise release</button>' });
}
function mxSubmitFund() {
    var form = document.getElementById('fr-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/procurement/requisitions/' + mxReqId + '/fund-release', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Funds released.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxOrderFromReq() {
    MX.api('POST', '/procurement/orders', { requisition_id: mxReqId })
        .then(function (d) { MX.ok('Purchase order created.'); if (d.po_id) location.href = '/procurement/orders/' + d.po_id; })
        .catch(function (e) { MX.fail(e.message); });
}
</script>
