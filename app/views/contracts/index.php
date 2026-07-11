<?php
// Contracts and renewals: a list with expiry status chips and the signed file.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Contracts</h1>
<?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="mxNewContract()"><i class="fa-solid fa-plus me-2"></i>Add contract</button>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Agreements</h2></div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search title, counterparty, reference" aria-label="Search contracts">
        <select id="ct-status" class="form-select form-select-sm" style="max-width:170px" aria-label="Filter by status">
            <option value="">All statuses</option>
<?php foreach ($statuses as $s): ?>
            <option><?= e($s) ?></option>
<?php endforeach; ?>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxContractTable, 'contracts')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-contract-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Title</th><th>Counterparty</th><th>Type</th><th>Ends</th><th>Value</th><th>Status</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-contract-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-file-contract"></i>
            <p>No contracts yet.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewContract()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanManageContracts = <?= $canManage ? 'true' : 'false' ?>;
var mxContractTypes = <?= json_encode(array_values($types)) ?>;
var mxContractStatuses = <?= json_encode(array_values($statuses)) ?>;
var mxContractCurrency = <?= json_encode($currency) ?>;
var mxContracts = [];

function mxMoney(v, c) { if (v === null || v === '' || v === undefined) return ''; return (c || '') + ' ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function mxContractChip(state) {
    var map = { Valid: 'mx-chip-success', Expiring: 'mx-chip-warning', Expired: 'mx-chip-danger', None: 'mx-chip-plain' };
    var label = { Valid: 'Active', Expiring: 'Renew soon', Expired: 'Expired', None: 'No end' };
    return '<span class="mx-chip ' + (map[state] || 'mx-chip-plain') + '">' + (label[state] || state) + '</span>';
}

function mxLoadContracts() {
    fetch('/api/contracts', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxContracts = data.contracts || []; mxRenderContracts(); });
}
function mxRenderContracts() {
    var labels = ['Title', 'Counterparty', 'Type', 'Ends', 'Value', 'Status', ''];
    var rows = mxContracts.map(function (c) {
        var endHtml = '';
        if (c.end_date) {
            var color = c.days_left < 0 ? 'var(--mx-danger)' : c.days_left <= 30 ? 'var(--mx-warning)' : 'inherit';
            endHtml = '<span class="mx-tabular" style="color:' + color + '">' + c.end_date + (c.days_left >= 0 ? ' (' + c.days_left + ' d)' : '') + '</span>';
        }
        var actions = '';
        if (c.has_file) actions += '<button class="btn btn-subtle btn-sm" onclick="mxDownloadContract(' + c.id + ')" aria-label="Download"><i class="fa-solid fa-download"></i></button>';
        if (mxCanManageContracts) actions += '<button class="btn btn-subtle btn-sm" onclick="mxEditContract(' + c.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
            '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteContract(' + c.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>';
        return [
            MX.escape(c.title) + (c.reference ? '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(c.reference) + '</span>' : ''),
            MX.escape(c.counterparty || ''),
            MX.escape(c.contract_type),
            endHtml,
            '<span class="mx-tabular">' + MX.escape(mxMoney(c.value, c.currency || mxContractCurrency)) + '</span>',
            mxContractChip(c.expiry_state) + ' <span class="text-muted" style="font-size:11px">' + MX.escape(c.status) + '</span>',
            actions
        ];
    });
    if (window.mxContractTable) { window.mxContractTable.clear(); window.mxContractTable.rows.add(rows).draw(); }
    else {
        window.mxContractTable = MX.table('#mx-contract-table', {
            data: rows,
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('ct-status').addEventListener('change', function () { window.mxContractTable.column(5).search(this.value).draw(); });
    }
    document.getElementById('mx-contract-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-contract-table').style.display = rows.length ? '' : 'none';
}
function mxContractForm(c) {
    c = c || {};
    var sel = function (name, opts, cur, def) {
        return '<select class="form-select" name="' + name + '">' + opts.map(function (o) { return '<option' + ((cur || def) === o ? ' selected' : '') + '>' + MX.escape(o) + '</option>'; }).join('') + '</select>';
    };
    return '<form id="contract-form">' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="200" value="' + MX.escape(c.title || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Counterparty</label><input class="form-control" name="counterparty" maxlength="200" value="' + MX.escape(c.counterparty || '') + '"></div>' +
        '<div class="col"><label class="form-label">Type</label>' + sel('contract_type', mxContractTypes, c.contract_type, 'Client') + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Reference</label><input class="form-control mx-mono" name="reference" maxlength="120" value="' + MX.escape(c.reference || '') + '"></div>' +
        '<div class="col"><label class="form-label">Status</label>' + sel('status', mxContractStatuses, c.status, 'Active') + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date" value="' + (c.start_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">End date</label><input type="date" class="form-control" name="end_date" value="' + (c.end_date || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col-5"><label class="form-label">Value</label><input type="number" step="0.01" min="0" class="form-control" name="value" value="' + (c.value != null ? c.value : '') + '"></div>' +
        '<div class="col-3"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" maxlength="3" value="' + MX.escape(c.currency || '') + '"></div>' +
        '<div class="col-4"><label class="form-label">Renewal alert (days)</label><input type="number" min="0" class="form-control" name="renewal_reminder_days" value="' + (c.renewal_reminder_days != null ? c.renewal_reminder_days : 60) + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Signed contract file' + (c.has_file ? ' (replace)' : '') + '</label><input type="file" class="form-control" id="contract-file" accept=".pdf,.png,.jpg,.jpeg"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="1000">' + MX.escape(c.notes || '') + '</textarea></div>' +
        '</form>';
}
function mxNewContract() {
    MX.drawer.open({ title: 'Add contract', body: mxContractForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitContract(null)">Add</button>' });
}
function mxEditContract(id) {
    var c = mxContracts.find(function (x) { return x.id == id; });
    if (!c) return;
    MX.drawer.open({ title: 'Edit contract', body: mxContractForm(c),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitContract(' + id + ')">Save</button>' });
}
function mxSubmitContract(id) {
    var form = document.getElementById('contract-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    ['title', 'counterparty', 'contract_type', 'reference', 'status', 'start_date', 'end_date', 'value', 'currency', 'renewal_reminder_days', 'notes'].forEach(function (k) {
        fd.append(k, MX.clean(form[k].value));
    });
    var file = document.getElementById('contract-file');
    if (file.files[0]) fd.append('file', file.files[0]);
    var url = '/contracts' + (id ? '/' + id : '');
    if (id) fd.append('_method', 'PATCH');
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: fd, redirect: 'manual' })
        .then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok === false) { MX.fail(data.error); MX.showFieldErrors(form, data.fields); return; }
            MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Added.'); mxLoadContracts();
        }).catch(function () { MX.fail(); });
}
function mxDownloadContract(id) {
    MX.api('POST', '/contracts/' + id + '/file-link', {}).then(function (d) { window.location.href = d.url; }).catch(function (e) { MX.fail(e.message); });
}
function mxDeleteContract(id) {
    MX.confirm('Delete this contract?', 'The signed file is removed too.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/contracts/' + id).then(function () { MX.ok('Deleted.'); mxLoadContracts(); }).catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxLoadContracts);
</script>
