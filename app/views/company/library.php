<?php
// Compliance Library: the company profile at a glance, the official documents
// with expiry status, and the past-performance references. A tender is built
// by selecting from here rather than hunting for files.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Compliance Library</h1>
<?php if (user_can('admin.settings')): ?>
    <a href="/admin/company-profile" class="btn btn-outline-primary"><i class="fa-solid fa-building me-2"></i>Edit company profile</a>
<?php endif; ?>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header"><h2><?= e($profile['trading_name'] ?? ($profile['legal_name'] ?? 'Company profile')) ?></h2></div>
    <div class="mx-card-body">
        <dl class="row mb-0" style="font-size:13px">
            <dt class="col-sm-3 text-muted fw-normal">Legal name</dt><dd class="col-sm-9"><?= e($profile['legal_name'] ?? 'Not set') ?></dd>
            <dt class="col-sm-3 text-muted fw-normal">Registration number</dt><dd class="col-sm-9"><?= e($profile['reg_number'] ?? 'Not set') ?></dd>
            <dt class="col-sm-3 text-muted fw-normal">Tax identification</dt><dd class="col-sm-9"><?= e($profile['tax_id'] ?? 'Not set') ?></dd>
            <dt class="col-sm-3 text-muted fw-normal">Contact</dt><dd class="col-sm-9 mb-0"><?= e($profile['email'] ?? '') ?><?= !empty($profile['phone']) ? ' &middot; ' . e($profile['phone']) : '' ?></dd>
        </dl>
    </div>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header">
        <h2>Compliance documents</h2>
<?php if ($canManage): ?>
        <button class="btn btn-primary btn-sm" onclick="mxNewDoc()"><i class="fa-solid fa-upload me-1"></i>Add document</button>
<?php endif; ?>
    </div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search title, authority, reference" aria-label="Search documents">
        <select id="cd-status" class="form-select form-select-sm" style="max-width:180px" aria-label="Filter by expiry status">
            <option value="">All statuses</option>
            <option>Valid</option><option>Expiring</option><option>Expired</option>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxDocTable, 'company-documents')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-doc-table" class="table dt-host mx-stack align-middle" style="width:100%">
            <thead><tr><th>Type</th><th>Title</th><th>Reference</th><th>Issued</th><th>Expires</th><th>Status</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-doc-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-folder-open"></i>
            <p>No compliance documents yet.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewDoc()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header">
        <h2>Past performance references</h2>
<?php if ($canManage): ?>
        <button class="btn btn-outline-primary btn-sm" onclick="mxNewRef()"><i class="fa-solid fa-plus me-1"></i>Add reference</button>
<?php endif; ?>
    </div>
    <div class="mx-card-body mx-flush" id="mx-ref-list">
        <div class="mx-empty py-4"><i class="fa-solid fa-award"></i><p class="mb-0">Loading references...</p></div>
    </div>
</div>

<script>
var mxCanManageLib = <?= $canManage ? 'true' : 'false' ?>;
var mxDocTypes = <?= json_encode(array_values($docTypes)) ?>;
var mxLibCurrency = <?= json_encode($currency) ?>;
var mxDocs = [];
var mxRefs = [];

function mxExpiryChip(state) {
    var map = { Valid: 'mx-chip-success', Expiring: 'mx-chip-warning', Expired: 'mx-chip-danger', None: 'mx-chip-plain' };
    var label = { Valid: 'Valid', Expiring: 'Expiring', Expired: 'Expired', None: 'No expiry' };
    return '<span class="mx-chip ' + (map[state] || 'mx-chip-plain') + '">' + (label[state] || state) + '</span>';
}

// ---- documents ----
function mxLoadDocs() {
    fetch('/api/company/documents', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxDocs = data.documents || []; mxRenderDocs(); });
}

function mxRenderDocs() {
    var labels = ['Type', 'Title', 'Reference', 'Issued', 'Expires', 'Status', ''];
    var rows = mxDocs.map(function (d) {
        var expiryHtml = '';
        if (d.expiry_date) {
            var color = d.days_left < 0 ? 'var(--mx-danger)' : d.days_left <= 60 ? 'var(--mx-warning)' : 'inherit';
            expiryHtml = '<span class="mx-tabular" style="color:' + color + '">' + d.expiry_date +
                (d.days_left >= 0 ? ' (' + d.days_left + ' d)' : '') + '</span>';
        }
        var actions = '<button class="btn btn-subtle btn-sm" onclick="mxDownloadDoc(' + d.id + ')" aria-label="Download"><i class="fa-solid fa-download"></i></button>';
        if (mxCanManageLib) {
            actions += '<button class="btn btn-subtle btn-sm" onclick="mxEditDoc(' + d.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteDoc(' + d.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>';
        }
        return [
            MX.escape(d.doc_type),
            MX.escape(d.title) + '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(d.original_name) + '</span>',
            MX.escape(d.reference_number || '') + (d.issuing_authority ? '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(d.issuing_authority) + '</span>' : ''),
            '<span class="mx-tabular">' + MX.escape(d.issue_date || '') + '</span>',
            expiryHtml,
            mxExpiryChip(d.expiry_state),
            actions
        ];
    });
    if (window.mxDocTable) { window.mxDocTable.clear(); window.mxDocTable.rows.add(rows).draw(); }
    else {
        window.mxDocTable = MX.table('#mx-doc-table', {
            data: rows,
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('cd-status').addEventListener('change', function () { window.mxDocTable.column(5).search(this.value).draw(); });
    }
    document.getElementById('mx-doc-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-doc-table').style.display = rows.length ? '' : 'none';
}

function mxDocForm(d) {
    d = d || {};
    return '<form id="doc-form">' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="200" value="' + MX.escape(d.title || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="doc_type">' +
        mxDocTypes.map(function (t) { return '<option' + ((d.doc_type || 'Other') === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Issued</label><input type="date" class="form-control" name="issue_date" value="' + (d.issue_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Expires</label><input type="date" class="form-control" name="expiry_date" value="' + (d.expiry_date || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Issuing authority</label><input class="form-control" name="issuing_authority" maxlength="200" value="' + MX.escape(d.issuing_authority || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Reference number</label><input class="form-control" name="reference_number" maxlength="120" value="' + MX.escape(d.reference_number || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(d.notes || '') + '"></div>' +
        (d.id ? '' : '<div class="mb-3"><label class="form-label">File</label><input type="file" class="form-control" name="file" required accept=".pdf,.doc,.docx,.odt,.txt,.png,.jpg,.jpeg"></div>') +
        '</form>';
}

function mxNewDoc() {
    MX.drawer.open({
        title: 'Add compliance document',
        body: mxDocForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitDoc(null)">Upload</button>'
    });
}
function mxEditDoc(id) {
    var d = mxDocs.find(function (x) { return x.id == id; });
    if (!d) return;
    MX.drawer.open({
        title: 'Edit document',
        body: mxDocForm(d),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitDoc(' + id + ')">Save changes</button>'
    });
}
function mxSubmitDoc(id) {
    var form = document.getElementById('doc-form');
    if (!form.reportValidity()) return;
    if (id) {
        MX.api('PATCH', '/company/documents/' + id, MX.formData(form))
            .then(function () { MX.drawer.close(); MX.ok('Saved.'); mxLoadDocs(); })
            .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
        return;
    }
    var fd = new FormData();
    ['title', 'doc_type', 'issue_date', 'expiry_date', 'issuing_authority', 'reference_number', 'notes'].forEach(function (k) {
        if (form[k]) fd.append(k, MX.clean(form[k].value));
    });
    fd.append('file', form.file.files[0]);
    fetch('/company/documents', {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd, redirect: 'manual'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok === false) { MX.fail(data.error); MX.showFieldErrors(form, data.fields); return; }
        MX.drawer.close(); MX.ok('Uploaded.'); mxLoadDocs();
    }).catch(function () { MX.fail(); });
}
function mxDownloadDoc(id) {
    MX.api('POST', '/company/documents/' + id + '/link', {})
        .then(function (data) { window.location.href = data.url; })
        .catch(function (e) { MX.fail(e.message); });
}
function mxDeleteDoc(id) {
    MX.confirm('Delete this document?', 'The file is removed permanently.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/company/documents/' + id)
            .then(function () { MX.ok('Deleted.'); mxLoadDocs(); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

// ---- references ----
function mxMoney(value, currency) {
    if (value === null || value === '' || value === undefined) return '';
    return (currency || '') + ' ' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function mxLoadRefs() {
    fetch('/api/company/references', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxRefs = data.references || []; mxRenderRefs(); });
}
function mxRenderRefs() {
    var box = document.getElementById('mx-ref-list');
    if (!mxRefs.length) {
        box.innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-award"></i><p class="mb-0">No references yet. These map to the experience criteria scored in tenders.</p></div>';
        return;
    }
    box.innerHTML = '<ul class="list-unstyled m-0">' + mxRefs.map(function (r) {
        var period = [r.start_date, r.end_date].filter(Boolean).join(' to ') || 'No dates';
        var actions = '';
        if (r.has_certificate) {
            actions += '<button class="btn btn-subtle btn-sm" onclick="mxDownloadRefCert(' + r.id + ')" aria-label="Download certificate"><i class="fa-solid fa-download"></i></button>';
        }
        if (mxCanManageLib) {
            actions += '<button class="btn btn-subtle btn-sm" onclick="mxEditRef(' + r.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteRef(' + r.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>';
        }
        return '<li class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">' +
            '<i class="fa-solid fa-award text-muted mt-1"></i>' +
            '<div class="flex-grow-1">' +
            '<div style="font-size:14px"><strong>' + MX.escape(r.project_title) + '</strong></div>' +
            '<div class="text-muted" style="font-size:12px">' + MX.escape(r.client_name) + ' &middot; ' + MX.escape(period) +
            (r.contract_value ? ' &middot; ' + MX.escape(mxMoney(r.contract_value, r.currency || mxLibCurrency)) : '') + '</div>' +
            (r.scope_summary ? '<div style="font-size:12px" class="mt-1">' + MX.escape(r.scope_summary) + '</div>' : '') +
            '</div><div class="d-flex">' + actions + '</div></li>';
    }).join('') + '</ul>';
}
function mxRefForm(r) {
    r = r || {};
    return '<form id="ref-form">' +
        '<div class="mb-3"><label class="form-label">Client name</label><input class="form-control" name="client_name" required maxlength="200" value="' + MX.escape(r.client_name || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Project title</label><input class="form-control" name="project_title" required maxlength="250" value="' + MX.escape(r.project_title || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col-7"><label class="form-label">Contract value</label><input type="number" step="0.01" min="0" class="form-control" name="contract_value" value="' + (r.contract_value != null ? r.contract_value : '') + '"></div>' +
        '<div class="col-5"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" maxlength="3" placeholder="' + MX.escape(mxLibCurrency) + '" value="' + MX.escape(r.currency || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Start</label><input type="date" class="form-control" name="start_date" value="' + (r.start_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">End</label><input type="date" class="form-control" name="end_date" value="' + (r.end_date || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Scope summary</label><textarea class="form-control" name="scope_summary" rows="2" maxlength="2000">' + MX.escape(r.scope_summary || '') + '</textarea></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Reference contact</label><input class="form-control" name="ref_contact_name" maxlength="160" value="' + MX.escape(r.ref_contact_name || '') + '"></div>' +
        '<div class="col"><label class="form-label">Contact detail</label><input class="form-control" name="ref_contact_detail" maxlength="200" value="' + MX.escape(r.ref_contact_detail || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Completion certificate' + (r.has_certificate ? ' (replace)' : '') + '</label><input type="file" class="form-control" name="certificate" accept=".pdf,.png,.jpg,.jpeg"></div>' +
        '</form>';
}
function mxNewRef() {
    MX.drawer.open({
        title: 'Add reference',
        body: mxRefForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitRef(null)">Add</button>'
    });
}
function mxEditRef(id) {
    var r = mxRefs.find(function (x) { return x.id == id; });
    if (!r) return;
    MX.drawer.open({
        title: 'Edit reference',
        body: mxRefForm(r),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitRef(' + id + ')">Save changes</button>'
    });
}
function mxSubmitRef(id) {
    var form = document.getElementById('ref-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    ['client_name', 'project_title', 'contract_value', 'currency', 'start_date', 'end_date', 'scope_summary', 'ref_contact_name', 'ref_contact_detail'].forEach(function (k) {
        fd.append(k, MX.clean(form[k].value));
    });
    if (form.certificate.files[0]) fd.append('certificate', form.certificate.files[0]);
    var url = '/company/references' + (id ? '/' + id : '');
    if (id) fd.append('_method', 'PATCH');
    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd, redirect: 'manual'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok === false) { MX.fail(data.error); MX.showFieldErrors(form, data.fields); return; }
        MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Added.'); mxLoadRefs();
    }).catch(function () { MX.fail(); });
}
function mxDownloadRefCert(id) {
    MX.api('POST', '/company/references/' + id + '/cert-link', {})
        .then(function (data) { window.location.href = data.url; })
        .catch(function (e) { MX.fail(e.message); });
}
function mxDeleteRef(id) {
    MX.confirm('Delete this reference?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/company/references/' + id)
            .then(function () { MX.ok('Deleted.'); mxLoadRefs(); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', function () { mxLoadDocs(); mxLoadRefs(); });
</script>
