<?php // Certifications: filterable list, in-progress view, entry point to the matrix. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Certifications and Skills</h1>
    <div class="d-flex gap-2">
        <a href="/certifications/matrix" class="btn btn-outline-primary"><i class="fa-solid fa-table-cells me-2"></i>Skills matrix</a>
<?php if ($canManageOwn || $canManageAll): ?>
        <button class="btn btn-primary" onclick="mxNewCert()"><i class="fa-solid fa-plus me-2"></i>Add certification</button>
<?php endif; ?>
    </div>
</div>

<div class="mx-card">
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search name, code, person" aria-label="Search certifications">
        <select id="cf-status" class="form-select form-select-sm" style="max-width:170px" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option>Active</option><option>In Progress</option><option>Expired</option>
        </select>
        <div class="form-check ms-2 mb-0">
            <input class="form-check-input" type="checkbox" id="cf-mine">
            <label class="form-check-label" for="cf-mine" style="font-size:13px">Mine only</label>
        </div>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxCertTable, 'certifications')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-cert-table" class="table dt-host mx-stack align-middle" style="width:100%">
            <thead><tr><th>Code</th><th>Name</th><th>Person</th><th>Issuer</th><th>Expires</th><th>Status</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-cert-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-certificate"></i>
            <p>No certifications recorded yet.</p>
<?php if ($canManageOwn || $canManageAll): ?>
            <button class="btn btn-primary" onclick="mxNewCert()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanManageAllCerts = <?= $canManageAll ? 'true' : 'false' ?>;
var mxCanManageOwnCerts = <?= $canManageOwn ? 'true' : 'false' ?>;
var mxCertPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;
var mxMyPid = <?= (int)$myPersonId ?>;
var mxCerts = [];

function mxCertChip(s) {
    var cls = { Active: 'mx-chip-success', 'In Progress': 'mx-chip-warning', Expired: 'mx-chip-danger' }[s];
    return '<span class="mx-chip ' + cls + '">' + s + '</span>';
}

function mxLoadCerts() {
    fetch('/api/certifications', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            mxCerts = data.certifications;
            mxRenderCerts();
            var openId = new URLSearchParams(location.search).get('cert');
            if (openId) {
                var c = mxCerts.find(function (x) { return x.id == openId; });
                if (c) mxEditCert(c.id, true);
                history.replaceState(null, '', location.pathname);
            }
        });
}

function mxRenderCerts() {
    var mine = document.getElementById('cf-mine').checked;
    var labels = ['Code', 'Name', 'Person', 'Issuer', 'Expires', 'Status', ''];
    var rows = mxCerts
        .filter(function (c) { return !mine || c.person_id == mxMyPid; })
        .map(function (c) {
            var canTouch = mxCanManageAllCerts || (mxCanManageOwnCerts && c.person_id == mxMyPid);
            var canFile = c.has_certificate && (mxCanManageAllCerts || c.person_id == mxMyPid);
            var expiryHtml = '';
            if (c.expires_on) {
                var color = c.days_left < 0 ? 'var(--mx-danger)' : c.days_left <= 30 ? 'var(--mx-warning)' : 'inherit';
                expiryHtml = '<span class="mx-tabular" style="color:' + color + '">' + c.expires_on +
                    (c.days_left >= 0 ? ' (' + c.days_left + ' d)' : '') + '</span>';
            }
            return [
                '<code>' + MX.escape(c.code) + '</code>',
                MX.escape(c.name) + (c.verify_url ? ' <a href="' + MX.escape(c.verify_url) + '" target="_blank" rel="noopener noreferrer" aria-label="Verify"><i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px"></i></a>' : ''),
                MX.escape(c.first_name + ' ' + c.last_name),
                MX.escape(c.issuing_body || ''),
                expiryHtml,
                mxCertChip(c.effective_status),
                (canFile ? '<button class="btn btn-subtle btn-sm" onclick="mxDownloadCert(' + c.id + ')" aria-label="Download certificate"><i class="fa-solid fa-download"></i></button>' : '') +
                (canTouch
                    ? '<button class="btn btn-subtle btn-sm" onclick="mxEditCert(' + c.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
                      '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteCert(' + c.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>'
                    : '')
            ];
        });
    if (window.mxCertTable) { window.mxCertTable.clear(); window.mxCertTable.rows.add(rows).draw(); }
    else {
        window.mxCertTable = MX.table('#mx-cert-table', {
            data: rows,
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('cf-status').addEventListener('change', function () { window.mxCertTable.column(5).search(this.value).draw(); });
        document.getElementById('cf-mine').addEventListener('change', mxRenderCerts);
    }
    document.getElementById('mx-cert-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-cert-table').style.display = rows.length ? '' : 'none';
}

function mxCertForm(c) {
    c = c || {};
    var personField = '';
    if (mxCanManageAllCerts && mxCertPeople.length) {
        personField = '<div class="mb-3"><label class="form-label">Person</label><select class="form-select" name="person_id">' +
            mxCertPeople.map(function (p) { return '<option value="' + p.id + '"' + ((c.person_id || mxMyPid) == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') +
            '</select></div>';
    }
    return '<form id="cert-form">' + personField +
        '<div class="row g-2 mb-3"><div class="col-4"><label class="form-label">Code</label><input class="form-control mx-mono" name="code" required maxlength="60" placeholder="CCNA" value="' + MX.escape(c.code || '') + '"></div>' +
        '<div class="col-8"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160" value="' + MX.escape(c.name || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Issuing body</label><input class="form-control" name="issuing_body" maxlength="160" value="' + MX.escape(c.issuing_body || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Earned</label><input type="date" class="form-control" name="earned_on" value="' + (c.earned_on || '') + '"></div>' +
        '<div class="col"><label class="form-label">Expires</label><input type="date" class="form-control" name="expires_on" value="' + (c.expires_on || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Credential ID</label><input class="form-control mx-mono" name="credential_id" maxlength="120" value="' + MX.escape(c.credential_id || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Verification link</label><input type="url" class="form-control" name="verify_url" maxlength="255" placeholder="https://" value="' + MX.escape(c.verify_url || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status">' +
        ['In Progress', 'Active', 'Expired'].map(function (s) { return '<option' + ((c.status || 'Active') === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') +
        '</select><div class="form-text">Anything past its expiry date shows as Expired regardless of this value.</div></div>' +
        '<div class="mb-3"><label class="form-label">Certificate file' + (c.has_certificate ? ' (replace)' : '') + '</label>' +
        '<input type="file" class="form-control" id="cert-file" accept=".pdf,.png,.jpg,.jpeg">' +
        '<div class="form-text">PDF, JPG or PNG. Attached as evidence when a tender requires proof of this qualification.' +
        (c.has_certificate ? ' A file is already attached; choosing a new one replaces it.' : '') + '</div></div>' +
        '</form>';
}

function mxNewCert() {
    MX.drawer.open({
        title: 'Add certification',
        body: mxCertForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitCert(null)">Add</button>'
    });
}
function mxEditCert(id) {
    var c = mxCerts.find(function (x) { return x.id == id; });
    if (!c) return;
    MX.drawer.open({
        title: 'Edit ' + c.code,
        body: mxCertForm(c),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitCert(' + id + ')">Save changes</button>'
    });
}
function mxSubmitCert(id) {
    var form = document.getElementById('cert-form');
    if (!form.reportValidity()) return;
    // The file input has no name so it stays out of the JSON metadata; it is
    // uploaded separately once the record exists, since it needs multipart.
    var fileInput = document.getElementById('cert-file');
    var file = fileInput && fileInput.files[0];
    var call = id ? MX.api('PATCH', '/certifications/' + id, MX.formData(form)) : MX.api('POST', '/certifications', MX.formData(form));
    call.then(function (data) {
        var certId = id || (data && data.certification_id);
        if (file && certId) { return mxUploadCertFile(certId, file); }
    }).then(function () {
        MX.drawer.close(); MX.ok(id ? 'Updated.' : 'Added.'); mxLoadCerts();
    }).catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxUploadCertFile(certId, file) {
    var fd = new FormData();
    fd.append('file', file);
    return fetch('/certifications/' + certId + '/file', {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd, redirect: 'manual'
    }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok === false) { throw new Error(d.error || 'The certificate file could not be uploaded.'); }
    });
}
function mxDownloadCert(id) {
    MX.api('POST', '/certifications/' + id + '/file-link', {})
        .then(function (data) { window.location.href = data.url; })
        .catch(function (e) { MX.fail(e.message); });
}
function mxDeleteCert(id) {
    MX.confirm('Remove this certification?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/certifications/' + id)
            .then(function () { MX.ok('Removed.'); mxLoadCerts(); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxLoadCerts);
</script>
