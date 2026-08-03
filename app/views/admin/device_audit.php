<?php
// Admin: device audit. Three panels: shared-device flags (the headline),
// per-user device history, and a recent-events table. The copy is factual and
// neutral throughout: it describes patterns and recommends a look, and never
// asserts wrongdoing.
function device_span_text(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' seconds';
    }
    if ($seconds < 3600) {
        return round($seconds / 60) . ' minutes';
    }
    if ($seconds < 86400) {
        $h = round($seconds / 3600, 1);
        return rtrim(rtrim((string)$h, '0'), '.') . ' hours';
    }
    return round($seconds / 86400) . ' days';
}
?>
<h1 style="font-size:20px" class="mb-2">Device Audit</h1>

<div class="mx-card mb-3" style="border-left:3px solid var(--mx-info)">
    <div class="mx-card-body d-flex align-items-start gap-3">
        <i class="fa-solid fa-circle-info" style="font-size:20px;color:var(--mx-info)"></i>
        <div style="font-size:13px">
            <p class="mb-1">This area shows patterns to review, not proof of anything. A device fingerprint records what device an action came from. It does not prove who was holding it.</p>
            <p class="text-muted mb-0">Fingerprints are approximate: similar phones look alike, and one person switching browsers looks different. A shared network or office wifi is not flagged here; only a shared device identity across accounts is. Treat every flag as a prompt to look, and act through a conversation and normal management.</p>
        </div>
    </div>
</div>

<ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-flags" type="button" role="tab">Shared-device flags</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-user" type="button" role="tab">Device history by person</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-events" type="button" role="tab">Recent events</button></li>
</ul>

<div class="tab-content">
    <!-- 6.1 Shared-device flags -->
    <div class="tab-pane fade show active" id="pane-flags" role="tabpanel">
<?php if (!$clusters): ?>
        <div class="mx-card"><div class="mx-card-body"><div class="mx-empty py-4"><i class="fa-solid fa-shield-halved"></i><p class="mb-0">No shared-device patterns in the last <?= (int)DEVICE_AUDIT_LOOKBACK_DAYS ?> days. Nothing to review.</p></div></div></div>
<?php else: ?>
<?php foreach ($clusters as $c): ?>
        <div class="mx-card mb-3" style="border-left:3px solid var(--mx-warning)">
            <div class="mx-card-body">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 style="font-size:15px" class="mb-1">One device checked in <?= (int)$c['user_count'] ?> accounts within <?= e(device_span_text((int)$c['span_seconds'])) ?>. Review recommended.</h2>
                        <div class="text-muted" style="font-size:12px">
                            <?= e(date('j M Y', strtotime($c['day']))) ?>,
                            <?= e(date('H:i', strtotime($c['first_at']))) ?> to <?= e(date('H:i', strtotime($c['last_at']))) ?>
                            &middot; <span class="mx-chip <?= $c['confidence'] === 'strong' ? 'mx-chip-info' : 'mx-chip-plain' ?>"><?= e($c['confidence']) ?> fingerprint</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div><code style="font-size:11px" title="<?= e($c['device_hash']) ?>"><?= e(substr($c['device_hash'], 0, 16)) ?></code></div>
                        <button class="btn btn-subtle btn-sm mt-1" onclick="mxLabelDevice('<?= e($c['device_hash']) ?>', this)"><i class="fa-solid fa-tag me-1"></i><span class="mx-dev-label"><?= $c['label'] !== null ? e($c['label']) : 'Name device' ?></span></button>
                    </div>
                </div>
                <div class="mt-2 p-2" style="background:var(--mx-bg);border:1px solid var(--mx-border);border-radius:8px;font-size:12px">
                    <div class="text-muted" style="word-break:break-all"><strong>Device:</strong> <?= e($c['platform'] ?: 'unknown platform') ?> &middot; <?= e($c['screen'] ?: 'unknown screen') ?> &middot; <?= e($c['language'] ?: '') ?></div>
                    <div class="text-muted" style="word-break:break-all"><?= e($c['user_agent'] ?: '') ?></div>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table align-middle mb-0" style="font-size:13px">
                        <thead><tr><th>Account</th><th>Person</th><th>Checked in</th></tr></thead>
                        <tbody>
<?php foreach ($c['members'] as $m): ?>
                            <tr>
                                <td><strong><?= e($m['username']) ?></strong></td>
                                <td><?= e(trim((string)$m['name'])) ?: '<span class="text-muted">Not linked</span>' ?></td>
                                <td class="mx-tabular"><?= e(date('H:i:s', strtotime((string)$m['at']))) ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endforeach; ?>
<?php endif; ?>
    </div>

    <!-- 6.2 Device history by person -->
    <div class="tab-pane fade" id="pane-user" role="tabpanel">
        <div class="mx-card">
            <div class="mx-table-toolbar">
                <select id="dev-user" class="form-select form-select-sm" style="max-width:280px" aria-label="Choose a person">
                    <option value="">Choose a person</option>
<?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= e($u['username'] . (trim((string)$u['name']) !== '' ? ' (' . trim((string)$u['name']) . ')' : '')) ?></option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="mx-card-body" id="dev-user-body">
                <div class="mx-empty py-4"><i class="fa-solid fa-user-shield"></i><p class="mb-0">Pick a person to see every device their account has acted from, and how many other accounts each device has served.</p></div>
            </div>
        </div>
    </div>

    <!-- 6.3 Recent events -->
    <div class="tab-pane fade" id="pane-events" role="tabpanel">
        <div class="mx-card">
            <div class="mx-table-toolbar">
                <select id="ev-type" class="form-select form-select-sm" style="max-width:150px" aria-label="Filter by type">
                    <option value="">All types</option>
                    <option value="login">Login</option>
                    <option value="check_in">Check in</option>
                    <option value="check_out">Check out</option>
                </select>
                <select id="ev-conf" class="form-select form-select-sm" style="max-width:160px" aria-label="Filter by confidence">
                    <option value="">All confidence</option>
                    <option value="strong">Strong</option>
                    <option value="weak">Weak</option>
                </select>
                <button class="btn btn-outline-primary btn-sm" onclick="mxLoadEvents()">Apply</button>
                <div class="flex-grow-1"></div>
                <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxDevEventsTable, 'device-events')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
            </div>
            <div class="mx-card-body mx-flush">
                <div class="table-responsive">
                    <table id="mx-dev-events" class="table dt-host mx-stack align-middle mb-0" style="width:100%">
                        <thead><tr><th>When</th><th>Account</th><th>Type</th><th>Device</th><th>Confidence</th><th>IP</th><?php if ($captureLocation): ?><th>Location</th><?php endif; ?></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var mxDevCaptureLocation = <?= $captureLocation ? 'true' : 'false' ?>;

function mxLabelDevice(hash, btn) {
    var span = btn.querySelector('.mx-dev-label');
    var current = (span.textContent === 'Name device') ? '' : span.textContent;
    Swal.fire({
        title: 'Name this device', input: 'text', inputValue: current,
        inputPlaceholder: 'For example, front desk tablet',
        showCancelButton: true, confirmButtonText: 'Save'
    }).then(function (r) {
        if (!r.isConfirmed) return;
        MX.api('POST', '/admin/device-audit/label', { device_hash: hash, label: r.value || '' })
            .then(function (d) { span.textContent = d.label || 'Name device'; MX.ok('Saved.'); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

function mxLoadUserDevices(userId) {
    var body = document.getElementById('dev-user-body');
    if (!userId) { body.innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-user-shield"></i><p class="mb-0">Pick a person.</p></div>'; return; }
    fetch('/api/admin/device-audit/user/' + userId, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { MX.fail(data.error); return; }
            if (!data.devices.length) { body.innerHTML = '<p class="text-muted mb-0" style="font-size:13px">No device events recorded for this account yet.</p>'; return; }
            var rows = data.devices.map(function (d) {
                var shared = d.distinct_users > 1
                    ? '<span class="mx-chip mx-chip-warning">' + d.distinct_users + ' accounts</span>'
                    : '<span class="mx-chip mx-chip-success">this account only</span>';
                return '<tr>' +
                    '<td><code style="font-size:11px" title="' + MX.escape(d.device_hash) + '">' + MX.escape(d.device_hash.slice(0, 16)) + '</code>' + (d.label ? '<div class="text-muted" style="font-size:11px">' + MX.escape(d.label) + '</div>' : '') + '</td>' +
                    '<td style="font-size:12px">' + MX.escape(d.platform || '') + '</td>' +
                    '<td><span class="mx-chip ' + (d.confidence === 'strong' ? 'mx-chip-info' : 'mx-chip-plain') + '">' + d.confidence + '</span></td>' +
                    '<td>' + shared + '</td>' +
                    '<td class="mx-tabular" style="white-space:nowrap">' + MX.escape((d.last_at || '').replace('T', ' ').slice(0, 16)) + '</td>' +
                    '<td class="mx-tabular">' + d.events + '</td>' +
                    '</tr>';
            }).join('');
            body.innerHTML = '<div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">' +
                '<thead><tr><th>Device</th><th>Platform</th><th>Confidence</th><th>Accounts served</th><th>Last seen</th><th>Events</th></tr></thead>' +
                '<tbody>' + rows + '</tbody></table></div>';
        });
}

function mxDevChip(t) {
    var map = { login: 'mx-chip-plain', check_in: 'mx-chip-success', check_out: 'mx-chip-info' };
    var label = { login: 'Login', check_in: 'Check in', check_out: 'Check out' };
    return '<span class="mx-chip ' + (map[t] || 'mx-chip-plain') + '">' + (label[t] || t) + '</span>';
}

function mxLoadEvents() {
    var qs = new URLSearchParams();
    var t = document.getElementById('ev-type').value; if (t) qs.set('type', t);
    var c = document.getElementById('ev-conf').value; if (c) qs.set('confidence', c);
    fetch('/api/admin/device-audit/events?' + qs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var labels = ['When', 'Account', 'Type', 'Device', 'Confidence', 'IP'];
            if (mxDevCaptureLocation) labels.push('Location');
            var rows = data.events.map(function (e) {
                var cells = [
                    '<span class="mx-tabular" style="white-space:nowrap">' + MX.escape((e.created_at || '').replace('T', ' ').slice(0, 16)) + '</span>',
                    MX.escape(e.username || 'unknown'),
                    mxDevChip(e.event_type),
                    '<code style="font-size:11px" title="' + MX.escape(e.device_hash) + '">' + MX.escape((e.device_hash || '').slice(0, 12)) + '</code>',
                    '<span class="mx-chip ' + (e.confidence === 'strong' ? 'mx-chip-info' : 'mx-chip-plain') + '">' + MX.escape(e.confidence) + '</span>',
                    '<code style="font-size:11px">' + MX.escape(e.ip_address || '') + '</code>'
                ];
                if (mxDevCaptureLocation) {
                    var loc = e.in_range === null || e.in_range === undefined ? '<span class="text-muted">Not provided</span>'
                        : (parseInt(e.in_range, 10) === 1 ? '<span class="mx-chip mx-chip-success">In range</span>' : '<span class="mx-chip mx-chip-warning">Out of range</span>');
                    cells.push(loc);
                }
                return cells;
            });
            if (window.mxDevEventsTable) { window.mxDevEventsTable.clear(); window.mxDevEventsTable.rows.add(rows).draw(); }
            else {
                window.mxDevEventsTable = MX.table('#mx-dev-events', {
                    data: rows, ordering: false,
                    columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
                });
            }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('dev-user').addEventListener('change', function () { mxLoadUserDevices(this.value); });
    document.querySelector('[data-bs-target="#pane-events"]').addEventListener('shown.bs.tab', function () {
        if (!window.mxDevEventsTable) mxLoadEvents();
    }, { once: true });
});
</script>
