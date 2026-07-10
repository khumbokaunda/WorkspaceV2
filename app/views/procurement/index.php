<?php
// Procurement overview: my item requests, the open requests an administrator
// consolidates, the requisitions, and the purchase orders.
$fmt = fn($v) => $v === null || $v === '' ? '' : $currency . ' ' . number_format((float)$v, 2);
$reqChip = fn($s) => [
    'Submitted' => 'mx-chip-info', 'Consolidated' => 'mx-chip-warning', 'Approved' => 'mx-chip-success',
    'Rejected' => 'mx-chip-danger', 'Fulfilled' => 'mx-chip-plain',
][$s] ?? 'mx-chip-plain';
$rqChip = fn($s) => ['Draft' => 'mx-chip-plain', 'Submitted' => 'mx-chip-info', 'Approved' => 'mx-chip-success', 'Rejected' => 'mx-chip-danger'][$s] ?? 'mx-chip-plain';
$poChip = fn($s) => ['Issued' => 'mx-chip-info', 'Partially Received' => 'mx-chip-warning', 'Received' => 'mx-chip-success', 'Cancelled' => 'mx-chip-plain'][$s] ?? 'mx-chip-plain';
$urgChip = fn($u) => ['Low' => 'mx-chip-plain', 'Normal' => 'mx-chip-info', 'High' => 'mx-chip-warning', 'Urgent' => 'mx-chip-danger'][$u] ?? 'mx-chip-plain';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Procurement</h1>
    <div class="d-flex gap-2">
<?php if ($canManage): ?>
        <button class="btn btn-outline-primary" onclick="mxNewOrder()"><i class="fa-solid fa-file-invoice me-2"></i>New purchase order</button>
<?php endif; ?>
<?php if ($canRequest): ?>
        <button class="btn btn-primary" onclick="mxNewRequest()"><i class="fa-solid fa-plus me-2"></i>Request an item</button>
<?php endif; ?>
    </div>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>My item requests</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$myRequests): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-cart-shopping"></i><p class="mb-0">You have not requested anything yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Est. cost</th><th>Urgency</th><th>Needed by</th><th>Status</th><th></th></tr></thead>
            <tbody>
<?php foreach ($myRequests as $r): ?>
                <tr>
                    <td><?= e($r['item_description']) ?></td>
                    <td class="text-end mx-tabular"><?= rtrim(rtrim(number_format((float)$r['quantity'], 2), '0'), '.') ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($r['estimated_unit_cost'])) ?></td>
                    <td><span class="mx-chip <?= $urgChip($r['urgency']) ?>"><?= e($r['urgency']) ?></span></td>
                    <td class="mx-tabular"><?= e($r['needed_by'] ?: '') ?></td>
                    <td><span class="mx-chip <?= $reqChip($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td class="text-nowrap">
<?php if ($r['status'] === 'Submitted'): ?>
                        <button class="btn btn-subtle btn-sm" onclick='mxEditRequest(<?= json_encode(["id"=>(int)$r["id"],"item_description"=>$r["item_description"],"quantity"=>$r["quantity"],"estimated_unit_cost"=>$r["estimated_unit_cost"],"justification"=>$r["justification"],"urgency"=>$r["urgency"],"needed_by"=>$r["needed_by"],"department"=>$r["department"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteRequest(<?= (int)$r['id'] ?>)" aria-label="Withdraw"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="mx-card mb-3">
    <div class="mx-card-header">
        <h2>Open requests to consolidate</h2>
        <button class="btn btn-primary btn-sm" onclick="mxConsolidate()"><i class="fa-solid fa-layer-group me-1"></i>Consolidate selected</button>
    </div>
    <div class="mx-card-body mx-flush">
<?php if (!$openRequests): ?>
        <div class="mx-empty py-4"><i class="fa-regular fa-square-check"></i><p class="mb-0">No open requests awaiting consolidation.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th style="width:36px"></th><th>Item</th><th>Requester</th><th class="text-end">Qty</th><th class="text-end">Est. cost</th><th>Urgency</th><th>Dept</th></tr></thead>
            <tbody>
<?php foreach ($openRequests as $r): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input mx-open-req" value="<?= (int)$r['id'] ?>"></td>
                    <td><?= e($r['item_description']) ?></td>
                    <td><?= e($r['requester']) ?></td>
                    <td class="text-end mx-tabular"><?= rtrim(rtrim(number_format((float)$r['quantity'], 2), '0'), '.') ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($r['estimated_unit_cost'])) ?></td>
                    <td><span class="mx-chip <?= $urgChip($r['urgency']) ?>"><?= e($r['urgency']) ?></span></td>
                    <td><?= e($r['department'] ?: '') ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canManage || $canApprove): ?>
<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>Requisitions</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$requisitions): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-file-lines"></i><p class="mb-0">No requisitions yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Reference</th><th>Purpose</th><th>Department</th><th class="text-end">Estimate</th><th>Items</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ($requisitions as $rq): ?>
                <tr>
                    <td><a href="/procurement/requisitions/<?= (int)$rq['id'] ?>" class="mx-mono"><?= e($rq['reference'] ?: ('#' . $rq['id'])) ?></a></td>
                    <td><?= e($rq['purpose']) ?></td>
                    <td><?= e($rq['department'] ?: '') ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($rq['total_estimate'])) ?></td>
                    <td class="mx-tabular"><?= (int)$rq['item_count'] ?></td>
                    <td><span class="mx-chip <?= $rqChip($rq['status']) ?>"><?= e($rq['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="mx-card">
    <div class="mx-card-header"><h2>Purchase orders</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$orders): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-file-invoice"></i><p class="mb-0">No purchase orders yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>PO number</th><th>Supplier</th><th class="text-end">Total</th><th>Expected</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ($orders as $po): ?>
                <tr>
                    <td><a href="/procurement/orders/<?= (int)$po['id'] ?>" class="mx-mono"><?= e($po['po_number'] ?: ('#' . $po['id'])) ?></a></td>
                    <td><?= e($po['supplier_name'] ?: '') ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($po['total'])) ?></td>
                    <td class="mx-tabular"><?= e($po['expected_delivery'] ?: '') ?></td>
                    <td><span class="mx-chip <?= $poChip($po['status']) ?>"><?= e($po['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
var mxProcUrgencies = <?= json_encode(array_values($urgencies)) ?>;
var mxProcSuppliers = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name']], $suppliers)) ?>;

function mxRequestForm(r) {
    r = r || {};
    return '<form id="ireq-form">' +
        '<div class="mb-3"><label class="form-label">Item description</label><input class="form-control" name="item_description" required maxlength="500" value="' + MX.escape(r.item_description || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0" class="form-control" name="quantity" value="' + (r.quantity != null ? r.quantity : 1) + '"></div>' +
        '<div class="col"><label class="form-label">Estimated unit cost</label><input type="number" step="0.01" min="0" class="form-control" name="estimated_unit_cost" value="' + (r.estimated_unit_cost != null ? r.estimated_unit_cost : '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Urgency</label><select class="form-select" name="urgency">' +
        mxProcUrgencies.map(function (u) { return '<option' + ((r.urgency || 'Normal') === u ? ' selected' : '') + '>' + MX.escape(u) + '</option>'; }).join('') + '</select></div>' +
        '<div class="col"><label class="form-label">Needed by</label><input type="date" class="form-control" name="needed_by" value="' + (r.needed_by || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Department</label><input class="form-control" name="department" maxlength="120" value="' + MX.escape(r.department || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Justification</label><textarea class="form-control" name="justification" rows="2" maxlength="1000">' + MX.escape(r.justification || '') + '</textarea></div>' +
        '</form>';
}
function mxNewRequest() {
    MX.drawer.open({ title: 'Request an item', body: mxRequestForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitRequest(null)">Submit</button>' });
}
function mxEditRequest(r) {
    MX.drawer.open({ title: 'Edit request', body: mxRequestForm(r),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitRequest(' + r.id + ')">Save</button>' });
}
function mxSubmitRequest(id) {
    var form = document.getElementById('ireq-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/procurement/requests/' + id, MX.formData(form)) : MX.api('POST', '/procurement/requests', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteRequest(id) {
    MX.confirm('Withdraw this request?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/procurement/requests/' + id).then(function () { MX.ok('Withdrawn.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}

function mxConsolidate() {
    var ids = [];
    document.querySelectorAll('.mx-open-req:checked').forEach(function (b) { ids.push(parseInt(b.value, 10)); });
    if (!ids.length) { MX.fail('Select at least one request.'); return; }
    var body = '<form id="cons-form">' +
        '<p class="text-muted" style="font-size:13px">Consolidating ' + ids.length + ' request' + (ids.length > 1 ? 's' : '') + ' into a new requisition.</p>' +
        '<div class="mb-3"><label class="form-label">Purpose</label><input class="form-control" name="purpose" required maxlength="250"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Reference</label><input class="form-control mx-mono" name="reference" maxlength="60"></div>' +
        '<div class="col"><label class="form-label">Period</label><input class="form-control" name="period" maxlength="60" placeholder="e.g. Q3 2026"></div></div>' +
        '<div class="mb-3"><label class="form-label">Department</label><input class="form-control" name="department" maxlength="120"></div>' +
        '</form>';
    MX.drawer.open({ title: 'Consolidate into requisition', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitConsolidate()">Create requisition</button>' });
    window.mxConsIds = ids;
}
function mxSubmitConsolidate() {
    var form = document.getElementById('cons-form');
    if (!form.reportValidity()) return;
    var payload = MX.formData(form);
    payload.item_request_ids = window.mxConsIds;
    MX.api('POST', '/procurement/requisitions', payload)
        .then(function (d) { MX.drawer.close(); MX.ok('Requisition created.'); if (d.requisition_id) location.href = '/procurement/requisitions/' + d.requisition_id; })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxNewOrder() {
    var supOpts = '<option value="">No supplier</option>' + mxProcSuppliers.map(function (s) { return '<option value="' + s.id + '">' + MX.escape(s.name) + '</option>'; }).join('');
    var body = '<form id="po-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">PO number</label><input class="form-control mx-mono" name="po_number" maxlength="60"></div>' +
        '<div class="col"><label class="form-label">Requisition id</label><input type="number" min="1" class="form-control" name="requisition_id" placeholder="optional"></div></div>' +
        '<div class="mb-3"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id">' + supOpts + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Expected delivery</label><input type="date" class="form-control" name="expected_delivery"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500"></div>' +
        '</form>';
    MX.drawer.open({ title: 'New purchase order', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitOrder()">Create</button>' });
}
function mxSubmitOrder() {
    var form = document.getElementById('po-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/procurement/orders', MX.formData(form))
        .then(function (d) { MX.drawer.close(); MX.ok('Created.'); if (d.po_id) location.href = '/procurement/orders/' + d.po_id; })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
