<?php
// A person's HR record: personal details, beneficiaries, bank accounts, and,
// for those who may see figures, the current salary structure. Employees edit
// their own; HR edits on their behalf.
$fmt = fn($v) => $v === null || $v === '' ? '' : $currency . ' ' . number_format((float)$v, 2);
$d = $details;
$isSelf = (int)$person['id'] === (int)(current_user()['person_id'] ?? 0);
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-center gap-3 flex-wrap">
        <span class="mx-avatar mx-avatar-lg"><?= e(strtoupper(mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1))) ?></span>
        <div class="flex-grow-1">
            <h1 style="font-size:20px" class="mb-0"><?= e($person['first_name'] . ' ' . $person['last_name']) ?></h1>
            <div class="text-muted mt-1"><?= e($person['job_title'] ?: '') ?><?= $person['department'] ? ' &middot; ' . e($person['department']) : '' ?><?= $isSelf ? ' &middot; your record' : '' ?></div>
        </div>
        <a href="/payroll" class="btn btn-subtle btn-sm">Payroll home</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Personal details</h2>
<?php if ($canEdit): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxEditDetails()"><i class="fa-solid fa-pen me-1"></i>Edit</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-5 text-muted fw-normal">Date of birth</dt><dd class="col-7"><?= e($d['date_of_birth'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">National id</dt><dd class="col-7"><?= e($d['national_id'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Gender</dt><dd class="col-7"><?= e($d['gender'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Marital status</dt><dd class="col-7"><?= e($d['marital_status'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Tax id</dt><dd class="col-7"><?= e($d['tax_id'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Home address</dt><dd class="col-7" style="white-space:pre-line"><?= e($d['home_address'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Personal phone</dt><dd class="col-7"><?= e($d['personal_phone'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Personal email</dt><dd class="col-7"><?= e($d['personal_email'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Emergency contact</dt><dd class="col-7 mb-0"><?= e(trim(($d['emergency_contact_name'] ?? '') . ' ' . ($d['emergency_contact_phone'] ?? ''))) ?: 'Not set' ?></dd>
                </dl>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Beneficiaries and next of kin</h2>
<?php if ($canEdit): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxAddBeneficiary()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$beneficiaries): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-people-roof"></i><p class="mb-0">No beneficiaries recorded.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($beneficiaries as $b): ?>
                    <li class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($b['name']) ?></strong><?= $b['relationship'] ? ' <span class="text-muted">' . e($b['relationship']) . '</span>' : '' ?><?= (int)$b['is_payroll_beneficiary'] === 1 ? ' <span class="mx-chip mx-chip-info" style="font-size:10px">payroll</span>' : '' ?></div>
                            <small class="text-muted"><?= $b['share_percent'] !== null ? e(rtrim(rtrim(number_format((float)$b['share_percent'], 2), '0'), '.')) . '% &middot; ' : '' ?><?= e($b['contact'] ?: '') ?></small>
                        </div>
<?php if ($canEdit): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteBeneficiary(<?= (int)$b['id'] ?>)" aria-label="Remove"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Bank accounts</h2>
<?php if ($canEdit): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxAddBank()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$banks): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-building-columns"></i><p class="mb-0">No bank account on file for salary payment.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($banks as $bk): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($bk['bank_name']) ?></strong><?= (int)$bk['is_primary'] === 1 ? ' <span class="mx-chip mx-chip-success" style="font-size:10px">primary</span>' : '' ?></div>
                            <small class="text-muted"><?= e($bk['branch'] ?: '') ?><?= $bk['account_name'] ? ' &middot; ' . e($bk['account_name']) : '' ?> &middot; <span class="mx-mono"><?= e($bk['account_display']) ?></span></small>
                        </div>
<?php if ($canEdit): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteBank(<?= (int)$bk['id'] ?>)" aria-label="Remove"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>

<?php if ($canSeeFigures): ?>
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Salary structure</h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxEditStructure()"><i class="fa-solid fa-pen me-1"></i>Set</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body">
<?php if (!$structure): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-money-check-dollar"></i><p class="mb-0">No salary structure set.</p></div>
<?php else: ?>
                <dl class="row mb-2" style="font-size:13px">
                    <dt class="col-5 text-muted fw-normal">Effective from</dt><dd class="col-7"><?= e($structure['effective_from']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Basic salary</dt><dd class="col-7 mx-tabular"><strong><?= e($fmt($structure['basic_salary'])) ?></strong></dd>
                </dl>
<?php if ($structureLines): ?>
                <table class="table table-sm align-middle mb-0" style="font-size:13px">
                    <thead><tr><th>Component</th><th>Type</th><th class="text-end">Amount or rate</th></tr></thead>
                    <tbody>
<?php foreach ($structureLines as $sl): ?>
                        <tr>
                            <td><?= e($sl['name']) ?></td>
                            <td><span class="mx-chip <?= $sl['comp_type'] === 'Earning' ? 'mx-chip-success' : 'mx-chip-warning' ?>" style="font-size:10px"><?= e($sl['comp_type']) ?></span></td>
                            <td class="text-end mx-tabular"><?= $sl['amount'] !== null ? e($fmt($sl['amount'])) : ($sl['rate'] !== null ? e(rtrim(rtrim(number_format((float)$sl['rate'], 4), '0'), '.')) . '%' : '<span class="text-muted">default</span>') ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
<?php endif; ?>
<?php endif; ?>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>

<script>
var mxPersonId = <?= (int)$person['id'] ?>;
var mxDetails = <?= json_encode($d ?: new stdClass()) ?>;
var mxComponents = <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'comp_type' => $c['comp_type'], 'calc_method' => $c['calc_method'], 'default_rate' => $c['default_rate']], $components)) ?>;
var mxStructure = <?= json_encode($structure ?: new stdClass()) ?>;
var mxStructureLines = <?= json_encode(array_map(fn($l) => ['component_id' => (int)$l['component_id'], 'amount' => $l['amount'], 'rate' => $l['rate']], $structureLines)) ?>;

function mxEditDetails() {
    var v = mxDetails;
    var body = '<form id="det-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Date of birth</label><input type="date" class="form-control" name="date_of_birth" value="' + (v.date_of_birth || '') + '"></div>' +
        '<div class="col"><label class="form-label">National id</label><input class="form-control" name="national_id" maxlength="60" value="' + MX.escape(v.national_id || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Gender</label><input class="form-control" name="gender" maxlength="30" value="' + MX.escape(v.gender || '') + '"></div>' +
        '<div class="col"><label class="form-label">Marital status</label><input class="form-control" name="marital_status" maxlength="30" value="' + MX.escape(v.marital_status || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Tax id</label><input class="form-control" name="tax_id" maxlength="60" value="' + MX.escape(v.tax_id || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Home address</label><textarea class="form-control" name="home_address" rows="2" maxlength="400">' + MX.escape(v.home_address || '') + '</textarea></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Personal phone</label><input class="form-control" name="personal_phone" maxlength="60" value="' + MX.escape(v.personal_phone || '') + '"></div>' +
        '<div class="col"><label class="form-label">Personal email</label><input type="email" class="form-control" name="personal_email" maxlength="190" value="' + MX.escape(v.personal_email || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Emergency contact</label><input class="form-control" name="emergency_contact_name" maxlength="160" value="' + MX.escape(v.emergency_contact_name || '') + '"></div>' +
        '<div class="col"><label class="form-label">Emergency phone</label><input class="form-control" name="emergency_contact_phone" maxlength="60" value="' + MX.escape(v.emergency_contact_phone || '') + '"></div></div>' +
        '</form>';
    MX.drawer.open({ title: 'Edit personal details', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveDetails()">Save</button>' });
}
function mxSaveDetails() {
    var form = document.getElementById('det-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/payroll/records/' + mxPersonId + '/details', MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxAddBeneficiary() {
    var body = '<form id="ben-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Relationship</label><input class="form-control" name="relationship" maxlength="80"></div>' +
        '<div class="col"><label class="form-label">Share percent</label><input type="number" step="0.01" min="0" max="100" class="form-control" name="share_percent"></div></div>' +
        '<div class="mb-3"><label class="form-label">Contact</label><input class="form-control" name="contact" maxlength="160"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_payroll_beneficiary" id="ben-pay"><label class="form-check-label" for="ben-pay">Payroll or benefits beneficiary</label></div>' +
        '</form>';
    MX.drawer.open({ title: 'Add beneficiary', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveBeneficiary()">Add</button>' });
}
function mxSaveBeneficiary() {
    var form = document.getElementById('ben-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/payroll/records/' + mxPersonId + '/beneficiaries', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Added.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteBeneficiary(id) {
    MX.confirm('Remove this beneficiary?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/payroll/beneficiaries/' + id).then(function () { MX.ok('Removed.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxAddBank() {
    var body = '<form id="bank-form">' +
        '<div class="mb-3"><label class="form-label">Bank name</label><input class="form-control" name="bank_name" required maxlength="120"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Branch</label><input class="form-control" name="branch" maxlength="120"></div>' +
        '<div class="col"><label class="form-label">Account name</label><input class="form-control" name="account_name" maxlength="160"></div></div>' +
        '<div class="mb-3"><label class="form-label">Account number</label><input class="form-control mx-mono" name="account_number" required maxlength="60"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_primary" id="bank-pri"><label class="form-check-label" for="bank-pri">Primary account for salary</label></div>' +
        '</form>';
    MX.drawer.open({ title: 'Add bank account', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveBank()">Add</button>' });
}
function mxSaveBank() {
    var form = document.getElementById('bank-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/payroll/records/' + mxPersonId + '/bank', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Added.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteBank(id) {
    MX.confirm('Remove this account?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/payroll/bank/' + id).then(function () { MX.ok('Removed.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}

function mxEditStructure() {
    var existing = {};
    mxStructureLines.forEach(function (l) { existing[l.component_id] = l; });
    var rows = mxComponents.map(function (c) {
        var l = existing[c.id] || {};
        var checked = existing[c.id] ? ' checked' : '';
        var hint = c.calc_method === 'Fixed Amount' ? 'amount' : (c.calc_method === 'Banded' ? 'auto (banded)' : 'rate %');
        var val = l.amount != null ? l.amount : (l.rate != null ? l.rate : (c.default_rate != null ? c.default_rate : ''));
        var disabled = c.calc_method === 'Banded' ? ' disabled' : '';
        return '<tr><td><label style="font-size:13px"><input type="checkbox" class="mx-sl-on" data-id="' + c.id + '"' + checked + '> ' + MX.escape(c.name) + '</label>' +
            '<div class="text-muted" style="font-size:11px">' + MX.escape(c.comp_type) + ' &middot; ' + MX.escape(c.calc_method) + '</div></td>' +
            '<td><input type="number" step="0.0001" class="form-control form-control-sm mx-sl-val" data-id="' + c.id + '" data-method="' + MX.escape(c.calc_method) + '" placeholder="' + hint + '" value="' + (c.calc_method === 'Banded' ? '' : val) + '"' + disabled + ' style="width:120px"></td></tr>';
    }).join('');
    var body = '<form id="struct-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Effective from</label><input type="date" class="form-control" name="effective_from" required value="' + (mxStructure.effective_from || MX.today()) + '"></div>' +
        '<div class="col"><label class="form-label">Basic salary</label><input type="number" step="0.01" min="0" class="form-control" name="basic_salary" required value="' + (mxStructure.basic_salary != null ? mxStructure.basic_salary : '') + '"></div></div>' +
        '<div class="mb-2"><label class="form-label">Components</label><table class="table table-sm align-middle"><thead><tr><th>Component</th><th>Amount or rate</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(mxStructure.notes || '') + '"></div>' +
        '<div class="form-text">Saving creates a new current structure and supersedes the previous one. Banded components (like PAYE) compute automatically at run time.</div>' +
        '</form>';
    MX.drawer.open({ title: 'Set salary structure', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveStructure()">Save structure</button>' });
}
function mxSaveStructure() {
    var form = document.getElementById('struct-form');
    if (!form.reportValidity()) return;
    var lines = [];
    document.querySelectorAll('.mx-sl-on:checked').forEach(function (cb) {
        var id = cb.dataset.id;
        var input = document.querySelector('.mx-sl-val[data-id="' + id + '"]');
        var method = input.dataset.method;
        var line = { component_id: parseInt(id, 10) };
        if (method === 'Fixed Amount') line.amount = input.value;
        else if (method !== 'Banded') line.rate = input.value;
        lines.push(line);
    });
    MX.api('POST', '/payroll/records/' + mxPersonId + '/structure', {
        effective_from: form.effective_from.value,
        basic_salary: form.basic_salary.value,
        notes: MX.clean(form.notes.value),
        lines: lines
    }).then(function () { MX.drawer.close(); MX.ok('Structure saved.'); setTimeout(function () { location.reload(); }, 500); })
      .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
