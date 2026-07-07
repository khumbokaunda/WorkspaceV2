<?php // Asset register: table with chips and mono identifiers, drawer with history timeline. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Asset Register</h1>
    <div class="d-flex gap-2">
<?php if ($canManage): ?>
        <button class="btn btn-outline-primary" onclick="mxImportCsv()"><i class="fa-solid fa-file-import me-2"></i>Import CSV</button>
        <button class="btn btn-primary" onclick="mxNewAsset()"><i class="fa-solid fa-plus me-2"></i>Add asset</button>
<?php endif; ?>
    </div>
</div>

<div class="mx-card">
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search tag, name, serial" aria-label="Search assets">
        <select id="af-category" class="form-select form-select-sm" style="max-width:160px" aria-label="Filter by category">
            <option value="">All categories</option>
<?php foreach ($categories as $c): ?>
            <option><?= e($c) ?></option>
<?php endforeach; ?>
        </select>
        <select id="af-status" class="form-select form-select-sm" style="max-width:150px" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option>Available</option><option>Assigned</option><option>In Repair</option><option>Retired</option>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxAssetTable, 'assets')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-asset-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Tag</th><th>Name</th><th>Category</th><th>Serial</th><th>Holder</th><th>Warranty</th><th>Status</th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-assets-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-laptop"></i>
            <p>The register is empty. Add assets one by one or import a CSV.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewAsset()">Add the first asset</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanManageAssets = <?= $canManage ? 'true' : 'false' ?>;
var mxCanAssign = <?= $canAssign ? 'true' : 'false' ?>;
var mxAssetPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;
var mxAssetCategories = <?= json_encode($categories) ?>;
var mxAssets = [];
var mxAssetToday = '';

function mxAssetChip(s) {
    var cls = { Available: 'mx-chip-success', Assigned: 'mx-chip-primary', 'In Repair': 'mx-chip-warning', Retired: 'mx-chip-plain' }[s];
    return '<span class="mx-chip ' + cls + '">' + s + '</span>';
}

function mxLoadAssets() {
    fetch('/api/assets', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            mxAssets = data.assets;
            mxAssetToday = data.today;
            var labels = ['Tag', 'Name', 'Category', 'Serial', 'Holder', 'Warranty', 'Status'];
            var rows = data.assets.map(function (a) {
                var warrantySoon = a.warranty_expiry && a.status !== 'Retired' && a.warranty_expiry >= mxAssetToday &&
                    (new Date(a.warranty_expiry) - new Date(mxAssetToday)) / 86400000 <= 60;
                var warrantyOver = a.warranty_expiry && a.warranty_expiry < mxAssetToday;
                return [
                    '<a href="javascript:void(0)" onclick="mxOpenAsset(' + a.id + ')"><code>' + MX.escape(a.asset_tag) + '</code></a>',
                    MX.escape(a.name),
                    MX.escape(a.category),
                    a.serial_number ? '<code>' + MX.escape(a.serial_number) + '</code>' : '',
                    MX.escape(a.holder_first ? a.holder_first + ' ' + a.holder_last : ''),
                    a.warranty_expiry
                        ? '<span style="' + (warrantyOver ? 'color:var(--mx-danger)' : warrantySoon ? 'color:var(--mx-warning)' : '') + '" class="mx-tabular">' + a.warranty_expiry + '</span>'
                        : '',
                    mxAssetChip(a.status)
                ];
            });
            if (window.mxAssetTable) { window.mxAssetTable.clear(); window.mxAssetTable.rows.add(rows).draw(); }
            else {
                window.mxAssetTable = MX.table('#mx-asset-table', {
                    data: rows,
                    columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
                });
                document.getElementById('af-category').addEventListener('change', function () { window.mxAssetTable.column(2).search(this.value ? '^' + this.value + '$' : '', true, false).draw(); });
                document.getElementById('af-status').addEventListener('change', function () { window.mxAssetTable.column(6).search(this.value).draw(); });
            }
            document.getElementById('mx-assets-empty').style.display = rows.length ? 'none' : '';
            document.getElementById('mx-asset-table').style.display = rows.length ? '' : 'none';
            var openId = new URLSearchParams(location.search).get('asset');
            if (openId) { mxOpenAsset(parseInt(openId, 10)); history.replaceState(null, '', location.pathname); }
        });
}

function mxAssetForm(a) {
    a = a || {};
    return '<form id="asset-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Asset tag</label><input class="form-control mx-mono" name="asset_tag" required maxlength="60" value="' + MX.escape(a.asset_tag || '') + '"></div>' +
        '<div class="col"><label class="form-label">Category</label><select class="form-select" name="category">' +
        mxAssetCategories.map(function (c) { return '<option' + (a.category === c ? ' selected' : '') + '>' + c + '</option>'; }).join('') +
        '</select></div></div>' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160" value="' + MX.escape(a.name || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Serial number</label><input class="form-control mx-mono" name="serial_number" maxlength="120" value="' + MX.escape(a.serial_number || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Purchased</label><input type="date" class="form-control" name="purchase_date" value="' + (a.purchase_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Warranty until</label><input type="date" class="form-control" name="warranty_expiry" value="' + (a.warranty_expiry || '') + '"></div></div>' +
        (a.id && a.status !== 'Assigned'
            ? '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status">' +
              ['Available', 'In Repair', 'Retired'].map(function (s) { return '<option' + (a.status === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select></div>'
            : '') +
        '<div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note" maxlength="500" value="' + MX.escape(a.note || '') + '"></div>' +
        '</form>';
}

function mxNewAsset() {
    MX.drawer.open({
        title: 'Add asset',
        body: mxAssetForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitAsset(null)">Add asset</button>'
    });
}

function mxOpenAsset(id) {
    MX.drawer.skeleton('Asset');
    fetch('/api/assets/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { MX.drawer.close(); MX.fail(data.error); return; }
            var a = data.asset;
            var body =
                '<div class="d-flex gap-2 align-items-center mb-3 flex-wrap">' + mxAssetChip(a.status) +
                '<code style="font-size:14px">' + MX.escape(a.asset_tag) + '</code></div>' +
                '<dl class="row" style="font-size:13px">' +
                '<dt class="col-4 text-muted fw-normal">Name</dt><dd class="col-8">' + MX.escape(a.name) + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Category</dt><dd class="col-8">' + MX.escape(a.category) + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Serial</dt><dd class="col-8">' + (a.serial_number ? '<code>' + MX.escape(a.serial_number) + '</code>' : 'Not set') + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Purchased</dt><dd class="col-8">' + (a.purchase_date || 'Not set') + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Warranty</dt><dd class="col-8">' + (a.warranty_expiry || 'Not set') + '</dd>' +
                (a.note ? '<dt class="col-4 text-muted fw-normal">Note</dt><dd class="col-8">' + MX.escape(a.note) + '</dd>' : '') +
                '</dl>' +
                '<hr><h3 style="font-size:14px" class="mb-3">Assignment history</h3>' +
                (data.history.length
                    ? '<ul class="mx-timeline">' + data.history.map(function (h) {
                        return '<li><div style="font-size:13px"><strong>' + MX.escape(h.first_name + ' ' + h.last_name) + '</strong>' +
                            (h.returned_at ? '' : ' <span class="mx-chip mx-chip-primary">current</span>') + '</div>' +
                            '<div class="mx-timeline-time">' + h.assigned_at + (h.returned_at ? ' to ' + h.returned_at : ' to now') + ', by ' + MX.escape(h.assigner) + '</div></li>';
                    }).join('') + '</ul>'
                    : '<p class="text-muted" style="font-size:13px">Never assigned.</p>');
            var footer = '';
            if (mxCanAssign && a.status === 'Available') footer += '<button class="btn btn-primary" onclick="mxAssignAsset(' + a.id + ')">Assign</button>';
            if (mxCanAssign && a.status === 'Assigned') footer += '<button class="btn btn-primary" onclick="mxReturnAsset(' + a.id + ')">Mark returned</button>';
            if (mxCanManageAssets) footer = '<button class="btn btn-outline-primary" onclick="mxEditAsset(' + a.id + ')">Edit</button>' + footer;
            MX.drawer.open({ title: a.name, body: body, footer: footer || null });
        });
}

function mxEditAsset(id) {
    var a = mxAssets.find(function (x) { return x.id == id; });
    if (!a) return;
    MX.drawer.open({
        title: 'Edit asset',
        body: mxAssetForm(a),
        footer: '<button class="btn btn-outline-primary" onclick="mxOpenAsset(' + id + ')">Back</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitAsset(' + id + ')">Save changes</button>'
    });
}

function mxSubmitAsset(id) {
    var form = document.getElementById('asset-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/assets/' + id, MX.formData(form)) : MX.api('POST', '/assets', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok(id ? 'Asset updated.' : 'Asset added.'); mxLoadAssets(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxAssignAsset(id) {
    var body = '<form id="assign-form">' +
        '<div class="mb-3"><label class="form-label">Assign to</label><select class="form-select" name="person_id" required>' +
        '<option value="">Choose a person</option>' +
        mxAssetPeople.map(function (p) { return '<option value="' + p.id + '">' + MX.escape(p.name) + '</option>'; }).join('') +
        '</select></div></form>';
    MX.drawer.open({
        title: 'Assign asset',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="mxOpenAsset(' + id + ')">Back</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitAssign(' + id + ')">Assign</button>'
    });
}
function mxSubmitAssign(id) {
    var form = document.getElementById('assign-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/assets/' + id + '/assign', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Assigned. The person has been notified.'); mxLoadAssets(); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxReturnAsset(id) {
    MX.confirm('Mark this asset as returned?', 'It becomes Available again.', 'Mark returned').then(function (go) {
        if (!go) return;
        MX.api('POST', '/assets/' + id + '/return', {})
            .then(function () { MX.drawer.close(); MX.ok('Returned.'); mxLoadAssets(); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

// CSV import with a preview and validation step before commit.
function mxImportCsv() {
    var body = '<form id="import-form">' +
        '<p class="text-muted" style="font-size:13px">Header row required:<br><code style="font-size:11px">asset_tag,name,category,serial_number,purchase_date,warranty_expiry,note</code></p>' +
        '<div class="mb-3"><input type="file" class="form-control" name="file" required accept=".csv"></div>' +
        '<div id="import-preview"></div></form>';
    MX.drawer.open({
        title: 'Import assets from CSV',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" id="import-btn" onclick="mxSubmitImport(\'preview\')">Preview</button>'
    });
}
function mxSubmitImport(mode) {
    var form = document.getElementById('import-form');
    if (!form.file.files[0]) { MX.fail('Choose a CSV file first.'); return; }
    var fd = new FormData();
    fd.append('file', form.file.files[0]);
    fd.append('mode', mode);
    fetch('/assets/import', {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok === false) { MX.fail(data.error); return; }
        if (data.mode === 'preview') {
            var box = document.getElementById('import-preview');
            box.innerHTML = '<p style="font-size:13px"><strong>' + data.valid + '</strong> rows ready, <strong>' + data.invalid + '</strong> with problems.</p>' +
                '<div style="max-height:220px;overflow-y:auto;font-size:12px">' +
                data.rows.map(function (r) {
                    return '<div class="d-flex gap-2 align-items-start border-bottom py-1">' +
                        '<i class="fa-solid ' + (r.problems.length ? 'fa-circle-xmark' : 'fa-circle-check') + '" style="color:var(' + (r.problems.length ? '--mx-danger' : '--mx-success') + ');margin-top:3px"></i>' +
                        '<div><code>' + MX.escape(r.asset_tag) + '</code> ' + MX.escape(r.name) +
                        (r.problems.length ? '<div style="color:var(--mx-danger)">' + MX.escape(r.problems.join('; ')) + '</div>' : '') +
                        '</div></div>';
                }).join('') + '</div>';
            var btn = document.getElementById('import-btn');
            if (data.valid > 0) {
                btn.textContent = 'Import ' + data.valid + ' assets';
                btn.onclick = function () { mxSubmitImport('commit'); };
            }
        } else {
            MX.drawer.close();
            MX.ok('Imported ' + data.inserted + ' assets' + (data.skipped ? ', skipped ' + data.skipped : '') + '.');
            mxLoadAssets();
        }
    }).catch(function () { MX.fail(); });
}

document.addEventListener('DOMContentLoaded', mxLoadAssets);
</script>
