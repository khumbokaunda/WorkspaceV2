<?php // Admin: read-only filterable audit trail. ?>
<h1 style="font-size:20px" class="mb-4">Audit Log</h1>

<div class="mx-card">
    <div class="mx-table-toolbar">
        <select id="au-entity" class="form-select form-select-sm" style="max-width:170px" aria-label="Filter by entity">
            <option value="">All entities</option>
<?php foreach ($entities as $en): ?>
            <option><?= e($en['entity']) ?></option>
<?php endforeach; ?>
        </select>
        <select id="au-user" class="form-select form-select-sm" style="max-width:170px" aria-label="Filter by user">
            <option value="">All users</option>
<?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= e($u['username']) ?></option>
<?php endforeach; ?>
        </select>
        <input type="date" id="au-from" class="form-control form-control-sm" style="max-width:150px" aria-label="From date">
        <input type="date" id="au-to" class="form-control form-control-sm" style="max-width:150px" aria-label="To date">
        <button class="btn btn-outline-primary btn-sm" onclick="mxLoadAudit()">Apply</button>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxAuditTable, 'audit')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <div class="table-responsive">
            <table id="mx-audit-table" class="table mx-stack align-middle mb-0" style="width:100%">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Detail</th><th>IP</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
function mxLoadAudit() {
    var qs = new URLSearchParams();
    var entity = document.getElementById('au-entity').value;
    var user = document.getElementById('au-user').value;
    var from = document.getElementById('au-from').value;
    var to = document.getElementById('au-to').value;
    if (entity) qs.set('entity', entity);
    if (user) qs.set('user_id', user);
    if (from) qs.set('from', from);
    if (to) qs.set('to', to);
    fetch('/api/admin/audit?' + qs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var labels = ['When', 'User', 'Action', 'Entity', 'Detail', 'IP'];
            var rows = data.entries.map(function (a) {
                var detail = '';
                try {
                    var d = JSON.parse(a.detail || '{}');
                    detail = Object.keys(d).map(function (k) { return k + ': ' + JSON.stringify(d[k]); }).join(', ');
                } catch (e) { detail = a.detail || ''; }
                var when = MX.escape(a.created_at).replace(' ', '<br>');
                var detailFull = MX.escape(detail);
                var detailShort = MX.escape(detail.length > 90 ? detail.slice(0, 90) + '…' : detail);
                return [
                    '<span class="mx-tabular" style="white-space:nowrap">' + when + '</span>',
                    MX.escape(a.username || 'system'),
                    MX.escape(a.label || a.action) + '<code class="text-muted d-block" style="font-size:11px">' + MX.escape(a.action) + '</code>',
                    '<span style="white-space:nowrap">' + MX.escape(a.entity + (a.entity_id ? ' #' + a.entity_id : '')) + '</span>',
                    '<span class="mx-audit-detail" style="font-size:12px" title="' + detailFull + '">' + detailShort + '</span>',
                    '<code style="font-size:11px;white-space:nowrap">' + MX.escape(a.ip_address || '') + '</code>'
                ];
            });
            if (window.mxAuditTable) { window.mxAuditTable.clear(); window.mxAuditTable.rows.add(rows).draw(); }
            else {
                window.mxAuditTable = MX.table('#mx-audit-table', {
                    data: rows,
                    ordering: false,
                    columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
                });
            }
        });
}
document.addEventListener('DOMContentLoaded', mxLoadAudit);
</script>
