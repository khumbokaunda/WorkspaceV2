<?php // Attendance: check in control, personal heatmap, manager board and timesheet. ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 style="font-size:20px" class="mb-0">Attendance</h1>
</div>

<div class="row g-3">
<?php if ($personId): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Today</h2><span class="text-muted" style="font-size:12px">Late after <?= e($lateThreshold) ?></span></div>
            <div class="mx-card-body text-center">
<?php if ($today && $today['check_in'] && !$today['check_out']): ?>
                <div class="mb-2"><span class="mx-chip <?= $today['state'] === 'Late' ? 'mx-chip-warning' : 'mx-chip-success' ?>"><?= e($today['state']) ?></span></div>
                <p class="text-muted mb-3">Checked in at <strong class="mx-mono"><?= e(substr((string)$today['check_in'], 0, 5)) ?></strong> (<?= e($today['mode']) ?>)</p>
                <button class="btn btn-outline-primary w-100" onclick="mxCheckOut()"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Check out</button>
<?php elseif ($today && $today['check_out']): ?>
                <div class="mb-2"><span class="mx-chip <?= $today['state'] === 'Late' ? 'mx-chip-warning' : 'mx-chip-success' ?>"><?= e($today['state']) ?></span></div>
                <p class="text-muted mb-0">In <strong class="mx-mono"><?= e(substr((string)$today['check_in'], 0, 5)) ?></strong>, out <strong class="mx-mono"><?= e(substr((string)$today['check_out'], 0, 5)) ?></strong>.</p>
<?php else: ?>
                <p class="text-muted mb-3">You have not checked in today.</p>
                <div class="d-flex gap-2 justify-content-center mb-3" role="group" aria-label="Work mode">
                    <input type="radio" class="btn-check" name="att-mode" id="mode-onsite" value="On-site" checked>
                    <label class="btn btn-outline-primary btn-sm" for="mode-onsite">On-site</label>
                    <input type="radio" class="btn-check" name="att-mode" id="mode-remote" value="Remote">
                    <label class="btn btn-outline-primary btn-sm" for="mode-remote">Remote</label>
                </div>
                <button class="btn btn-primary w-100" onclick="mxCheckIn()"><i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Check in</button>
<?php endif; ?>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header">
                <h2>My month</h2>
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-subtle btn-sm" onclick="mxMonthShift(-1)" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
                    <span id="mx-cal-label" style="font-size:13px;font-weight:600;min-width:110px;text-align:center"></span>
                    <button class="btn btn-subtle btn-sm" onclick="mxMonthShift(1)" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="mx-card-body">
                <div class="mx-cal" id="mx-heatmap" aria-label="Attendance calendar"></div>
                <div class="d-flex gap-3 mt-3 flex-wrap" style="font-size:12px">
                    <span><span class="mx-chip mx-chip-success">Present</span></span>
                    <span><span class="mx-chip mx-chip-warning">Late</span></span>
                    <span><span class="mx-chip mx-chip-danger">Absent</span></span>
                    <span><span class="mx-chip mx-chip-info">Leave</span></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canSeeTeam): ?>
    <div class="col-12 col-lg-8">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Who is in today</h2></div>
            <div class="mx-card-body mx-flush" id="mx-board"><div class="p-3"><span class="mx-skeleton mb-2" style="width:70%"></span><span class="mx-skeleton mb-2" style="width:55%"></span><span class="mx-skeleton" style="width:65%"></span></div></div>
        </div>

        <div class="mx-card">
            <div class="mx-table-toolbar">
                <strong style="font-size:14px">Timesheet</strong>
                <input type="date" id="mx-ts-from" class="form-control form-control-sm" style="max-width:160px" value="<?= e(date('Y-m-01')) ?>" aria-label="From date">
                <input type="date" id="mx-ts-to" class="form-control form-control-sm" style="max-width:160px" value="<?= e(date('Y-m-d')) ?>" aria-label="To date">
                <button class="btn btn-outline-primary btn-sm" onclick="mxLoadTeam()">Apply</button>
                <div class="flex-grow-1"></div>
                <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxSheetTable, 'timesheet')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
            </div>
            <div class="mx-card-body mx-flush">
                <table id="mx-sheet" class="table mx-stack align-middle" style="width:100%">
                    <thead><tr><th>Date</th><th>Person</th><th>In</th><th>Out</th><th>Mode</th><th>State</th><?= $canCorrect ? '<th></th>' : '' ?></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
var mxCanCorrect = <?= $canCorrect ? 'true' : 'false' ?>;
var mxCalMonth = '<?= e(date('Y-m')) ?>';

function mxCheckIn() {
    var mode = document.querySelector('input[name="att-mode"]:checked');
    MX.api('POST', '/attendance/check-in', { mode: mode ? mode.value : 'On-site' })
        .then(function () { MX.ok('Checked in.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxCheckOut() {
    MX.api('POST', '/attendance/check-out', {})
        .then(function () { MX.ok('Checked out.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
}

// Personal heatmap.
function mxLoadHeatmap() {
    fetch('/api/attendance/me?month=' + mxCalMonth, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var box = document.getElementById('mx-heatmap');
            if (!box) return;
            var first = new Date(mxCalMonth + '-01T00:00:00');
            document.getElementById('mx-cal-label').textContent =
                first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            var daysInMonth = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
            var lead = (first.getDay() + 6) % 7;
            var today = MX.today();
            var html = ['Mo','Tu','We','Th','Fr','Sa','Su'].map(function (d) { return '<div class="mx-cal-head">' + d + '</div>'; }).join('');
            for (var i = 0; i < lead; i++) html += '<div></div>';
            for (var d = 1; d <= daysInMonth; d++) {
                var iso = mxCalMonth + '-' + String(d).padStart(2, '0');
                var info = data.days[iso];
                var cls = '', title = '';
                if (info) {
                    cls = info.state === 'Present' ? 'mx-present' : info.state === 'Late' ? 'mx-late' : info.state === 'Absent' ? 'mx-absent' : 'mx-leave';
                    title = info.state + (info.in ? ' in ' + info.in : '') + (info.out ? ' out ' + info.out : '');
                }
                if (iso === today) cls += ' mx-today';
                html += '<div class="mx-cal-day ' + cls + '" title="' + MX.escape(title) + '">' + d + '</div>';
            }
            box.innerHTML = html;
        });
}
function mxMonthShift(delta) {
    var d = new Date(mxCalMonth + '-01T00:00:00');
    d.setMonth(d.getMonth() + delta);
    mxCalMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    mxLoadHeatmap();
}

// Manager board and timesheet.
function mxLoadTeam() {
    var from = document.getElementById('mx-ts-from').value;
    var to = document.getElementById('mx-ts-to').value;
    fetch('/api/attendance/team?from=' + from + '&to=' + to, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var board = document.getElementById('mx-board');
            board.innerHTML = '<ul class="list-unstyled m-0">' + data.board.map(function (p) {
                var chip;
                if (p.on_leave > 0) chip = '<span class="mx-chip mx-chip-info">On leave</span>';
                else if (p.check_in && !p.check_out) chip = '<span class="mx-chip ' + (p.state === 'Late' ? 'mx-chip-warning' : 'mx-chip-success') + '">In (' + MX.escape(p.mode) + ')</span>';
                else if (p.check_out) chip = '<span class="mx-chip mx-chip-plain">Left ' + p.check_out.slice(0, 5) + '</span>';
                else chip = '<span class="mx-chip mx-chip-danger">Not in</span>';
                return '<li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">' +
                    '<span class="mx-avatar">' + MX.escape(p.first_name.charAt(0)) + '</span>' +
                    '<div class="flex-grow-1"><div style="font-size:13px">' + MX.escape(p.first_name + ' ' + p.last_name) + '</div>' +
                    '<small class="text-muted">' + MX.escape(p.department || '') + '</small></div>' +
                    (p.check_in ? '<code>' + p.check_in.slice(0, 5) + '</code>' : '') + chip + '</li>';
            }).join('') + '</ul>';

            var labels = ['Date', 'Person', 'In', 'Out', 'Mode', 'State', ''];
            var rows = data.sheet.map(function (r) {
                var chip = '<span class="mx-chip ' + (r.state === 'Present' ? 'mx-chip-success' : r.state === 'Late' ? 'mx-chip-warning' : 'mx-chip-danger') + '">' + r.state + '</span>';
                var row = [r.work_date, MX.escape(r.first_name + ' ' + r.last_name),
                    r.check_in ? r.check_in.slice(0, 5) : '', r.check_out ? r.check_out.slice(0, 5) : '',
                    MX.escape(r.mode), chip];
                if (mxCanCorrect) row.push('<button class="btn btn-subtle btn-sm" onclick=\'mxCorrect(' + JSON.stringify(r).replace(/'/g, "&#39;") + ')\' aria-label="Correct record"><i class="fa-solid fa-pen"></i></button>');
                return row;
            });
            if (window.mxSheetTable) { window.mxSheetTable.clear(); window.mxSheetTable.rows.add(rows).draw(); }
            else {
                window.mxSheetTable = MX.table('#mx-sheet', {
                    data: rows,
                    order: [[0, 'desc']],
                    columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
                });
            }
        });
}

function mxCorrect(r) {
    var body = '<form id="corr-form">' +
        '<p class="text-muted" style="font-size:13px">' + MX.escape(r.first_name + ' ' + r.last_name) + ', ' + r.work_date + '</p>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Check in</label><input type="time" class="form-control" name="check_in" value="' + (r.check_in ? r.check_in.slice(0, 5) : '') + '"></div>' +
        '<div class="col"><label class="form-label">Check out</label><input type="time" class="form-control" name="check_out" value="' + (r.check_out ? r.check_out.slice(0, 5) : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">State</label><select class="form-select" name="state">' +
        ['Present', 'Late', 'Absent'].map(function (s) { return '<option' + (r.state === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Mode</label><select class="form-select" name="mode">' +
        ['On-site', 'Remote'].map(function (s) { return '<option' + (r.mode === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note" maxlength="255" value="' + MX.escape(r.note || '') + '" placeholder="Reason for the correction"></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Correct attendance',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSaveCorrection(' + r.id + ')">Save correction</button>'
    });
}
function mxSaveCorrection(id) {
    var form = document.getElementById('corr-form');
    MX.api('PATCH', '/attendance/' + id, MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Corrected.'); mxLoadTeam(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('mx-heatmap')) mxLoadHeatmap();
    if (document.getElementById('mx-board')) mxLoadTeam();
});
</script>
