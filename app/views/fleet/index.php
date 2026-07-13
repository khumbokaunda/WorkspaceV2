<?php
// Fleet: vehicles with expiry chips for service, insurance and license, and a
// service log per vehicle.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Fleet</h1>
<?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="mxNewVehicle()"><i class="fa-solid fa-plus me-2"></i>Add vehicle</button>
<?php endif; ?>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2>Vehicles</h2></div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search registration, make, driver" aria-label="Search vehicles">
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxFleetTable, 'vehicles')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-fleet-table" class="table dt-host mx-stack align-middle" style="width:100%">
            <thead><tr><th>Registration</th><th>Vehicle</th><th>Driver</th><th>Service</th><th>Insurance</th><th>License</th><th>Status</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-fleet-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-car"></i><p>No vehicles yet.</p>
<?php if ($canManage): ?>
            <button class="btn btn-primary" onclick="mxNewVehicle()">Add the first one</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanManageFleet = <?= $canManage ? 'true' : 'false' ?>;
var mxFleetStatuses = <?= json_encode(array_values($statuses)) ?>;
var mxFleetLogTypes = <?= json_encode(array_values($logTypes)) ?>;
var mxFleetPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;
var mxFleetCurrency = <?= json_encode($currency) ?>;
var mxVehicles = [];

function mxExpCell(date, state) {
    if (!date) return '';
    var map = { Valid: 'inherit', Expiring: 'var(--mx-warning)', Expired: 'var(--mx-danger)', None: 'inherit' };
    return '<span class="mx-tabular" style="color:' + (map[state] || 'inherit') + '">' + date + '</span>';
}
function mxLoadFleet() {
    fetch('/api/fleet', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (data) { mxVehicles = data.vehicles || []; mxRenderFleet(); });
}
function mxRenderFleet() {
    var labels = ['Registration', 'Vehicle', 'Driver', 'Service', 'Insurance', 'License', 'Status', ''];
    var rows = mxVehicles.map(function (v) {
        var actions = '<button class="btn btn-subtle btn-sm" onclick="mxVehicleDetail(' + v.id + ')" aria-label="Detail"><i class="fa-solid fa-clipboard-list"></i></button>';
        if (mxCanManageFleet) actions += '<button class="btn btn-subtle btn-sm" onclick="mxEditVehicle(' + v.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
            '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteVehicle(' + v.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>';
        return [
            '<span class="mx-mono">' + MX.escape(v.registration) + '</span>',
            MX.escape([v.make, v.model, v.year].filter(Boolean).join(' ')),
            MX.escape(v.driver || ''),
            mxExpCell(v.service_due, v.service_state),
            mxExpCell(v.insurance_expiry, v.insurance_state),
            mxExpCell(v.license_expiry, v.license_state),
            '<span class="mx-chip ' + (v.status === 'Active' ? 'mx-chip-success' : v.status === 'Retired' ? 'mx-chip-plain' : 'mx-chip-warning') + '">' + MX.escape(v.status) + '</span>',
            actions
        ];
    });
    if (window.mxFleetTable) { window.mxFleetTable.clear(); window.mxFleetTable.rows.add(rows).draw(); }
    else window.mxFleetTable = MX.table('#mx-fleet-table', { data: rows, columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }] });
    document.getElementById('mx-fleet-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-fleet-table').style.display = rows.length ? '' : 'none';
}
function mxVehicleForm(v) {
    v = v || {};
    var driverOpts = '<option value="">Unassigned</option>' + mxFleetPeople.map(function (p) { return '<option value="' + p.id + '"' + (v.assigned_to == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('');
    var statusOpts = mxFleetStatuses.map(function (s) { return '<option' + ((v.status || 'Active') === s ? ' selected' : '') + '>' + MX.escape(s) + '</option>'; }).join('');
    return '<form id="vehicle-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Registration</label><input class="form-control mx-mono" name="registration" required maxlength="40" value="' + MX.escape(v.registration || '') + '"></div>' +
        '<div class="col"><label class="form-label">Status</label><select class="form-select" name="status">' + statusOpts + '</select></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Make</label><input class="form-control" name="make" maxlength="80" value="' + MX.escape(v.make || '') + '"></div>' +
        '<div class="col"><label class="form-label">Model</label><input class="form-control" name="model" maxlength="80" value="' + MX.escape(v.model || '') + '"></div>' +
        '<div class="col-3"><label class="form-label">Year</label><input type="number" min="1950" max="2100" class="form-control" name="year" value="' + (v.year || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Assigned driver</label><select class="form-select" name="assigned_to">' + driverOpts + '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Service due</label><input type="date" class="form-control" name="service_due" value="' + (v.service_due || '') + '"></div>' +
        '<div class="col"><label class="form-label">Insurance expiry</label><input type="date" class="form-control" name="insurance_expiry" value="' + (v.insurance_expiry || '') + '"></div>' +
        '<div class="col"><label class="form-label">License expiry</label><input type="date" class="form-control" name="license_expiry" value="' + (v.license_expiry || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(v.notes || '') + '"></div>' +
        '</form>';
}
function mxNewVehicle() { MX.drawer.open({ title: 'Add vehicle', body: mxVehicleForm(null), footer: mxFleetFoot('mxSubmitVehicle(null)') }); }
function mxEditVehicle(id) { var v = mxVehicles.find(function (x) { return x.id == id; }); if (v) MX.drawer.open({ title: 'Edit vehicle', body: mxVehicleForm(v), footer: mxFleetFoot('mxSubmitVehicle(' + id + ')') }); }
function mxFleetFoot(onclick) { return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="' + onclick + '">Save</button>'; }
function mxSubmitVehicle(id) {
    var form = document.getElementById('vehicle-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/fleet/' + id, MX.formData(form)) : MX.api('POST', '/fleet', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok('Saved.'); mxLoadFleet(); }).catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteVehicle(id) {
    MX.confirm('Delete this vehicle?', 'Its service log is removed too.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/fleet/' + id).then(function () { MX.ok('Deleted.'); mxLoadFleet(); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxVehicleDetail(id) {
    fetch('/api/fleet/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (data) {
            var v = data.vehicle, logs = data.logs || [];
            var logRows = logs.length ? logs.map(function (l) {
                return '<li class="d-flex gap-2 px-0 py-1 border-bottom" style="font-size:12px"><span class="mx-tabular">' + l.log_date + '</span><span class="mx-chip mx-chip-plain" style="font-size:10px">' + MX.escape(l.log_type) + '</span><span class="flex-grow-1">' + MX.escape(l.description || '') + '</span>' + (l.cost ? '<span class="mx-tabular">' + MX.escape(mxFleetCurrency) + ' ' + Number(l.cost).toLocaleString() + '</span>' : '') + '</li>';
            }).join('') : '<li class="text-muted" style="font-size:12px">No service log entries.</li>';
            var logForm = mxCanManageFleet ? '<hr><div style="font-size:13px" class="mb-2"><strong>Add log entry</strong></div><form id="vlog-form">' +
                '<div class="row g-2 mb-2"><div class="col"><select class="form-select form-select-sm" name="log_type">' + mxFleetLogTypes.map(function (t) { return '<option>' + MX.escape(t) + '</option>'; }).join('') + '</select></div>' +
                '<div class="col"><input type="date" class="form-control form-control-sm" name="log_date" value="' + MX.today() + '"></div></div>' +
                '<div class="row g-2 mb-2"><div class="col"><input type="number" class="form-control form-control-sm" name="odometer" placeholder="Odometer"></div>' +
                '<div class="col"><input type="number" step="0.01" class="form-control form-control-sm" name="cost" placeholder="Cost"></div></div>' +
                '<input class="form-control form-control-sm mb-2" name="description" placeholder="Description" maxlength="500">' +
                '<button type="button" class="btn btn-primary btn-sm" onclick="mxSubmitLog(' + id + ')">Add entry</button></form>' : '';
            MX.drawer.open({
                title: v.registration + ' log',
                body: '<div class="text-muted mb-2" style="font-size:13px">' + MX.escape([v.make, v.model].filter(Boolean).join(' ')) + '</div><ul class="list-unstyled m-0">' + logRows + '</ul>' + logForm,
                footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Close</button>'
            });
        });
}
function mxSubmitLog(vehicleId) {
    var form = document.getElementById('vlog-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/fleet/' + vehicleId + '/logs', MX.formData(form))
        .then(function () { MX.ok('Logged.'); mxVehicleDetail(vehicleId); }).catch(function (e) { MX.fail(e.message); });
}

document.addEventListener('DOMContentLoaded', mxLoadFleet);
</script>
