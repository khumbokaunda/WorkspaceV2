<?php
// Room and resource booking: the resources, and the upcoming bookings with
// double-booking prevented at save time.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Room Booking</h1>
    <div class="d-flex gap-2">
<?php if ($canManage): ?>
        <button class="btn btn-outline-primary" onclick="mxNewResource()"><i class="fa-solid fa-gear me-2"></i>Add resource</button>
<?php endif; ?>
        <button class="btn btn-primary" onclick="mxNewBooking()"><i class="fa-solid fa-plus me-2"></i>Book</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Resources</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$resources): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-door-open"></i><p class="mb-0">No resources yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($resources as $r): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($r['name']) ?></strong><?= (int)$r['is_active'] !== 1 ? ' <span class="mx-chip mx-chip-plain" style="font-size:10px">inactive</span>' : '' ?></div>
                            <small class="text-muted"><?= e($r['resource_type']) ?><?= $r['location'] ? ' &middot; ' . e($r['location']) : '' ?><?= $r['capacity'] ? ' &middot; seats ' . (int)$r['capacity'] : '' ?></small>
                        </div>
<?php if ($canManage): ?>
                        <button class="btn btn-subtle btn-sm" onclick='mxEditResource(<?= json_encode(["id"=>(int)$r["id"],"name"=>$r["name"],"resource_type"=>$r["resource_type"],"location"=>$r["location"],"capacity"=>$r["capacity"],"is_active"=>(int)$r["is_active"],"notes"=>$r["notes"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteResource(<?= (int)$r['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
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
            <div class="mx-card-header"><h2>Upcoming bookings</h2></div>
            <div class="mx-card-body mx-flush" id="mx-bookings-list">
                <div class="mx-empty py-4"><i class="fa-solid fa-calendar-check"></i><p class="mb-0">Loading...</p></div>
            </div>
        </div>
    </div>
</div>

<script>
var mxCanManageBookings = <?= $canManage ? 'true' : 'false' ?>;
var mxResourceTypes = <?= json_encode(array_values($resourceTypes)) ?>;
var mxResources = <?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'is_active' => (int)$r['is_active']], $resources)) ?>;

function mxLoadBookings() {
    fetch('/api/bookings', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (data) { mxRenderBookings(data.bookings || []); });
}
function mxRenderBookings(bookings) {
    var box = document.getElementById('mx-bookings-list');
    if (!bookings.length) { box.innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-calendar-check"></i><p class="mb-0">No upcoming bookings.</p></div>'; return; }
    box.innerHTML = '<ul class="list-unstyled m-0">' + bookings.map(function (b) {
        var canCancel = mxCanManageBookings || b.is_mine;
        var range = b.start_at.slice(0, 16) + ' to ' + b.end_at.slice(11, 16);
        return '<li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">' +
            '<div class="flex-grow-1"><div style="font-size:13px"><strong>' + MX.escape(b.resource_name) + '</strong> <span class="text-muted">' + MX.escape(b.title) + '</span></div>' +
            '<small class="text-muted mx-tabular">' + MX.escape(range) + ' &middot; ' + MX.escape(b.booker || '') + '</small></div>' +
            (canCancel ? '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxCancelBooking(' + b.id + ')" aria-label="Cancel"><i class="fa-regular fa-trash-can"></i></button>' : '') +
            '</li>';
    }).join('') + '</ul>';
}
function mxNewBooking() {
    var active = mxResources.filter(function (r) { return r.is_active === 1; });
    if (!active.length) { MX.fail('No active resources to book.'); return; }
    var opts = active.map(function (r) { return '<option value="' + r.id + '">' + MX.escape(r.name) + '</option>'; }).join('');
    var body = '<form id="booking-form">' +
        '<div class="mb-3"><label class="form-label">Resource</label><select class="form-select" name="resource_id" required>' + opts + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="200" placeholder="e.g. Project kickoff"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Start</label><input type="datetime-local" class="form-control" name="start_at" required></div>' +
        '<div class="col"><label class="form-label">End</label><input type="datetime-local" class="form-control" name="end_at" required></div></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500"></div>' +
        '</form>';
    MX.drawer.open({ title: 'Book a resource', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitBooking()">Book</button>' });
}
function mxSubmitBooking() {
    var form = document.getElementById('booking-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/bookings', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Booked.'); mxLoadBookings(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxCancelBooking(id) {
    MX.confirm('Cancel this booking?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/bookings/' + id).then(function () { MX.ok('Cancelled.'); mxLoadBookings(); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxResourceForm(r) {
    r = r || {};
    var typeOpts = mxResourceTypes.map(function (t) { return '<option' + ((r.resource_type || 'Room') === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('');
    return '<form id="resource-form">' + (r.id ? '<input type="hidden" name="id" value="' + r.id + '">' : '') +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160" value="' + MX.escape(r.name || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Type</label><select class="form-select" name="resource_type">' + typeOpts + '</select></div>' +
        '<div class="col"><label class="form-label">Capacity</label><input type="number" min="0" class="form-control" name="capacity" value="' + (r.capacity || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Location</label><input class="form-control" name="location" maxlength="160" value="' + MX.escape(r.location || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(r.notes || '') + '"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="res-active"' + (!r.id || r.is_active ? ' checked' : '') + '><label class="form-check-label" for="res-active">Active and bookable</label></div>' +
        '</form>';
}
function mxNewResource() { MX.drawer.open({ title: 'Add resource', body: mxResourceForm(null), footer: mxResFoot() }); }
function mxEditResource(r) { MX.drawer.open({ title: 'Edit resource', body: mxResourceForm(r), footer: mxResFoot() }); }
function mxResFoot() { return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveResource()">Save</button>'; }
function mxSaveResource() {
    var form = document.getElementById('resource-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/bookings/resources', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteResource(id) {
    MX.confirm('Delete this resource?', 'Its bookings are removed too.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/bookings/resources/' + id).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxLoadBookings);
</script>
