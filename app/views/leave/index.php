<?php // Leave: balances, request drawer with live balance, approvals queue, team calendar, history. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Leave</h1>
<?php if ($personId): ?>
    <button class="btn btn-primary" onclick="mxRequestLeave()"><i class="fa-solid fa-plus me-2"></i>Request leave</button>
<?php endif; ?>
</div>

<?php if ($balances): ?>
<div class="row g-3 mb-3">
<?php foreach ($balances as $b): if ($b['allocated'] <= 0 && $b['used'] <= 0) continue; ?>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="mx-card mx-card-body p-3">
            <div class="mx-stat">
                <span class="mx-stat-value" style="font-size:20px"><?= e(rtrim(rtrim(number_format($b['remaining'], 1), '0'), '.')) ?></span>
                <span class="mx-stat-label"><?= e($b['name']) ?> left of <?= e(rtrim(rtrim(number_format($b['allocated'], 1), '0'), '.')) ?></span>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
<?php if ($canApprove): ?>
    <div class="col-12 col-lg-5">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Approvals queue</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$pending): ?>
                <div class="mx-empty py-4"><i class="fa-regular fa-thumbs-up"></i><p class="mb-0">No pending requests.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($pending as $p): ?>
                    <li class="px-3 py-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="mx-avatar"><?= e(strtoupper(mb_substr($p['first_name'], 0, 1))) ?></span>
                            <div class="flex-grow-1">
                                <div style="font-size:13px"><strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong>, <?= e($p['type_name']) ?></div>
                                <small class="text-muted mx-tabular"><?= e($p['start_date']) ?> to <?= e($p['end_date']) ?> (<?= e(rtrim(rtrim((string)$p['working_days'], '0'), '.')) ?> working days)</small>
<?php if ($p['reason']): ?>
                                <div class="text-muted" style="font-size:12px">"<?= e($p['reason']) ?>"</div>
<?php endif; ?>
                            </div>
                            <button class="btn btn-outline-primary btn-sm" onclick="mxReview(<?= (int)$p['id'] ?>, 'approve')" aria-label="Approve"><i class="fa-solid fa-check"></i></button>
                            <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxReview(<?= (int)$p['id'] ?>, 'reject')" aria-label="Reject"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

    <div class="<?= $canApprove ? 'col-12 col-lg-7' : 'col-12 col-lg-7' ?>">
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Team calendar</h2>
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-subtle btn-sm" onclick="mxCalShift(-1)" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
                    <span id="mx-lcal-label" style="font-size:13px;font-weight:600;min-width:110px;text-align:center"></span>
                    <button class="btn btn-subtle btn-sm" onclick="mxCalShift(1)" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="mx-card-body" id="mx-leave-cal"></div>
        </div>
    </div>

    <div class="col-12">
        <div class="mx-card">
            <div class="mx-table-toolbar">
                <strong style="font-size:14px">Requests</strong>
<?php if ($canSeeAll): ?>
                <select id="mx-leave-scope" class="form-select form-select-sm" style="max-width:160px" aria-label="Scope">
                    <option value="mine">My requests</option>
                    <option value="all">Everyone</option>
                </select>
<?php endif; ?>
                <div class="flex-grow-1"></div>
                <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxLeaveTable, 'leave')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
            </div>
            <div class="mx-card-body mx-flush">
                <table id="mx-leave-table" class="table dt-host mx-stack align-middle" style="width:100%">
                    <thead><tr><th>Person</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Review</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
var mxLeaveTypes = <?= json_encode(array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name']], $leaveTypes)) ?>;
var mxMyPersonId = <?= (int)($personId ?? 0) ?>;
var mxLcalMonth = '<?= e(date('Y-m')) ?>';

function mxRequestLeave() {
    var body = '<form id="lv-form">' +
        '<div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="leave_type_id" id="lv-type">' +
        mxLeaveTypes.map(function (t) { return '<option value="' + t.id + '">' + MX.escape(t.name) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="row g-2 mb-2"><div class="col"><label class="form-label">First day</label><input type="date" class="form-control" name="start_date" id="lv-start" required min="' + MX.today() + '"></div>' +
        '<div class="col"><label class="form-label">Last day</label><input type="date" class="form-control" name="end_date" id="lv-end" required></div></div>' +
        '<p class="text-muted mb-3" style="font-size:12px" id="lv-balance">Remaining balance loads when you pick a type.</p>' +
        '<div class="mb-3"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="2" maxlength="500"></textarea></div>' +
        '<div id="lv-team" class="mb-2"></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Request leave',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitLeave()">Submit request</button>'
    });
    document.getElementById('lv-type').addEventListener('change', mxLoadBalance);
    document.getElementById('lv-start').addEventListener('change', mxTeamAway);
    document.getElementById('lv-end').addEventListener('change', mxTeamAway);
    mxLoadBalance();
}

function mxLoadBalance() {
    var typeId = document.getElementById('lv-type').value;
    fetch('/api/leave/balance?type_id=' + typeId, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) document.getElementById('lv-balance').innerHTML =
                'Remaining balance: <strong>' + d.remaining + '</strong> of ' + d.allocated + ' days this year.';
        });
}

// Availability preview: who is already away in the chosen window.
function mxTeamAway() {
    var s = document.getElementById('lv-start').value, e = document.getElementById('lv-end').value;
    if (!s || !e || e < s) return;
    fetch('/api/leave/calendar?from=' + s + '&to=' + e, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var others = d.items.filter(function (i) { return i.person_id != mxMyPersonId && i.status === 'Approved'; });
            var box = document.getElementById('lv-team');
            if (!others.length) { box.innerHTML = '<p class="text-muted" style="font-size:12px"><i class="fa-solid fa-circle-check me-1" style="color:var(--mx-success)"></i>No teammates are away in that window.</p>'; return; }
            box.innerHTML = '<p style="font-size:12px;color:var(--mx-warning)"><i class="fa-solid fa-triangle-exclamation me-1"></i>Already away then:</p><ul class="list-unstyled" style="font-size:12px">' +
                others.map(function (i) { return '<li>' + MX.escape(i.first_name + ' ' + i.last_name) + ' (' + i.start_date + ' to ' + i.end_date + ')</li>'; }).join('') + '</ul>';
        });
}

function mxSubmitLeave() {
    var form = document.getElementById('lv-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/leave', MX.formData(form))
        .then(function (d) { MX.drawer.close(); MX.ok('Request submitted (' + d.working_days + ' working days).'); setTimeout(function () { location.reload(); }, 800); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxReview(id, action) {
    Swal.fire({
        title: action === 'approve' ? 'Approve this request?' : 'Reject this request?',
        input: 'text',
        inputPlaceholder: 'Review note (optional)',
        showCancelButton: true,
        confirmButtonText: action === 'approve' ? 'Approve' : 'Reject',
        reverseButtons: true
    }).then(function (r) {
        if (!r.isConfirmed) return;
        MX.api('POST', '/leave/' + id + '/' + action, { review_note: MX.clean(r.value || '') })
            .then(function () { MX.ok('Done. The requester has been notified.'); setTimeout(function () { location.reload(); }, 700); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

function mxCancelLeave(id) {
    MX.confirm('Cancel this leave request?', 'An approved request returns its days to your balance.', 'Cancel request')
        .then(function (go) {
            if (!go) return;
            MX.api('POST', '/leave/' + id + '/cancel', {})
                .then(function () { MX.ok('Cancelled.'); setTimeout(function () { location.reload(); }, 700); })
                .catch(function (e) { MX.fail(e.message); });
        });
}

// Month calendar of absences.
function mxLoadLeaveCal() {
    var first = mxLcalMonth + '-01';
    var d = new Date(first + 'T00:00:00');
    var last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    var lastIso = MX.isoDate(last);
    document.getElementById('mx-lcal-label').textContent = d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    fetch('/api/leave/calendar?from=' + first + '&to=' + lastIso, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var byDay = {};
            data.items.forEach(function (i) {
                var s = i.start_date < first ? first : i.start_date;
                var e = i.end_date > lastIso ? lastIso : i.end_date;
                var cur = new Date(s + 'T00:00:00');
                var end = new Date(e + 'T00:00:00');
                while (cur <= end) {
                    var iso = MX.isoDate(cur);
                    (byDay[iso] = byDay[iso] || []).push(i);
                    cur.setDate(cur.getDate() + 1);
                }
            });
            var lead = (d.getDay() + 6) % 7;
            var html = ['Mo','Tu','We','Th','Fr','Sa','Su'].map(function (x) { return '<div class="mx-cal-head">' + x + '</div>'; }).join('');
            for (var i = 0; i < lead; i++) html += '<div></div>';
            var today = MX.today();
            for (var day = 1; day <= last.getDate(); day++) {
                var iso = mxLcalMonth + '-' + String(day).padStart(2, '0');
                var who = byDay[iso] || [];
                var cls = who.length ? 'mx-leave' : '';
                if (iso === today) cls += ' mx-today';
                var title = who.map(function (w) { return w.first_name + ' ' + w.last_name + (w.status === 'Pending' ? ' (pending)' : ''); }).join(', ');
                html += '<div class="mx-cal-day ' + cls + '" title="' + MX.escape(title) + '">' + day + (who.length ? '<span style="position:absolute;bottom:2px;right:4px;font-size:9px">' + who.length + '</span>' : '') + '</div>';
            }
            document.getElementById('mx-leave-cal').innerHTML = '<div class="mx-cal">' + html + '</div>';
        });
}
function mxCalShift(delta) {
    var d = new Date(mxLcalMonth + '-01T00:00:00');
    d.setMonth(d.getMonth() + delta);
    mxLcalMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    mxLoadLeaveCal();
}

// Requests table.
function mxLoadRequests(scope) {
    fetch('/api/leave/list' + (scope === 'all' ? '?scope=all' : ''), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var labels = ['Person', 'Type', 'From', 'To', 'Days', 'Status', 'Review', ''];
            var rows = data.requests.map(function (r) {
                var chip = { Approved: 'mx-chip-success', Pending: 'mx-chip-warning', Rejected: 'mx-chip-danger', Cancelled: 'mx-chip-plain' }[r.status];
                var actions = '';
                if (r.person_id == mxMyPersonId && (r.status === 'Pending' || (r.status === 'Approved' && r.start_date > MX.today()))) {
                    actions = '<button class="btn btn-subtle btn-sm" onclick="mxCancelLeave(' + r.id + ')" aria-label="Cancel request"><i class="fa-regular fa-circle-xmark"></i></button>';
                }
                return [
                    MX.escape(r.first_name + ' ' + r.last_name),
                    MX.escape(r.type_name),
                    r.start_date, r.end_date,
                    String(parseFloat(r.working_days)),
                    '<span class="mx-chip ' + chip + '">' + r.status + '</span>',
                    MX.escape((r.reviewer || '') + (r.review_note ? ': ' + r.review_note : '')),
                    actions
                ];
            });
            if (window.mxLeaveTable) { window.mxLeaveTable.clear(); window.mxLeaveTable.rows.add(rows).draw(); }
            else {
                window.mxLeaveTable = MX.table('#mx-leave-table', {
                    data: rows,
                    order: [[2, 'desc']],
                    columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
                });
            }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    mxLoadLeaveCal();
    mxLoadRequests('mine');
    var scope = document.getElementById('mx-leave-scope');
    if (scope) scope.addEventListener('change', function () { mxLoadRequests(this.value); });
    if (new URLSearchParams(location.search).get('action') === 'new' && mxMyPersonId) mxRequestLeave();
});
</script>
