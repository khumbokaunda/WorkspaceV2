<?php
// Suppliers register. Manufacturer authorizations live on the supplier detail
// page, where their expiry is tracked.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Suppliers</h1>
<?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="mxNewSupplier()"><i class="fa-solid fa-plus me-2"></i>Add supplier</button>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Supplier register</h2></div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search name, product lines, contact" aria-label="Search suppliers">
        <select id="sp-cat" class="form-select form-select-sm" style="max-width:190px" aria-label="Filter by category">
            <option value="">All categories</option>
<?php foreach ($categories as $c): ?>
            <option><?= e($c) ?></option>
<?php endforeach; ?>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxSupplierTable, 'suppliers')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-supplier-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Name</th><th>Category</th><th>Product lines</th><th>Contact</th><th>Authorizations</th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-supplier-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-truck-field"></i>
            <p>No suppliers yet.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewSupplier()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanManageSuppliers = <?= $canManage ? 'true' : 'false' ?>;
var mxSupplierCategories = <?= json_encode(array_values($categories)) ?>;
var mxSuppliers = [];

function mxLoadSuppliers() {
    fetch('/api/suppliers', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxSuppliers = data.suppliers || []; mxRenderSuppliers(); });
}
function mxRenderSuppliers() {
    var labels = ['Name', 'Category', 'Product lines', 'Contact', 'Authorizations'];
    var rows = mxSuppliers.map(function (s) {
        return [
            '<a href="/suppliers/' + s.id + '">' + MX.escape(s.name) + '</a>',
            MX.escape(s.category),
            MX.escape(s.product_lines || ''),
            MX.escape(s.contact_name || ''),
            '<span class="mx-tabular">' + (s.authorization_count || 0) + '</span>'
        ];
    });
    if (window.mxSupplierTable) { window.mxSupplierTable.clear(); window.mxSupplierTable.rows.add(rows).draw(); }
    else {
        window.mxSupplierTable = MX.table('#mx-supplier-table', {
            data: rows,
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
        document.getElementById('sp-cat').addEventListener('change', function () { window.mxSupplierTable.column(1).search(this.value).draw(); });
    }
    document.getElementById('mx-supplier-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-supplier-table').style.display = rows.length ? '' : 'none';
}
function mxSupplierForm(s) {
    s = s || {};
    return '<form id="supplier-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="200" value="' + MX.escape(s.name || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Category</label><select class="form-select" name="category">' +
        mxSupplierCategories.map(function (t) { return '<option' + ((s.category || 'Distributor') === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select></div><div class="col"><label class="form-label">Rating (1 to 5)</label><input type="number" min="1" max="5" class="form-control" name="rating" value="' + (s.rating != null ? s.rating : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Product lines or brands</label><input class="form-control" name="product_lines" maxlength="400" value="' + MX.escape(s.product_lines || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Contact</label><input class="form-control" name="contact_name" maxlength="160" value="' + MX.escape(s.contact_name || '') + '"></div>' +
        '<div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="60" value="' + MX.escape(s.phone || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" maxlength="190" value="' + MX.escape(s.email || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Lead time notes</label><input class="form-control" name="lead_time_notes" maxlength="400" value="' + MX.escape(s.lead_time_notes || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="2000">' + MX.escape(s.notes || '') + '</textarea></div>' +
        '</form>';
}
function mxNewSupplier() {
    MX.drawer.open({
        title: 'Add supplier', body: mxSupplierForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitSupplier(null)">Add</button>'
    });
}
function mxSubmitSupplier(id) {
    var form = document.getElementById('supplier-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/suppliers/' + id, MX.formData(form)) : MX.api('POST', '/suppliers', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok(id ? 'Saved.' : 'Added.'); mxLoadSuppliers(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

document.addEventListener('DOMContentLoaded', mxLoadSuppliers);
</script>
