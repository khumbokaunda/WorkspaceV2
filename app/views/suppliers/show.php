<?php
// Supplier detail: identity and the manufacturer authorizations held against
// this supplier, each with a gated file and tracked expiry.
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-center gap-3 flex-wrap">
        <span class="mx-avatar mx-avatar-lg"><i class="fa-solid fa-truck-field"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($supplier['name']) ?></h1>
                <span class="mx-chip mx-chip-plain"><?= e($supplier['category']) ?></span>
<?php if ($supplier['rating'] !== null): ?>
                <span class="mx-chip mx-chip-info">Rating <?= (int)$supplier['rating'] ?>/5</span>
<?php endif; ?>
            </div>
            <div class="text-muted mt-1"><?= e($supplier['product_lines'] ?: 'No product lines recorded') ?></div>
        </div>
<?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="mxEditSupplier()"><i class="fa-solid fa-pen me-2"></i>Edit</button>
            <button class="btn btn-subtle" style="color:var(--mx-danger)" onclick="mxDeleteSupplier()"><i class="fa-regular fa-trash-can me-2"></i>Delete</button>
        </div>
<?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Contact</h2></div>
            <div class="mx-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-4 text-muted fw-normal">Contact</dt><dd class="col-8"><?= e($supplier['contact_name'] ?: 'Not set') ?></dd>
                    <dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8"><?= e($supplier['phone'] ?: 'Not set') ?></dd>
                    <dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8"><?= $supplier['email'] ? '<a href="mailto:' . e($supplier['email']) . '">' . e($supplier['email']) . '</a>' : 'Not set' ?></dd>
                    <dt class="col-4 text-muted fw-normal">Lead time</dt><dd class="col-8 mb-0"><?= e($supplier['lead_time_notes'] ?: 'Not set') ?></dd>
                </dl>
<?php if (!empty($supplier['notes'])): ?>
                <hr>
                <p class="mb-0" style="font-size:13px;white-space:pre-line"><?= e($supplier['notes']) ?></p>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Manufacturer authorizations</h2>
                <div class="d-flex gap-2">
                    <a href="/suppliers" class="btn btn-subtle btn-sm">All suppliers</a>
<?php if ($canManage): ?>
                    <button class="btn btn-outline-primary btn-sm" onclick="mxNewAuth()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
                </div>
            </div>
            <div class="mx-card-body mx-flush" id="mx-auth-list"></div>
        </div>
    </div>
</div>

<script>
var mxSupplier = <?= json_encode([
    'id' => (int)$supplier['id'], 'name' => $supplier['name'], 'category' => $supplier['category'],
    'product_lines' => $supplier['product_lines'], 'contact_name' => $supplier['contact_name'],
    'phone' => $supplier['phone'], 'email' => $supplier['email'],
    'lead_time_notes' => $supplier['lead_time_notes'], 'rating' => $supplier['rating'] !== null ? (int)$supplier['rating'] : null,
    'notes' => $supplier['notes'],
]) ?>;
var mxAuths = <?= json_encode($authorizations) ?>;
var mxSupplierCategories = <?= json_encode(array_values($categories)) ?>;
var mxAuthStatuses = <?= json_encode(array_values($statuses)) ?>;
var mxCanManageSuppliers = <?= $canManage ? 'true' : 'false' ?>;

function mxAuthChip(s) {
    var map = { Active: 'mx-chip-success', Expired: 'mx-chip-danger', Revoked: 'mx-chip-plain' };
    return '<span class="mx-chip ' + (map[s] || 'mx-chip-plain') + '">' + MX.escape(s) + '</span>';
}
function mxRenderAuths() {
    var box = document.getElementById('mx-auth-list');
    if (!mxAuths.length) {
        box.innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-file-shield"></i><p class="mb-0">No authorizations recorded. These prove you are an authorized reseller of a brand and are selectable when a tender requires proof.</p></div>';
        return;
    }
    box.innerHTML = '<ul class="list-unstyled m-0">' + mxAuths.map(function (a) {
        var expiryHtml = '';
        if (a.expiry_date) {
            var color = a.days_left < 0 ? 'var(--mx-danger)' : a.days_left <= 60 ? 'var(--mx-warning)' : 'var(--mx-muted)';
            expiryHtml = '<span style="color:' + color + '">expires ' + a.expiry_date + (a.days_left >= 0 ? ' (' + a.days_left + ' d)' : '') + '</span>';
        }
        var actions = '';
        if (a.has_file) actions += '<button class="btn btn-subtle btn-sm" onclick="mxDownloadAuth(' + a.id + ')" aria-label="Download"><i class="fa-solid fa-download"></i></button>';
        if (mxCanManageSuppliers) {
            actions += '<button class="btn btn-subtle btn-sm" onclick="mxEditAuth(' + a.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteAuth(' + a.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>';
        }
        return '<li class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">' +
            '<div class="flex-grow-1"><div style="font-size:13px"><strong>' + MX.escape(a.product_line) + '</strong> ' + mxAuthChip(a.effective_status) + '</div>' +
            '<small class="text-muted">' + (a.authorization_ref ? MX.escape(a.authorization_ref) + ' · ' : '') + expiryHtml + '</small>' +
            (a.notes ? '<div style="font-size:12px" class="mt-1">' + MX.escape(a.notes) + '</div>' : '') +
            '</div><div class="d-flex">' + actions + '</div></li>';
    }).join('') + '</ul>';
}

function mxEditSupplier() {
    var s = mxSupplier;
    var body = '<form id="supplier-edit-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="200" value="' + MX.escape(s.name) + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Category</label><select class="form-select" name="category">' +
        mxSupplierCategories.map(function (t) { return '<option' + (s.category === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select></div><div class="col"><label class="form-label">Rating (1 to 5)</label><input type="number" min="1" max="5" class="form-control" name="rating" value="' + (s.rating != null ? s.rating : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Product lines or brands</label><input class="form-control" name="product_lines" maxlength="400" value="' + MX.escape(s.product_lines || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Contact</label><input class="form-control" name="contact_name" maxlength="160" value="' + MX.escape(s.contact_name || '') + '"></div>' +
        '<div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="60" value="' + MX.escape(s.phone || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" maxlength="190" value="' + MX.escape(s.email || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Lead time notes</label><input class="form-control" name="lead_time_notes" maxlength="400" value="' + MX.escape(s.lead_time_notes || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="2000">' + MX.escape(s.notes || '') + '</textarea></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Edit supplier', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSaveSupplier()">Save changes</button>'
    });
}
function mxSaveSupplier() {
    var form = document.getElementById('supplier-edit-form');
    if (!form.reportValidity()) return;
    MX.api('PATCH', '/suppliers/' + mxSupplier.id, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteSupplier() {
    MX.confirm('Delete this supplier?', 'Its authorizations and their files are removed.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/suppliers/' + mxSupplier.id)
            .then(function () { MX.ok('Deleted.'); setTimeout(function () { location.href = '/suppliers'; }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

function mxAuthForm(a) {
    a = a || {};
    return '<form id="auth-form">' +
        '<div class="mb-3"><label class="form-label">Product line or brand</label><input class="form-control" name="product_line" required maxlength="200" value="' + MX.escape(a.product_line || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Authorization reference</label><input class="form-control" name="authorization_ref" maxlength="160" value="' + MX.escape(a.authorization_ref || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Issued</label><input type="date" class="form-control" name="issue_date" value="' + (a.issue_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Expires</label><input type="date" class="form-control" name="expiry_date" value="' + (a.expiry_date || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status">' +
        mxAuthStatuses.map(function (t) { return '<option' + ((a.status || 'Active') === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select><div class="form-text">Anything past its expiry date shows as Expired regardless of this value.</div></div>' +
        '<div class="mb-3"><label class="form-label">Authorization file' + (a.has_file ? ' (replace)' : '') + '</label><input type="file" class="form-control" name="file" accept=".pdf,.png,.jpg,.jpeg"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(a.notes || '') + '"></div>' +
        '</form>';
}
function mxNewAuth() {
    MX.drawer.open({
        title: 'Add authorization', body: mxAuthForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitAuth(null)">Add</button>'
    });
}
function mxEditAuth(id) {
    var a = mxAuths.find(function (x) { return x.id == id; });
    if (!a) return;
    MX.drawer.open({
        title: 'Edit authorization', body: mxAuthForm(a),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitAuth(' + id + ')">Save changes</button>'
    });
}
function mxSubmitAuth(id) {
    var form = document.getElementById('auth-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    ['product_line', 'authorization_ref', 'issue_date', 'expiry_date', 'status', 'notes'].forEach(function (k) {
        fd.append(k, MX.clean(form[k].value));
    });
    if (form.file.files[0]) fd.append('file', form.file.files[0]);
    var url = '/suppliers/' + mxSupplier.id + '/authorizations';
    if (id) { url = '/suppliers/authorizations/' + id; fd.append('_method', 'PATCH'); }
    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd, redirect: 'manual'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok === false) { MX.fail(data.error); MX.showFieldErrors(form, data.fields); return; }
        MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Added.'); setTimeout(function () { location.reload(); }, 600);
    }).catch(function () { MX.fail(); });
}
function mxDownloadAuth(id) {
    MX.api('POST', '/suppliers/authorizations/' + id + '/link', {})
        .then(function (data) { window.location.href = data.url; })
        .catch(function (e) { MX.fail(e.message); });
}
function mxDeleteAuth(id) {
    MX.confirm('Delete this authorization?', 'The attached file is removed permanently.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/suppliers/authorizations/' + id)
            .then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxRenderAuths);
</script>
