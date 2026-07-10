<?php
// Client detail: identity, named contacts, and this client's opportunities.
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-center gap-3 flex-wrap">
        <span class="mx-avatar mx-avatar-lg"><i class="fa-solid fa-handshake"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($client['name']) ?></h1>
                <span class="mx-chip mx-chip-plain"><?= e($client['client_type']) ?></span>
            </div>
            <div class="text-muted mt-1"><?= e($client['sector'] ?: 'No sector recorded') ?></div>
        </div>
<?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="mxEditClient()"><i class="fa-solid fa-pen me-2"></i>Edit</button>
            <button class="btn btn-subtle" style="color:var(--mx-danger)" onclick="mxDeleteClient()"><i class="fa-regular fa-trash-can me-2"></i>Delete</button>
        </div>
<?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Contact</h2></div>
            <div class="mx-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-4 text-muted fw-normal">Main contact</dt><dd class="col-8"><?= e($client['main_contact'] ?: 'Not set') ?></dd>
                    <dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8"><?= e($client['phone'] ?: 'Not set') ?></dd>
                    <dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8"><?= $client['email'] ? '<a href="mailto:' . e($client['email']) . '">' . e($client['email']) . '</a>' : 'Not set' ?></dd>
                    <dt class="col-4 text-muted fw-normal">Address</dt><dd class="col-8 mb-0" style="white-space:pre-line"><?= e($client['address'] ?: 'Not set') ?></dd>
                </dl>
<?php if (!empty($client['notes'])): ?>
                <hr>
                <p class="mb-0" style="font-size:13px;white-space:pre-line"><?= e($client['notes']) ?></p>
<?php endif; ?>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Contacts</h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxAddContact()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$contacts): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-address-book"></i><p class="mb-0">No named contacts yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($contacts as $ct): ?>
                    <li class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($ct['name']) ?></strong><?= $ct['title'] ? ' <span class="text-muted">' . e($ct['title']) . '</span>' : '' ?></div>
                            <small class="text-muted"><?= e(trim(($ct['phone'] ?? '') . ($ct['phone'] && $ct['email'] ? ' · ' : '') . ($ct['email'] ?? ''))) ?></small>
                        </div>
<?php if ($canManage): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteContact(<?= (int)$ct['id'] ?>)" aria-label="Delete contact"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Opportunities</h2>
                <a href="/clients" class="btn btn-subtle btn-sm">All clients</a>
            </div>
            <div class="mx-card-body mx-flush" id="mx-copp-list">
                <div class="mx-empty py-4"><i class="fa-solid fa-bullseye"></i><p class="mb-0">Loading...</p></div>
            </div>
        </div>
    </div>
</div>

<script>
var mxClient = <?= json_encode([
    'id' => (int)$client['id'], 'name' => $client['name'], 'client_type' => $client['client_type'],
    'sector' => $client['sector'], 'main_contact' => $client['main_contact'], 'phone' => $client['phone'],
    'email' => $client['email'], 'address' => $client['address'], 'notes' => $client['notes'],
]) ?>;
var mxClientTypes = <?= json_encode(array_values(client_types())) ?>;
var mxStages = <?= json_encode(array_values($stages)) ?>;
var mxClientsCurrency = <?= json_encode($currency) ?>;
var mxCanManageClients = <?= $canManage ? 'true' : 'false' ?>;

function mxMoney(value, currency) {
    if (value === null || value === '' || value === undefined) return '';
    return (currency || '') + ' ' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function mxStageChip(s) {
    var map = { Lead: 'mx-chip-plain', Qualifying: 'mx-chip-info', Bidding: 'mx-chip-warning', Won: 'mx-chip-success', Lost: 'mx-chip-danger' };
    return '<span class="mx-chip ' + (map[s] || 'mx-chip-plain') + '">' + MX.escape(s) + '</span>';
}

function mxLoadClientOpps() {
    fetch('/api/opportunities?client_id=' + mxClient.id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var box = document.getElementById('mx-copp-list');
            var opps = data.opportunities || [];
            if (!opps.length) {
                box.innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-bullseye"></i><p class="mb-0">No opportunities recorded for this client.</p></div>';
                return;
            }
            box.innerHTML = '<ul class="list-unstyled m-0">' + opps.map(function (o) {
                return '<li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">' +
                    '<div class="flex-grow-1"><div style="font-size:13px"><strong>' + MX.escape(o.title) + '</strong></div>' +
                    '<small class="text-muted">' + MX.escape(mxMoney(o.estimated_value, o.currency || mxClientsCurrency)) +
                    (o.expected_decision_date ? ' · decision ' + MX.escape(o.expected_decision_date) : '') + '</small></div>' +
                    mxStageChip(o.stage) + '</li>';
            }).join('') + '</ul>';
        });
}

function mxEditClient() {
    var c = mxClient;
    var body = '<form id="client-edit-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="200" value="' + MX.escape(c.name) + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Type</label><select class="form-select" name="client_type">' +
        mxClientTypes.map(function (t) { return '<option' + (c.client_type === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select></div><div class="col"><label class="form-label">Sector</label><input class="form-control" name="sector" maxlength="120" value="' + MX.escape(c.sector || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Main contact</label><input class="form-control" name="main_contact" maxlength="160" value="' + MX.escape(c.main_contact || '') + '"></div>' +
        '<div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="60" value="' + MX.escape(c.phone || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" maxlength="190" value="' + MX.escape(c.email || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2" maxlength="400">' + MX.escape(c.address || '') + '</textarea></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" maxlength="2000">' + MX.escape(c.notes || '') + '</textarea></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Edit client', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSaveClient()">Save changes</button>'
    });
}
function mxSaveClient() {
    var form = document.getElementById('client-edit-form');
    if (!form.reportValidity()) return;
    MX.api('PATCH', '/clients/' + mxClient.id, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteClient() {
    MX.confirm('Delete this client?', 'Contacts are removed. Opportunities and tenders keep their history but lose the client link.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/clients/' + mxClient.id)
            .then(function () { MX.ok('Deleted.'); setTimeout(function () { location.href = '/clients'; }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}
function mxAddContact() {
    var body = '<form id="contact-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160"></div>' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="120"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="60"></div>' +
        '<div class="col"><label class="form-label">Email</label><input type="email" class="form-control" name="email" maxlength="190"></div></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="400"></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Add contact', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSaveContact()">Add</button>'
    });
}
function mxSaveContact() {
    var form = document.getElementById('contact-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/clients/' + mxClient.id + '/contacts', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Added.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteContact(id) {
    MX.confirm('Remove this contact?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/clients/contacts/' + id)
            .then(function () { MX.ok('Removed.'); setTimeout(function () { location.reload(); }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxLoadClientOpps);
</script>
