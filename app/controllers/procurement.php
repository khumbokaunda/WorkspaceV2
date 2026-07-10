<?php
// Procurement and office administration. Staff raise item requests; the office
// administrator consolidates them into a requisition; an approver authorises it
// and releases funds; a purchase order is raised to a supplier; and goods are
// received against it, with trackable items pushed into the Assets module.
//
// Access: procurement.request raises requests, procurement.manage consolidates
// and buys, procurement.approve authorises and releases funds. A requester sees
// their own requests without procurement.manage.

declare(strict_types=1);

function proc_urgencies(): array { return ['Low', 'Normal', 'High', 'Urgent']; }
function proc_request_statuses(): array { return ['Submitted', 'Consolidated', 'Approved', 'Rejected', 'Fulfilled']; }
function proc_requisition_statuses(): array { return ['Draft', 'Submitted', 'Approved', 'Rejected']; }
function proc_po_statuses(): array { return ['Issued', 'Partially Received', 'Received', 'Cancelled']; }
function proc_asset_categories(): array { return ['Laptop', 'Desktop', 'Monitor', 'Phone', 'Network', 'Peripheral', 'Furniture', 'Other']; }

function proc_can_manage(): bool { return user_can('procurement.manage'); }
function proc_can_approve(): bool { return user_can('procurement.approve'); }

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

function index(): void
{
    $uid = (int)current_user()['id'];
    $canManage = proc_can_manage();

    $myRequests = db_all(
        'SELECT * FROM item_requests WHERE requested_by = ? ORDER BY created_at DESC',
        [$uid]
    );
    // Requests still awaiting consolidation, for the administrator.
    $openRequests = $canManage
        ? db_all(
            "SELECT ir.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS requester
             FROM item_requests ir LEFT JOIN users u ON u.id = ir.requested_by LEFT JOIN people p ON p.id = u.person_id
             WHERE ir.status = 'Submitted' ORDER BY ir.created_at",
            []
          )
        : [];
    $requisitions = $canManage || proc_can_approve()
        ? db_all(
            "SELECT rq.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS creator,
                    (SELECT COUNT(*) FROM item_requests i WHERE i.requisition_id = rq.id) AS item_count
             FROM requisitions rq LEFT JOIN users u ON u.id = rq.created_by LEFT JOIN people p ON p.id = u.person_id
             ORDER BY rq.created_at DESC",
            []
          )
        : [];
    $orders = $canManage
        ? db_all(
            'SELECT po.*, s.name AS supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id
             ORDER BY po.created_at DESC',
            []
          )
        : [];

    render('procurement/index', [
        'pageTitle' => 'Procurement',
        'breadcrumbs' => ['Procurement' => null],
        'canManage' => $canManage,
        'canApprove' => proc_can_approve(),
        'canRequest' => user_can('procurement.request'),
        'myRequests' => $myRequests,
        'openRequests' => $openRequests,
        'requisitions' => $requisitions,
        'orders' => $orders,
        'urgencies' => proc_urgencies(),
        'suppliers' => module_enabled('suppliers') ? db_all('SELECT id, name FROM suppliers ORDER BY name') : [],
        'currency' => setting('currency', 'MWK'),
    ]);
}

// ---------------------------------------------------------------------------
// Item requests
// ---------------------------------------------------------------------------

function request_input(): array
{
    $desc = in_str('item_description');
    $errors = [];
    if ($desc === '') {
        $errors['item_description'] = 'Required.';
    }
    foreach (['quantity', 'estimated_unit_cost'] as $n) {
        $v = in_str($n);
        if ($v !== '' && !is_numeric($v)) {
            $errors[$n] = 'Enter a number.';
        }
    }
    if (in_str('needed_by') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str('needed_by'))) {
        $errors['needed_by'] = 'Invalid date.';
    }
    $urgency = in_array(in_str('urgency'), proc_urgencies(), true) ? in_str('urgency') : 'Normal';
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'item_description' => mb_substr($desc, 0, 500),
        'quantity' => in_str('quantity') !== '' ? (float)in_str('quantity') : 1.0,
        'estimated_unit_cost' => in_str('estimated_unit_cost') !== '' ? (float)in_str('estimated_unit_cost') : null,
        'justification' => in_str('justification') ?: null,
        'urgency' => $urgency,
        'needed_by' => in_str('needed_by') ?: null,
        'department' => in_str('department') ?: null,
    ];
}

function create_request(): void
{
    $r = request_input();
    db_query(
        'INSERT INTO item_requests (requested_by, item_description, quantity, estimated_unit_cost, justification, urgency, needed_by, department)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            (int)current_user()['id'], $r['item_description'], $r['quantity'], $r['estimated_unit_cost'],
            $r['justification'], $r['urgency'], $r['needed_by'], $r['department'],
        ]
    );
    $id = db_insert_id();
    audit('item_request.create', 'item_request', $id, ['item' => $r['item_description']]);
    json_ok(['request_id' => $id]);
}

// The requester may edit or withdraw their own request while it is still just
// Submitted (not yet consolidated). procurement.manage may edit any Submitted.
function request_editable(array $req): bool
{
    if ($req['status'] !== 'Submitted') {
        return false;
    }
    return proc_can_manage() || (int)$req['requested_by'] === (int)current_user()['id'];
}

function update_request(string $id): void
{
    $req = db_row('SELECT * FROM item_requests WHERE id = ?', [(int)$id]);
    if (!$req) {
        json_err('That request does not exist.', 404);
    }
    if (!request_editable($req)) {
        json_err('That request can no longer be edited.', 403);
    }
    $r = request_input();
    db_query(
        'UPDATE item_requests SET item_description = ?, quantity = ?, estimated_unit_cost = ?, justification = ?, urgency = ?, needed_by = ?, department = ? WHERE id = ?',
        [$r['item_description'], $r['quantity'], $r['estimated_unit_cost'], $r['justification'], $r['urgency'], $r['needed_by'], $r['department'], (int)$id]
    );
    audit('item_request.update', 'item_request', (int)$id, []);
    json_ok();
}

function delete_request(string $id): void
{
    $req = db_row('SELECT * FROM item_requests WHERE id = ?', [(int)$id]);
    if (!$req) {
        json_err('That request does not exist.', 404);
    }
    if (!request_editable($req)) {
        json_err('That request can no longer be withdrawn.', 403);
    }
    db_query('DELETE FROM item_requests WHERE id = ?', [(int)$id]);
    audit('item_request.delete', 'item_request', (int)$id, []);
    json_ok();
}

// ---------------------------------------------------------------------------
// Requisitions (consolidation and approval)
// ---------------------------------------------------------------------------

function requisition(string $id): void
{
    $reqId = (int)$id;
    $rq = db_row(
        "SELECT rq.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cp.first_name,''),' ',COALESCE(cp.last_name,''))),''), cu.username) AS creator,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(ap.first_name,''),' ',COALESCE(ap.last_name,''))),''), au.username) AS approver
         FROM requisitions rq
         LEFT JOIN users cu ON cu.id = rq.created_by LEFT JOIN people cp ON cp.id = cu.person_id
         LEFT JOIN users au ON au.id = rq.approved_by LEFT JOIN people ap ON ap.id = au.person_id
         WHERE rq.id = ?",
        [$reqId]
    );
    if (!$rq) {
        render_error(404, 'Not found', 'That requisition does not exist.');
    }
    render('procurement/requisition', [
        'pageTitle' => 'Requisition ' . ($rq['reference'] ?: ('#' . $reqId)),
        'breadcrumbs' => ['Procurement' => '/procurement', 'Requisition' => null],
        'req' => $rq,
        'items' => db_all(
            "SELECT ir.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS requester
             FROM item_requests ir LEFT JOIN users u ON u.id = ir.requested_by LEFT JOIN people p ON p.id = u.person_id
             WHERE ir.requisition_id = ? ORDER BY ir.id",
            [$reqId]
        ),
        'fundReleases' => db_all(
            "SELECT fr.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS authoriser
             FROM fund_releases fr LEFT JOIN users u ON u.id = fr.authorised_by LEFT JOIN people p ON p.id = u.person_id
             WHERE fr.requisition_id = ? ORDER BY fr.id",
            [$reqId]
        ),
        'orders' => db_all('SELECT po.*, s.name AS supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id WHERE po.requisition_id = ? ORDER BY po.id', [$reqId]),
        'canManage' => proc_can_manage(),
        'canApprove' => proc_can_approve(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

// Consolidate a set of submitted item requests into a new requisition.
function create_requisition(): void
{
    $purpose = in_str('purpose');
    if ($purpose === '') {
        json_err('A purpose is required.', 422, ['purpose' => 'Required.']);
    }
    $ids = input()['item_request_ids'] ?? [];
    $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    if (!$ids) {
        json_err('Select at least one item request to consolidate.', 422, ['item_request_ids' => 'Select at least one.']);
    }
    // Only still-submitted requests may be consolidated.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all("SELECT id, quantity, estimated_unit_cost FROM item_requests WHERE status = 'Submitted' AND id IN ($placeholders)", $ids);
    if (!$rows) {
        json_err('None of the selected requests are available to consolidate.', 422);
    }
    $total = 0.0;
    foreach ($rows as $r) {
        $total += (float)$r['quantity'] * (float)($r['estimated_unit_cost'] ?? 0);
    }
    db_query(
        'INSERT INTO requisitions (reference, purpose, period, department, total_estimate, status, created_by)
         VALUES (?,?,?,?,?,?,?)',
        [
            in_str('reference') ?: null, mb_substr($purpose, 0, 250), in_str('period') ?: null,
            in_str('department') ?: null, $total, 'Draft', (int)current_user()['id'],
        ]
    );
    $reqId = db_insert_id();
    $validIds = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($validIds), '?'));
    db_query("UPDATE item_requests SET status = 'Consolidated', requisition_id = ? WHERE id IN ($ph)", array_merge([$reqId], $validIds));
    audit('requisition.create', 'requisition', $reqId, ['items' => count($validIds), 'total' => $total]);
    json_ok(['requisition_id' => $reqId]);
}

function submit_requisition(string $id): void
{
    $rq = db_row('SELECT * FROM requisitions WHERE id = ?', [(int)$id]);
    if (!$rq) {
        json_err('That requisition does not exist.', 404);
    }
    if ($rq['status'] !== 'Draft') {
        json_err('Only a draft requisition can be submitted.', 409);
    }
    db_query("UPDATE requisitions SET status = 'Submitted' WHERE id = ?", [(int)$id]);
    notify_role('admin', 'A requisition is awaiting approval: ' . $rq['purpose'], '/procurement/requisitions/' . (int)$id, 'procurement');
    audit('requisition.submit', 'requisition', (int)$id, []);
    json_ok();
}

function decide_requisition(string $id): void
{
    if (!proc_can_approve()) {
        json_err('You do not have permission to approve requisitions.', 403);
    }
    $rq = db_row('SELECT * FROM requisitions WHERE id = ?', [(int)$id]);
    if (!$rq) {
        json_err('That requisition does not exist.', 404);
    }
    if (!in_array($rq['status'], ['Submitted', 'Draft'], true)) {
        json_err('That requisition has already been decided.', 409);
    }
    $decision = in_str('decision');
    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        json_err('Choose approve or reject.', 422);
    }
    $note = in_str('approval_note') ?: null;
    db_query(
        'UPDATE requisitions SET status = ?, approved_by = ?, approved_at = NOW(), approval_note = ? WHERE id = ?',
        [$decision, (int)current_user()['id'], $note, (int)$id]
    );
    if ($decision === 'Approved') {
        db_query("UPDATE item_requests SET status = 'Approved' WHERE requisition_id = ?", [(int)$id]);
    } else {
        // Rejected requests return to the pool so they can be reconsolidated.
        db_query("UPDATE item_requests SET status = 'Submitted', requisition_id = NULL WHERE requisition_id = ?", [(int)$id]);
    }
    audit('requisition.decide', 'requisition', (int)$id, ['decision' => $decision]);
    json_ok();
}

function add_fund_release(string $id): void
{
    if (!proc_can_approve()) {
        json_err('You do not have permission to release funds.', 403);
    }
    $rq = db_row('SELECT * FROM requisitions WHERE id = ?', [(int)$id]);
    if (!$rq) {
        json_err('That requisition does not exist.', 404);
    }
    if ($rq['status'] !== 'Approved') {
        json_err('Funds can only be released against an approved requisition.', 409);
    }
    $amount = in_str('amount');
    if ($amount === '' || !is_numeric($amount)) {
        json_err('Enter the amount to release.', 422, ['amount' => 'Enter a number.']);
    }
    db_query(
        'INSERT INTO fund_releases (requisition_id, amount, payment_method, account, authorised_by, note) VALUES (?,?,?,?,?,?)',
        [(int)$id, (float)$amount, in_str('payment_method') ?: null, in_str('account') ?: null, (int)current_user()['id'], in_str('note') ?: null]
    );
    audit('fund_release.create', 'requisition', (int)$id, ['amount' => (float)$amount]);
    json_ok(['fund_release_id' => db_insert_id()]);
}

// ---------------------------------------------------------------------------
// Purchase orders
// ---------------------------------------------------------------------------

function order(string $id): void
{
    $poId = (int)$id;
    $po = db_row('SELECT po.*, s.name AS supplier_name, rq.purpose AS requisition_purpose FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN requisitions rq ON rq.id = po.requisition_id WHERE po.id = ?', [$poId]);
    if (!$po) {
        render_error(404, 'Not found', 'That purchase order does not exist.');
    }
    $items = db_all('SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id', [$poId]);
    foreach ($items as &$it) {
        $it['line_total'] = (float)$it['quantity'] * (float)$it['unit_price'];
        $it['received'] = (float)db_val('SELECT COALESCE(SUM(quantity_received),0) FROM goods_receipt_items WHERE po_item_id = ?', [(int)$it['id']], 0);
    }
    render('procurement/order', [
        'pageTitle' => 'Purchase order ' . ($po['po_number'] ?: ('#' . $poId)),
        'breadcrumbs' => ['Procurement' => '/procurement', 'Purchase order' => null],
        'po' => $po,
        'items' => $items,
        'receipts' => db_all(
            "SELECT gr.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS receiver
             FROM goods_receipts gr LEFT JOIN users u ON u.id = gr.received_by LEFT JOIN people p ON p.id = u.person_id
             WHERE gr.po_id = ? ORDER BY gr.id",
            [$poId]
        ),
        'receiptItems' => db_all(
            'SELECT gri.*, gr.received_date, poi.description FROM goods_receipt_items gri
             JOIN goods_receipts gr ON gr.id = gri.receipt_id
             JOIN purchase_order_items poi ON poi.id = gri.po_item_id
             WHERE gr.po_id = ? ORDER BY gri.id',
            [$poId]
        ),
        'assetCategories' => proc_asset_categories(),
        'assetsEnabled' => module_enabled('assets'),
        'canManage' => proc_can_manage(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function create_order(): void
{
    if (!proc_can_manage()) {
        json_err('You do not have permission to raise purchase orders.', 403);
    }
    $reqId = in_int('requisition_id');
    if ($reqId) {
        $rq = db_row('SELECT status FROM requisitions WHERE id = ?', [$reqId]);
        if (!$rq) {
            json_err('Unknown requisition.', 422, ['requisition_id' => 'Unknown.']);
        }
        if ($rq['status'] !== 'Approved') {
            json_err('A purchase order can only be raised against an approved requisition.', 409);
        }
    }
    $supplierId = in_int('supplier_id');
    if ($supplierId && !db_val('SELECT id FROM suppliers WHERE id = ?', [$supplierId])) {
        json_err('Unknown supplier.', 422, ['supplier_id' => 'Unknown.']);
    }
    if (in_str('expected_delivery') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str('expected_delivery'))) {
        json_err('Enter a valid delivery date.', 422, ['expected_delivery' => 'Invalid date.']);
    }
    db_query(
        'INSERT INTO purchase_orders (po_number, requisition_id, supplier_id, status, expected_delivery, total, notes, created_by)
         VALUES (?,?,?,?,?,0,?,?)',
        [in_str('po_number') ?: null, $reqId ?: null, $supplierId ?: null, 'Issued', in_str('expected_delivery') ?: null, in_str('notes') ?: null, (int)current_user()['id']]
    );
    $poId = db_insert_id();
    audit('purchase_order.create', 'purchase_order', $poId, ['requisition_id' => $reqId]);
    json_ok(['po_id' => $poId]);
}

function update_order(string $id): void
{
    if (!proc_can_manage()) {
        json_err('You do not have permission to edit purchase orders.', 403);
    }
    $po = db_row('SELECT * FROM purchase_orders WHERE id = ?', [(int)$id]);
    if (!$po) {
        json_err('That purchase order does not exist.', 404);
    }
    $status = in_str('status');
    if ($status !== '' && !in_array($status, proc_po_statuses(), true)) {
        json_err('Invalid status.', 422, ['status' => 'Invalid.']);
    }
    if (in_str('expected_delivery') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str('expected_delivery'))) {
        json_err('Enter a valid delivery date.', 422, ['expected_delivery' => 'Invalid date.']);
    }
    $supplierId = in_int('supplier_id');
    db_query(
        'UPDATE purchase_orders SET po_number = ?, supplier_id = ?, status = ?, expected_delivery = ?, notes = ? WHERE id = ?',
        [in_str('po_number') ?: null, $supplierId ?: null, $status ?: $po['status'], in_str('expected_delivery') ?: null, in_str('notes') ?: null, (int)$id]
    );
    audit('purchase_order.update', 'purchase_order', (int)$id, ['status' => $status ?: $po['status']]);
    json_ok();
}

function po_recalc_total(int $poId): void
{
    $total = (float)db_val('SELECT COALESCE(SUM(quantity * unit_price),0) FROM purchase_order_items WHERE po_id = ?', [$poId], 0);
    db_query('UPDATE purchase_orders SET total = ? WHERE id = ?', [$total, $poId]);
}

function add_order_item(string $id): void
{
    if (!proc_can_manage()) {
        json_err('You do not have permission to edit purchase orders.', 403);
    }
    $poId = (int)$id;
    if (!db_val('SELECT id FROM purchase_orders WHERE id = ?', [$poId])) {
        json_err('That purchase order does not exist.', 404);
    }
    $desc = in_str('description');
    if ($desc === '') {
        json_err('A line description is required.', 422, ['description' => 'Required.']);
    }
    foreach (['quantity', 'unit_price'] as $n) {
        if (in_str($n) !== '' && !is_numeric(in_str($n))) {
            json_err('Enter a number.', 422, [$n => 'Enter a number.']);
        }
    }
    $cat = in_array(in_str('asset_category'), proc_asset_categories(), true) ? in_str('asset_category') : null;
    db_query(
        'INSERT INTO purchase_order_items (po_id, item_request_id, description, quantity, unit_price, is_trackable_asset, asset_category)
         VALUES (?,?,?,?,?,?,?)',
        [
            $poId, in_int('item_request_id') ?: null, mb_substr($desc, 0, 500),
            in_str('quantity') !== '' ? (float)in_str('quantity') : 1.0,
            in_str('unit_price') !== '' ? (float)in_str('unit_price') : 0.0,
            in_int('is_trackable_asset') ? 1 : 0, $cat,
        ]
    );
    $itemId = db_insert_id();
    po_recalc_total($poId);
    audit('purchase_order.item.add', 'purchase_order', $poId, ['item_id' => $itemId]);
    json_ok(['item_id' => $itemId]);
}

function delete_order_item(string $id): void
{
    if (!proc_can_manage()) {
        json_err('You do not have permission to edit purchase orders.', 403);
    }
    $item = db_row('SELECT * FROM purchase_order_items WHERE id = ?', [(int)$id]);
    if (!$item) {
        json_err('That line does not exist.', 404);
    }
    db_query('DELETE FROM purchase_order_items WHERE id = ?', [(int)$id]);
    po_recalc_total((int)$item['po_id']);
    audit('purchase_order.item.delete', 'purchase_order', (int)$item['po_id'], ['item_id' => (int)$id]);
    json_ok();
}

// Record a goods receipt against a purchase order. Each received line may push
// a trackable item into the Assets module, closing the loop from request to
// tracked asset. The PO status is recomputed from cumulative received amounts.
function add_receipt(string $id): void
{
    if (!proc_can_manage()) {
        json_err('You do not have permission to receive goods.', 403);
    }
    $poId = (int)$id;
    $po = db_row('SELECT * FROM purchase_orders WHERE id = ?', [$poId]);
    if (!$po) {
        json_err('That purchase order does not exist.', 404);
    }
    $receivedDate = in_str('received_date');
    if ($receivedDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
        json_err('Enter a valid received date.', 422, ['received_date' => 'Invalid date.']);
    }
    $lines = input()['lines'] ?? [];
    if (!is_array($lines) || !$lines) {
        json_err('Record at least one received line.', 422);
    }

    db_query(
        'INSERT INTO goods_receipts (po_id, received_date, received_by, note) VALUES (?,?,?,?)',
        [$poId, $receivedDate, (int)current_user()['id'], in_str('note') ?: null]
    );
    $receiptId = db_insert_id();
    $assetsMade = 0;

    foreach ($lines as $line) {
        $poItemId = (int)($line['po_item_id'] ?? 0);
        $qty = (float)($line['quantity_received'] ?? 0);
        if ($poItemId <= 0 || $qty <= 0) {
            continue;
        }
        $poItem = db_row('SELECT * FROM purchase_order_items WHERE id = ? AND po_id = ?', [$poItemId, $poId]);
        if (!$poItem) {
            continue;
        }
        $assetId = null;
        $makeAsset = !empty($line['make_asset']) && (int)$poItem['is_trackable_asset'] === 1 && module_enabled('assets');
        if ($makeAsset) {
            $category = in_array($poItem['asset_category'], proc_asset_categories(), true) ? $poItem['asset_category'] : 'Other';
            $tag = 'PO' . $poId . '-' . strtoupper(bin2hex(random_bytes(3)));
            db_query(
                "INSERT INTO assets (asset_tag, name, category, purchase_date, status, note)
                 VALUES (?,?,?,?,'Available',?)",
                [$tag, mb_substr($poItem['description'], 0, 160), $category, $receivedDate, 'Received on ' . ($po['po_number'] ?: ('PO#' . $poId))]
            );
            $assetId = db_insert_id();
            $assetsMade++;
        }
        db_query(
            'INSERT INTO goods_receipt_items (receipt_id, po_item_id, quantity_received, asset_id) VALUES (?,?,?,?)',
            [$receiptId, $poItemId, $qty, $assetId]
        );
    }

    // Recompute the PO status from cumulative received against ordered.
    $ordered = (float)db_val('SELECT COALESCE(SUM(quantity),0) FROM purchase_order_items WHERE po_id = ?', [$poId], 0);
    $received = (float)db_val(
        'SELECT COALESCE(SUM(gri.quantity_received),0) FROM goods_receipt_items gri
         JOIN goods_receipts gr ON gr.id = gri.receipt_id WHERE gr.po_id = ?',
        [$poId],
        0
    );
    $newStatus = $received <= 0 ? 'Issued' : ($received >= $ordered ? 'Received' : 'Partially Received');
    if ($po['status'] !== 'Cancelled') {
        db_query('UPDATE purchase_orders SET status = ? WHERE id = ?', [$newStatus, $poId]);
    }
    // Fully received requisition items are marked fulfilled.
    if ($newStatus === 'Received' && $po['requisition_id']) {
        db_query("UPDATE item_requests SET status = 'Fulfilled' WHERE requisition_id = ? AND status = 'Approved'", [(int)$po['requisition_id']]);
    }

    audit('goods_receipt.create', 'purchase_order', $poId, ['receipt_id' => $receiptId, 'assets_created' => $assetsMade]);
    json_ok(['receipt_id' => $receiptId, 'assets_created' => $assetsMade, 'po_status' => $newStatus]);
}

// ---------------------------------------------------------------------------
// Budgets (optional module)
// ---------------------------------------------------------------------------

function budgets(): void
{
    $rows = db_all('SELECT * FROM budgets ORDER BY department, period');
    foreach ($rows as &$b) {
        // Committed from approved requisitions in this department; spent from
        // purchase orders raised against those requisitions.
        $b['committed'] = (float)db_val(
            "SELECT COALESCE(SUM(total_estimate),0) FROM requisitions WHERE department = ? AND status = 'Approved'",
            [$b['department']],
            0
        );
        $b['spent'] = (float)db_val(
            "SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po JOIN requisitions rq ON rq.id = po.requisition_id
             WHERE rq.department = ? AND po.status <> 'Cancelled'",
            [$b['department']],
            0
        );
        $b['remaining'] = (float)$b['amount'] - $b['committed'];
    }
    render('procurement/budgets', [
        'pageTitle' => 'Budgets',
        'breadcrumbs' => ['Procurement' => '/procurement', 'Budgets' => null],
        'budgets' => $rows,
        'canManage' => user_can('budgets.manage'),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function save_budget(): void
{
    if (!user_can('budgets.manage')) {
        json_err('You do not have permission to manage budgets.', 403);
    }
    $dept = in_str('department');
    $period = in_str('period');
    if ($dept === '' || $period === '') {
        json_err('Department and period are required.', 422, [
            'department' => $dept === '' ? 'Required.' : null,
            'period' => $period === '' ? 'Required.' : null,
        ]);
    }
    if (in_str('amount') === '' || !is_numeric(in_str('amount'))) {
        json_err('Enter the budget amount.', 422, ['amount' => 'Enter a number.']);
    }
    db_query(
        'INSERT INTO budgets (department, period, amount, notes) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount), notes = VALUES(notes)',
        [mb_substr($dept, 0, 120), mb_substr($period, 0, 60), (float)in_str('amount'), in_str('notes') ?: null]
    );
    audit('budget.save', 'budget', null, ['department' => $dept, 'period' => $period]);
    json_ok();
}

function delete_budget(string $id): void
{
    if (!user_can('budgets.manage')) {
        json_err('You do not have permission to manage budgets.', 403);
    }
    if (!db_val('SELECT id FROM budgets WHERE id = ?', [(int)$id])) {
        json_err('That budget does not exist.', 404);
    }
    db_query('DELETE FROM budgets WHERE id = ?', [(int)$id]);
    audit('budget.delete', 'budget', (int)$id, []);
    json_ok();
}
