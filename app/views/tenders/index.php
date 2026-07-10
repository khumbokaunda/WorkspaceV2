<?php
// Tenders: pipeline summary, a list with a prominent countdown to the closing
// date, and the create and edit drawer. Missing a deadline loses the bid
// regardless of quality, so the countdown is front and centre while open.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Tenders</h1>
    <div class="d-flex gap-2">
        <a href="/tenders/analytics" class="btn btn-outline-primary"><i class="fa-solid fa-chart-pie me-2"></i>Analytics</a>
<?php if ($canManage): ?>
        <button class="btn btn-primary" onclick="mxNewTender()"><i class="fa-solid fa-plus me-2"></i>New tender</button>
<?php endif; ?>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header">
        <h2>Bid pipeline</h2>
        <div id="mx-tpipe" class="d-flex gap-1 flex-wrap"></div>
    </div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search reference, title, client" aria-label="Search tenders">
        <select id="t-status" class="form-select form-select-sm" style="max-width:190px" aria-label="Filter by status">
            <option value="">All statuses</option>
<?php foreach ($statuses as $s): ?>
            <option><?= e($s) ?></option>
<?php endforeach; ?>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxTenderTable, 'tenders')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-tender-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Reference and title</th><th>Client</th><th>Closing</th><th>Value</th><th>Owner</th><th>Status</th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-tender-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-file-signature"></i>
            <p>No tenders yet.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewTender()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanManageTenders = <?= $canManage ? 'true' : 'false' ?>;
var mxTStatuses = <?= json_encode(array_values($statuses)) ?>;
var mxTSources = <?= json_encode(array_values($sources)) ?>;
var mxTMethods = <?= json_encode(array_values($submissionMethods)) ?>;
var mxTClients = <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name']], $clients)) ?>;
var mxTOwners = <?= json_encode(array_map(fn($o) => ['id' => (int)$o['id'], 'name' => trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: $o['username']], $owners)) ?>;
var mxTCurrency = <?= json_encode($currency) ?>;
var mxTenders = [];

function mxMoney(value, currency) {
    if (value === null || value === '' || value === undefined) return '';
    return (currency || '') + ' ' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function mxTStatusChip(s) {
    var map = {
        'Identified': 'mx-chip-plain', 'Go Decision Pending': 'mx-chip-info', 'Preparing': 'mx-chip-info',
        'Submitted': 'mx-chip-warning', 'Under Evaluation': 'mx-chip-warning',
        'Won': 'mx-chip-success', 'Lost': 'mx-chip-danger', 'Cancelled': 'mx-chip-plain'
    };
    return '<span class="mx-chip ' + (map[s] || 'mx-chip-plain') + '">' + MX.escape(s) + '</span>';
}
// Countdown to the closing date. Open tenders show remaining time; closed ones
// show the date plainly.
function mxCountdown(t) {
    if (!t.closing_date) return '';
    var closed = ['Won', 'Lost', 'Cancelled', 'Under Evaluation', 'Submitted'].indexOf(t.status) !== -1;
    var when = new Date(t.closing_date.replace(' ', 'T'));
    var days = Math.floor((when - new Date()) / 86400000);
    if (closed) return '<span class="mx-tabular text-muted">' + MX.escape(t.closing_date) + '</span>';
    var color = days < 0 ? 'var(--mx-danger)' : days <= 3 ? 'var(--mx-warning)' : 'var(--mx-success)';
    var label = days < 0 ? 'Closed' : (days === 0 ? 'Closes today' : days + ' days left');
    return '<span class="mx-tabular" style="color:' + color + '">' + label + '</span>' +
        '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(t.closing_date) + '</span>';
}

function mxLoadTenders() {
    fetch('/api/tenders', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxTenders = data.tenders || []; mxRenderTenders(); });
}
function mxRenderTenders() {
    var counts = {};
    mxTStatuses.forEach(function (s) { counts[s] = 0; });
    mxTenders.forEach(function (t) { if (counts[t.status] !== undefined) counts[t.status]++; });
    document.getElementById('mx-tpipe').innerHTML = mxTStatuses.map(function (s) {
        return counts[s] ? '<span class="mx-chip mx-chip-plain" style="font-size:11px">' + MX.escape(s) + ' ' + counts[s] + '</span>' : '';
    }).join('');

    var labels = ['Reference and title', 'Client', 'Closing', 'Value', 'Owner', 'Status'];
    var rows = mxTenders.map(function (t) {
        var unmet = t.mandatory_unmet > 0
            ? ' <span class="mx-chip mx-chip-danger" style="font-size:10px" title="Unmet mandatory requirements">' + t.mandatory_unmet + ' unmet</span>'
            : '';
        var title = '<a href="/tenders/' + t.id + '">' + MX.escape(t.title) + '</a>' + unmet +
            (t.reference_number ? '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(t.reference_number) + '</span>' : '');
        return [
            title,
            MX.escape(t.client_name || ''),
            mxCountdown(t),
            '<span class="mx-tabular">' + MX.escape(mxMoney(t.estimated_value, t.currency || mxTCurrency)) + '</span>',
            MX.escape(t.owner_name || ''),
            mxTStatusChip(t.status)
        ];
    });
    if (window.mxTenderTable) { window.mxTenderTable.clear(); window.mxTenderTable.rows.add(rows).draw(); }
    else {
        window.mxTenderTable = MX.table('#mx-tender-table', {
            data: rows, order: [],
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('t-status').addEventListener('change', function () { window.mxTenderTable.column(5).search(this.value).draw(); });
    }
    document.getElementById('mx-tender-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-tender-table').style.display = rows.length ? '' : 'none';
}

function mxTenderForm(t) {
    t = t || {};
    var clientOpts = '<option value="">No client</option>' + mxTClients.map(function (c) {
        return '<option value="' + c.id + '"' + (t.client_id == c.id ? ' selected' : '') + '>' + MX.escape(c.name) + '</option>';
    }).join('');
    var ownerOpts = '<option value="">Unassigned</option>' + mxTOwners.map(function (m) {
        return '<option value="' + m.id + '"' + (t.bid_owner_id == m.id ? ' selected' : '') + '>' + MX.escape(m.name) + '</option>';
    }).join('');
    var sel = function (name, opts, cur, def) {
        return '<select class="form-select" name="' + name + '">' + opts.map(function (o) {
            return '<option' + ((cur || def) === o ? ' selected' : '') + '>' + MX.escape(o) + '</option>';
        }).join('') + '</select>';
    };
    var dtLocal = function (v) { return v ? String(v).replace(' ', 'T').slice(0, 16) : ''; };
    return '<form id="tender-form">' +
        '<div class="row g-2 mb-3"><div class="col-8"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="250" value="' + MX.escape(t.title || '') + '"></div>' +
        '<div class="col-4"><label class="form-label">Reference</label><input class="form-control mx-mono" name="reference_number" maxlength="120" value="' + MX.escape(t.reference_number || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Client</label><select class="form-select" name="client_id">' + clientOpts + '</select></div>' +
        '<div class="col"><label class="form-label">Category</label><input class="form-control" name="category" maxlength="120" value="' + MX.escape(t.category || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Source</label>' + sel('source', mxTSources, t.source, 'Portal') + '</div>' +
        '<div class="col"><label class="form-label">Submission</label>' + sel('submission_method', mxTMethods, t.submission_method, 'Portal') + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Issue date</label><input type="date" class="form-control" name="issue_date" value="' + (t.issue_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Closing date and time</label><input type="datetime-local" class="form-control" name="closing_date" value="' + dtLocal(t.closing_date) + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Validity (days)</label><input type="number" min="0" class="form-control" name="tender_validity_days" value="' + (t.tender_validity_days != null ? t.tender_validity_days : '') + '"></div>' +
        '<div class="col"><label class="form-label">Clarification deadline</label><input type="datetime-local" class="form-control" name="clarification_deadline" value="' + dtLocal(t.clarification_deadline) + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Site visit</label><input type="datetime-local" class="form-control" name="site_visit_at" value="' + dtLocal(t.site_visit_at) + '"></div>' +
        '<div class="col"><label class="form-label">Status</label>' + sel('status', mxTStatuses, t.status, 'Identified') + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col-5"><label class="form-label">Estimated value</label><input type="number" step="0.01" min="0" class="form-control" name="estimated_value" value="' + (t.estimated_value != null ? t.estimated_value : '') + '"></div>' +
        '<div class="col-3"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" maxlength="3" placeholder="' + MX.escape(mxTCurrency) + '" value="' + MX.escape(t.currency || '') + '"></div>' +
        '<div class="col-4"><label class="form-label">VAT percent</label><input type="number" step="0.01" min="0" class="form-control" name="vat_percent" value="' + (t.vat_percent != null ? t.vat_percent : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Bid owner</label><select class="form-select" name="bid_owner_id">' + ownerOpts + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="5000">' + MX.escape(t.description || '') + '</textarea></div>' +
        '</form>';
}
function mxNewTender() {
    MX.drawer.open({
        title: 'New tender', body: mxTenderForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitTender(null)">Create</button>'
    });
}
function mxSubmitTender(id) {
    var form = document.getElementById('tender-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/tenders/' + id, MX.formData(form)) : MX.api('POST', '/tenders', MX.formData(form));
    call.then(function (data) {
        MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Created.');
        if (!id && data.tender_id) { window.location.href = '/tenders/' + data.tender_id; }
        else { mxLoadTenders(); }
    }).catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

document.addEventListener('DOMContentLoaded', mxLoadTenders);
</script>
