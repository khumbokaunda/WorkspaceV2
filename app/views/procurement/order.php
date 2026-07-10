<?php
// Purchase order detail: the ordered lines, cumulative received amounts, and
// the goods receipts. Receiving a trackable line can create an asset.
$fmt = fn($v) => $v === null || $v === '' ? '' : $currency . ' ' . number_format((float)$v, 2);
$num = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
$poChip = ['Issued' => 'mx-chip-info', 'Partially Received' => 'mx-chip-warning', 'Received' => 'mx-chip-success', 'Cancelled' => 'mx-chip-plain'][$po['status']] ?? 'mx-chip-plain';
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($po['po_number'] ?: ('Purchase order #' . $po['id'])) ?></h1>
                <span class="mx-chip <?= $poChip ?>"><?= e($po['status']) ?></span>
            </div>
            <div class="text-muted mt-1">
                <?= e($po['supplier_name'] ?: 'No supplier') ?>
                <?= $po['expected_delivery'] ? ' &middot; expected ' . e($po['expected_delivery']) : '' ?>
<?php if ($po['requisition_id']): ?>
                &middot; from <a href="/procurement/requisitions/<?= (int)$po['requisition_id'] ?>">requisition</a>
<?php endif; ?>
            </div>
<?php if (!empty($po['notes'])): ?>
            <div class="mt-1" style="font-size:13px"><?= e($po['notes']) ?></div>
<?php endif; ?>
        </div>
        <div class="text-end">
            <div class="mx-stat-value" style="font-size:22px"><?= e($fmt($po['total'])) ?></div>
            <div class="mx-stat-label">Order total</div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/procurement" class="btn btn-subtle btn-sm">All procurement</a>
<?php if ($canManage && $po['status'] !== 'Cancelled'): ?>
    <button class="btn btn-outline-primary btn-sm" onclick="mxEditOrder()"><i class="fa-solid fa-pen me-1"></i>Edit</button>
    <button class="btn btn-outline-primary btn-sm" onclick="mxAddLine()"><i class="fa-solid fa-plus me-1"></i>Add line</button>
<?php if ($items): ?>
    <button class="btn btn-primary btn-sm" onclick="mxReceive()"><i class="fa-solid fa-truck-ramp-box me-1"></i>Receive goods</button>
<?php endif; ?>
<?php endif; ?>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>Ordered lines</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$items): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-list"></i><p class="mb-0">No lines yet. Add what this order covers.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Line total</th><th class="text-end">Received</th><th>Asset</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
<?php foreach ($items as $it):
        $fully = (float)$it['received'] >= (float)$it['quantity']; ?>
                <tr>
                    <td><?= e($it['description']) ?></td>
                    <td class="text-end mx-tabular"><?= $num($it['quantity']) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($it['unit_price'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($it['line_total'])) ?></td>
                    <td class="text-end mx-tabular" style="color:<?= $fully ? 'var(--mx-success)' : 'var(--mx-muted)' ?>"><?= $num($it['received']) ?></td>
                    <td><?= (int)$it['is_trackable_asset'] === 1 ? '<span class="mx-chip mx-chip-plain" style="font-size:10px">trackable</span>' : '' ?></td>
<?php if ($canManage): ?>
                    <td><button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteLine(<?= (int)$it['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Goods received</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$receipts): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-truck-ramp-box"></i><p class="mb-0">Nothing received yet.</p></div>
<?php else: ?>
<?php foreach ($receipts as $gr): ?>
        <div class="px-3 py-2 border-bottom">
            <div style="font-size:13px"><strong>Received <?= e(date('j M Y', strtotime($gr['received_date']))) ?></strong> by <?= e($gr['receiver'] ?: '') ?><?= $gr['note'] ? ' &middot; ' . e($gr['note']) : '' ?></div>
            <ul class="list-unstyled mb-0 mt-1">
<?php foreach ($receiptItems as $ri): if ((int)$ri['receipt_id'] !== (int)$gr['id']) continue; ?>
                <li style="font-size:12px" class="text-muted"><?= e($ri['description']) ?> &middot; <?= $num($ri['quantity_received']) ?><?= $ri['asset_id'] ? ' &middot; <span style="color:var(--mx-success)">asset created</span>' : '' ?></li>
<?php endforeach; ?>
            </ul>
        </div>
<?php endforeach; ?>
<?php endif; ?>
    </div>
</div>

<script>
var mxPoId = <?= (int)$po['id'] ?>;
var mxPo = <?= json_encode(['po_number' => $po['po_number'], 'supplier_id' => $po['supplier_id'] !== null ? (int)$po['supplier_id'] : null, 'status' => $po['status'], 'expected_delivery' => $po['expected_delivery'], 'notes' => $po['notes']]) ?>;
var mxPoStatuses = <?= json_encode(array_values(proc_po_statuses())) ?>;
var mxAssetCats = <?= json_encode(array_values($assetCategories)) ?>;
var mxAssetsEnabled = <?= $assetsEnabled ? 'true' : 'false' ?>;
var mxPoItems = <?= json_encode(array_map(fn($it) => [
    'id' => (int)$it['id'], 'description' => $it['description'], 'quantity' => (float)$it['quantity'],
    'received' => (float)$it['received'], 'is_trackable_asset' => (int)$it['is_trackable_asset'],
], $items)) ?>;

function mxEditOrder() {
    var supFieldNote = '<div class="form-text">Change the supplier under the requisition if needed; leave as is to keep.</div>';
    var body = '<form id="poe-form">' +
        '<div class="mb-3"><label class="form-label">PO number</label><input class="form-control mx-mono" name="po_number" maxlength="60" value="' + MX.escape(mxPo.po_number || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status">' +
        mxPoStatuses.map(function (s) { return '<option' + (mxPo.status === s ? ' selected' : '') + '>' + MX.escape(s) + '</option>'; }).join('') + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Expected delivery</label><input type="date" class="form-control" name="expected_delivery" value="' + (mxPo.expected_delivery || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(mxPo.notes || '') + '"></div>' +
        '</form>';
    MX.drawer.open({ title: 'Edit purchase order', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveOrder()">Save</button>' });
}
function mxSaveOrder() {
    var form = document.getElementById('poe-form');
    MX.api('PATCH', '/procurement/orders/' + mxPoId, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxAddLine() {
    var catOpts = mxAssetCats.map(function (c) { return '<option>' + MX.escape(c) + '</option>'; }).join('');
    var body = '<form id="line-form">' +
        '<div class="mb-3"><label class="form-label">Description</label><input class="form-control" name="description" required maxlength="500"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0" class="form-control" name="quantity" value="1"></div>' +
        '<div class="col"><label class="form-label">Unit price</label><input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="0"></div></div>' +
        '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_trackable_asset" id="line-track" onchange="document.getElementById(\'line-cat\').style.display=this.checked?\'block\':\'none\'"><label class="form-check-label" for="line-track">Trackable asset (can be pushed to the asset register on receipt)</label></div>' +
        '<div class="mb-3" id="line-cat" style="display:none"><label class="form-label">Asset category</label><select class="form-select" name="asset_category">' + catOpts + '</select></div>' +
        '</form>';
    MX.drawer.open({ title: 'Add order line', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveLine()">Add</button>' });
}
function mxSaveLine() {
    var form = document.getElementById('line-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/procurement/orders/' + mxPoId + '/items', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Line added.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteLine(id) {
    MX.confirm('Remove this line?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/procurement/order-items/' + id).then(function () { MX.ok('Removed.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxReceive() {
    var rows = mxPoItems.map(function (it) {
        var outstanding = Math.max(0, it.quantity - it.received);
        var trackCell = (it.is_trackable_asset === 1 && mxAssetsEnabled)
            ? '<label style="font-size:12px"><input type="checkbox" class="mx-rcv-asset" data-id="' + it.id + '"> create asset</label>'
            : '<span class="text-muted" style="font-size:12px">-</span>';
        return '<tr><td style="font-size:13px">' + MX.escape(it.description) + '<div class="text-muted" style="font-size:11px">outstanding ' + outstanding + '</div></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm mx-rcv-qty" data-id="' + it.id + '" value="' + outstanding + '" style="width:90px"></td>' +
            '<td>' + trackCell + '</td></tr>';
    }).join('');
    var body = '<form id="rcv-form">' +
        '<div class="mb-3"><label class="form-label">Received date</label><input type="date" class="form-control" name="received_date" required value="' + MX.today() + '"></div>' +
        '<div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note" maxlength="500"></div>' +
        '<table class="table table-sm align-middle"><thead><tr><th>Item</th><th>Received</th><th>Asset</th></tr></thead><tbody>' + rows + '</tbody></table>' +
        '</form>';
    MX.drawer.open({ title: 'Receive goods', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitReceive()">Record receipt</button>' });
}
function mxSubmitReceive() {
    var form = document.getElementById('rcv-form');
    if (!form.reportValidity()) return;
    var lines = [];
    var assetIds = {};
    document.querySelectorAll('.mx-rcv-asset:checked').forEach(function (c) { assetIds[c.dataset.id] = true; });
    document.querySelectorAll('.mx-rcv-qty').forEach(function (q) {
        var qty = parseFloat(q.value);
        if (qty > 0) lines.push({ po_item_id: parseInt(q.dataset.id, 10), quantity_received: qty, make_asset: assetIds[q.dataset.id] ? 1 : 0 });
    });
    if (!lines.length) { MX.fail('Enter at least one received quantity.'); return; }
    MX.api('POST', '/procurement/orders/' + mxPoId + '/receipts', { received_date: form.received_date.value, note: MX.clean(form.note.value), lines: lines })
        .then(function (d) { MX.drawer.close(); MX.ok(d.assets_created ? ('Received. ' + d.assets_created + ' asset(s) created.') : 'Received.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
}
</script>
