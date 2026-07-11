<?php
// Pay component catalogue. The seeded components and any tax bands are
// placeholders the company confirms with their accountant.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Pay components</h1>
    <div class="d-flex gap-2">
        <a href="/payroll" class="btn btn-subtle">Payroll home</a>
        <button class="btn btn-primary" onclick="mxNewComponent()"><i class="fa-solid fa-plus me-2"></i>Add component</button>
    </div>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-body">
        <p class="mb-0" style="font-size:13px"><i class="fa-solid fa-triangle-exclamation me-1" style="color:var(--mx-warning)"></i>These are placeholders, not authoritative rates. Confirm every earning, deduction and tax band with your accountant and against current law before running payroll.</p>
    </div>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Catalogue</h2></div>
    <div class="mx-card-body mx-flush">
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Name</th><th>Type</th><th>Method</th><th class="text-end">Default rate</th><th>Taxable</th><th>Active</th><th></th></tr></thead>
            <tbody>
<?php foreach ($components as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td>
                    <td><span class="mx-chip <?= $c['comp_type'] === 'Earning' ? 'mx-chip-success' : 'mx-chip-warning' ?>"><?= e($c['comp_type']) ?></span></td>
                    <td><?= e($c['calc_method']) ?></td>
                    <td class="text-end mx-tabular"><?= $c['default_rate'] !== null ? e(rtrim(rtrim(number_format((float)$c['default_rate'], 4), '0'), '.')) : ($c['calc_method'] === 'Banded' ? '<span class="text-muted">bands</span>' : '') ?></td>
                    <td><?= (int)$c['is_taxable'] === 1 ? 'Yes' : 'No' ?></td>
                    <td><?= (int)$c['is_active'] === 1 ? '<span class="mx-chip mx-chip-success">Active</span>' : '<span class="mx-chip mx-chip-plain">Off</span>' ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-subtle btn-sm" onclick='mxEditComponent(<?= json_encode(["id"=>(int)$c["id"],"comp_type"=>$c["comp_type"],"name"=>$c["name"],"calc_method"=>$c["calc_method"],"default_rate"=>$c["default_rate"],"bands"=>$c["bands"],"is_taxable"=>(int)$c["is_taxable"],"is_active"=>(int)$c["is_active"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteComponent(<?= (int)$c['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>

<script>
var mxCompTypes = <?= json_encode(array_values($types)) ?>;
var mxCompMethods = <?= json_encode(array_values($methods)) ?>;

function mxComponentForm(c) {
    c = c || {};
    return '<form id="comp-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="120" value="' + MX.escape(c.name || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Type</label><select class="form-select" name="comp_type">' +
        mxCompTypes.map(function (t) { return '<option' + ((c.comp_type || 'Earning') === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') + '</select></div>' +
        '<div class="col"><label class="form-label">Method</label><select class="form-select" name="calc_method" onchange="mxCompMethodChange(this.value)">' +
        mxCompMethods.map(function (m) { return '<option' + ((c.calc_method || 'Fixed Amount') === m ? ' selected' : '') + '>' + MX.escape(m) + '</option>'; }).join('') + '</select></div></div>' +
        '<div class="mb-3" id="comp-rate-wrap"><label class="form-label">Default rate (amount or percent)</label><input type="number" step="0.0001" class="form-control" name="default_rate" value="' + (c.default_rate != null ? c.default_rate : '') + '"></div>' +
        '<div class="mb-3" id="comp-bands-wrap" style="display:' + ((c.calc_method || 'Fixed Amount') === 'Banded' ? 'block' : 'none') + '"><label class="form-label">Bands (JSON)</label><textarea class="form-control mx-mono" name="bands" rows="4" style="font-size:12px">' + MX.escape(c.bands || '[{"upto":100000,"rate":0},{"upto":null,"rate":30}]') + '</textarea><div class="form-text">List of {upto, rate}. upto null means the top band. Applied to taxable gross.</div></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_taxable" id="comp-tax"' + (c.id ? (c.is_taxable ? ' checked' : '') : ' checked') + '><label class="form-check-label" for="comp-tax">Taxable (counts toward taxable gross)</label></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="comp-active"' + (!c.id || c.is_active ? ' checked' : '') + '><label class="form-check-label" for="comp-active">Active</label></div>' +
        '</form>';
}
function mxCompMethodChange(m) {
    document.getElementById('comp-bands-wrap').style.display = m === 'Banded' ? 'block' : 'none';
    document.getElementById('comp-rate-wrap').style.display = m === 'Banded' ? 'none' : 'block';
}
function mxNewComponent() {
    MX.drawer.open({ title: 'Add pay component', body: mxComponentForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitComponent(null)">Add</button>' });
}
function mxEditComponent(c) {
    MX.drawer.open({ title: 'Edit component', body: mxComponentForm(c),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitComponent(' + c.id + ')">Save</button>' });
}
function mxSubmitComponent(id) {
    var form = document.getElementById('comp-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/payroll/components/' + id, MX.formData(form)) : MX.api('POST', '/payroll/components', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteComponent(id) {
    MX.confirm('Delete this component?', 'Salary lines using it are removed too.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/payroll/components/' + id).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
