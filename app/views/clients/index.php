<?php
// Clients directory and the opportunity pipeline. Clients are the procuring
// entities a tender is raised against; opportunities are the light pipeline
// that may become a formal tender.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Clients</h1>
<?php if ($canManage): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="mxNewOpp()"><i class="fa-solid fa-bullseye me-2"></i>Add opportunity</button>
        <button class="btn btn-primary" onclick="mxNewClient()"><i class="fa-solid fa-plus me-2"></i>Add client</button>
    </div>
<?php endif; ?>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>Client register</h2></div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search name, sector, contact" aria-label="Search clients">
        <select id="cl-type" class="form-select form-select-sm" style="max-width:180px" aria-label="Filter by type">
            <option value="">All types</option>
<?php foreach ($clientTypes as $t): ?>
            <option><?= e($t) ?></option>
<?php endforeach; ?>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxClientTable, 'clients')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-client-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Name</th><th>Type</th><th>Sector</th><th>Main contact</th><th>Opportunities</th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-client-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-handshake"></i>
            <p>No clients yet.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewClient()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header">
        <h2>Opportunity pipeline</h2>
        <div id="mx-pipe-summary" class="d-flex gap-1 flex-wrap"></div>
    </div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search title, client" aria-label="Search opportunities">
        <select id="op-stage" class="form-select form-select-sm" style="max-width:170px" aria-label="Filter by stage">
            <option value="">All stages</option>
<?php foreach ($stages as $s): ?>
            <option><?= e($s) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-opp-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Title</th><th>Client</th><th>Value</th><th>Stage</th><th>Decision</th><th>Owner</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-opp-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-bullseye"></i>
            <p>No opportunities yet.</p>
        </div>
    </div>
</div>

<script>
var mxCanManageClients = <?= $canManage ? 'true' : 'false' ?>;
var mxClientTypes = <?= json_encode(array_values($clientTypes)) ?>;
var mxStages = <?= json_encode(array_values($stages)) ?>;
var mxOwners = <?= json_encode(array_map(fn($o) => ['id' => (int)$o['id'], 'name' => trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: $o['username']], $owners)) ?>;
var mxClientsCurrency = <?= json_encode($currency) ?>;
var mxClients = [];
var mxOpps = [];

function mxMoney(value, currency) {
    if (value === null || value === '' || value === undefined) return '';
    return (currency || '') + ' ' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function mxStageChip(s) {
    var map = { Lead: 'mx-chip-plain', Qualifying: 'mx-chip-info', Bidding: 'mx-chip-warning', Won: 'mx-chip-success', Lost: 'mx-chip-danger' };
    return '<span class="mx-chip ' + (map[s] || 'mx-chip-plain') + '">' + MX.escape(s) + '</span>';
}

// ---- clients ----
function mxLoadClients() {
    fetch('/api/clients', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxClients = data.clients || []; mxRenderClients(); });
}
function mxRenderClients() {
    var labels = ['Name', 'Type', 'Sector', 'Main contact', 'Opportunities'];
    var rows = mxClients.map(function (c) {
        return [
            '<a href="/clients/' + c.id + '">' + MX.escape(c.name) + '</a>',
            MX.escape(c.client_type),
            MX.escape(c.sector || ''),
            MX.escape(c.main_contact || ''),
            '<span class="mx-tabular">' + (c.opportunity_count || 0) + '</span>'
        ];
    });
    if (window.mxClientTable) { window.mxClientTable.clear(); window.mxClientTable.rows.add(rows).draw(); }
    else {
        window.mxClientTable = MX.table('#mx-client-table', {
            data: rows,
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('cl-type').addEventListener('change', function () { window.mxClientTable.column(1).search(this.value).draw(); });
    }
    document.getElementById('mx-client-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-client-table').style.display = rows.length ? '' : 'none';
}
function mxClientForm(c) {
    c = c || {};
    return '<form id="client-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="200" value="' + MX.escape(c.name || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Type</label><select class="form-select" name="client_type">' +
        mxClientTypes.map(function (t) { return '<option' + ((c.client_type || 'Private') === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select></div><div class="col"><label class="form-label">Sector</label><input class="form-control" name="sector" maxlength="120" value="' + MX.escape(c.sector || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Main contact</label><input class="form-control" name="main_contact" maxlength="160" value="' + MX.escape(c.main_contact || '') + '"></div>' +
        '<div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="60" value="' + MX.escape(c.phone || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" maxlength="190" value="' + MX.escape(c.email || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2" maxlength="400">' + MX.escape(c.address || '') + '</textarea></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="2000">' + MX.escape(c.notes || '') + '</textarea></div>' +
        '</form>';
}
function mxNewClient() {
    MX.drawer.open({
        title: 'Add client', body: mxClientForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitClient(null)">Add</button>'
    });
}
function mxSubmitClient(id) {
    var form = document.getElementById('client-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/clients/' + id, MX.formData(form)) : MX.api('POST', '/clients', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Added.'); mxLoadClients(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

// ---- opportunities ----
function mxLoadOpps() {
    fetch('/api/opportunities', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxOpps = data.opportunities || []; mxRenderOpps(); });
}
function mxRenderOpps() {
    var counts = {};
    mxStages.forEach(function (s) { counts[s] = 0; });
    mxOpps.forEach(function (o) { if (counts[o.stage] !== undefined) counts[o.stage]++; });
    document.getElementById('mx-pipe-summary').innerHTML = mxStages.map(function (s) {
        return '<span class="mx-chip mx-chip-plain" style="font-size:11px">' + MX.escape(s) + ' ' + counts[s] + '</span>';
    }).join('');

    var labels = ['Title', 'Client', 'Value', 'Stage', 'Decision', 'Owner', ''];
    var rows = mxOpps.map(function (o) {
        var actions = mxCanManageClients
            ? '<button class="btn btn-subtle btn-sm" onclick="mxEditOpp(' + o.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
              '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteOpp(' + o.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>'
            : '';
        return [
            MX.escape(o.title),
            o.client_id ? '<a href="/clients/' + o.client_id + '">' + MX.escape(o.client_name || '') + '</a>' : '<span class="text-muted">None</span>',
            '<span class="mx-tabular">' + MX.escape(mxMoney(o.estimated_value, o.currency || mxClientsCurrency)) + '</span>',
            mxStageChip(o.stage),
            '<span class="mx-tabular">' + MX.escape(o.expected_decision_date || '') + '</span>',
            MX.escape(o.owner_name || ''),
            actions
        ];
    });
    if (window.mxOppTable) { window.mxOppTable.clear(); window.mxOppTable.rows.add(rows).draw(); }
    else {
        window.mxOppTable = MX.table('#mx-opp-table', {
            data: rows, order: [],
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('op-stage').addEventListener('change', function () { window.mxOppTable.column(3).search(this.value).draw(); });
    }
    document.getElementById('mx-opp-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-opp-table').style.display = rows.length ? '' : 'none';
}
function mxOppForm(o) {
    o = o || {};
    var clientOpts = '<option value="">No client</option>' + mxClients.map(function (c) {
        return '<option value="' + c.id + '"' + (o.client_id == c.id ? ' selected' : '') + '>' + MX.escape(c.name) + '</option>';
    }).join('');
    var ownerOpts = '<option value="">Unassigned</option>' + mxOwners.map(function (m) {
        return '<option value="' + m.id + '"' + (o.owner_id == m.id ? ' selected' : '') + '>' + MX.escape(m.name) + '</option>';
    }).join('');
    return '<form id="opp-form">' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="250" value="' + MX.escape(o.title || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Client</label><select class="form-select" name="client_id">' + clientOpts + '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col-7"><label class="form-label">Estimated value</label><input type="number" step="0.01" min="0" class="form-control" name="estimated_value" value="' + (o.estimated_value != null ? o.estimated_value : '') + '"></div>' +
        '<div class="col-5"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" maxlength="3" placeholder="' + MX.escape(mxClientsCurrency) + '" value="' + MX.escape(o.currency || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Stage</label><select class="form-select" name="stage">' +
        mxStages.map(function (s) { return '<option' + ((o.stage || 'Lead') === s ? ' selected' : '') + '>' + MX.escape(s) + '</option>'; }).join('') +
        '</select></div><div class="col"><label class="form-label">Expected decision</label><input type="date" class="form-control" name="expected_decision_date" value="' + (o.expected_decision_date || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Source</label><input class="form-control" name="source" maxlength="120" value="' + MX.escape(o.source || '') + '"></div>' +
        '<div class="col"><label class="form-label">Owner</label><select class="form-select" name="owner_id">' + ownerOpts + '</select></div></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="2000">' + MX.escape(o.notes || '') + '</textarea></div>' +
        '</form>';
}
function mxNewOpp() {
    MX.drawer.open({
        title: 'Add opportunity', body: mxOppForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitOpp(null)">Add</button>'
    });
}
function mxEditOpp(id) {
    var o = mxOpps.find(function (x) { return x.id == id; });
    if (!o) return;
    MX.drawer.open({
        title: 'Edit opportunity', body: mxOppForm(o),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitOpp(' + id + ')">Save changes</button>'
    });
}
function mxSubmitOpp(id) {
    var form = document.getElementById('opp-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/opportunities/' + id, MX.formData(form)) : MX.api('POST', '/opportunities', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Added.'); mxLoadOpps(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteOpp(id) {
    MX.confirm('Delete this opportunity?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/opportunities/' + id)
            .then(function () { MX.ok('Deleted.'); mxLoadOpps(); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    mxLoadClients();
    mxLoadOpps();
});
</script>
