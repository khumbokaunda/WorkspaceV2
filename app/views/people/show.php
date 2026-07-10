<?php
// Person profile: header, then sections for contact, certifications,
// documents (the vault), assigned assets and recent activity.
$statusChip = match ($person['employment_status']) {
    'Active' => 'mx-chip-success', 'On Leave' => 'mx-chip-info', default => 'mx-chip-danger',
};
$isSelf = (int)(current_user()['person_id'] ?? 0) === (int)$person['id'];
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-center gap-3 flex-wrap">
        <span class="mx-avatar mx-avatar-lg"><?= e(strtoupper(mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1))) ?></span>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($person['first_name'] . ' ' . $person['last_name']) ?></h1>
                <span class="mx-chip <?= $statusChip ?>"><?= e($person['employment_status']) ?></span>
            </div>
            <div class="text-muted mt-1">
                <?= e($person['job_title'] ?: 'No job title') ?><?= $person['department'] ? ' &middot; ' . e($person['department']) : '' ?>
<?php if ($person['mgr_id']): ?>
                &middot; reports to <a href="/people/<?= (int)$person['mgr_id'] ?>"><?= e($person['mgr_first'] . ' ' . $person['mgr_last']) ?></a>
<?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
<?php if (user_can('people.edit')): ?>
            <button class="btn btn-outline-primary" onclick="mxEditPerson()"><i class="fa-solid fa-pen me-2"></i>Edit</button>
<?php endif; ?>
<?php if (user_can('people.terminate') && $person['employment_status'] !== 'Terminated'): ?>
            <button class="btn btn-subtle" style="color:var(--mx-danger)" onclick="mxTerminate()"><i class="fa-solid fa-user-slash me-2"></i>Terminate</button>
<?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Contact</h2></div>
            <div class="mx-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8"><a href="mailto:<?= e($person['email']) ?>"><?= e($person['email']) ?></a></dd>
                    <dt class="col-4 text-muted fw-normal">Phone</dt><dd class="col-8"><?= e($person['phone'] ?: 'Not set') ?></dd>
                    <dt class="col-4 text-muted fw-normal">Started</dt><dd class="col-8 mb-0"><?= e($person['start_date'] ?: 'Not set') ?></dd>
                </dl>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header"><h2>Assigned assets</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$assets): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-laptop"></i><p class="mb-0">No assets assigned.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($assets as $a): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <code><?= e($a['asset_tag']) ?></code>
                        <div class="flex-grow-1" style="font-size:13px"><?= e($a['name']) ?></div>
                        <small class="text-muted"><?= e(date('j M Y', strtotime($a['assigned_at']))) ?></small>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Certifications</h2>
                <a href="/certifications" class="btn btn-subtle btn-sm">Skills area</a>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$certs): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-certificate"></i><p class="mb-0">No certifications recorded.</p></div>
<?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
<?php $canSeeCertFiles = $isSelf || user_can('certifications.manage_all'); ?>
                        <thead><tr><th>Code</th><th>Name</th><th>Issuer</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                        <tbody>
<?php foreach ($certs as $c):
        $chip = match ($c['effective_status']) { 'Active' => 'mx-chip-success', 'In Progress' => 'mx-chip-warning', default => 'mx-chip-danger' }; ?>
                            <tr>
                                <td><code><?= e($c['code']) ?></code></td>
                                <td><?= e($c['name']) ?></td>
                                <td><?= e($c['issuing_body'] ?: '') ?></td>
                                <td class="mx-tabular"><?= e($c['expires_on'] ?: 'No expiry') ?></td>
                                <td><span class="mx-chip <?= $chip ?>"><?= e($c['effective_status']) ?></span></td>
                                <td class="text-end">
<?php if ($canSeeCertFiles && $c['cert_stored_name']): ?>
                                    <button class="btn btn-subtle btn-sm" onclick="mxDownloadCertFile(<?= (int)$c['id'] ?>)" aria-label="Download certificate"><i class="fa-solid fa-download"></i></button>
<?php endif; ?>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
<?php endif; ?>
            </div>
        </div>

<?php if ($canSeeDocs): ?>
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Document vault</h2>
<?php if (user_can('documents.upload') && ($isSelf || user_can('documents.view_all'))): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxUploadDoc()"><i class="fa-solid fa-upload me-1"></i>Upload</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush" id="mx-doc-list">
<?php if (!$documents): ?>
                <div class="mx-empty py-4"><i class="fa-regular fa-folder-open"></i><p class="mb-0">No documents yet. CVs and contracts uploaded here are versioned, newest first.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($documents as $d): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <i class="fa-regular fa-file-lines text-muted"></i>
                        <div class="flex-grow-1">
                            <div style="font-size:13px">
                                <strong><?= e($d['doc_type']) ?></strong>
<?php if ($d['version_label']): ?><span class="mx-chip mx-chip-plain ms-1"><?= e($d['version_label']) ?></span><?php endif; ?>
                                <span class="text-muted ms-1"><?= e($d['original_name']) ?></span>
                            </div>
                            <small class="text-muted"><?= e(date('j M Y H:i', strtotime($d['uploaded_at']))) ?> by <?= e($d['uploader']) ?><?= $d['note'] ? ', ' . e($d['note']) : '' ?> &middot; <?= e(number_format($d['size_bytes'] / 1024, 0)) ?> KB</small>
                        </div>
                        <button class="btn btn-subtle btn-sm" onclick="mxDownloadDoc(<?= (int)$d['id'] ?>)" aria-label="Download"><i class="fa-solid fa-download"></i></button>
<?php if (user_can('documents.view_all')): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteDoc(<?= (int)$d['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
<?php endif; ?>

        <div class="mx-card">
            <div class="mx-card-header"><h2>Recent activity</h2></div>
            <div class="mx-card-body">
<?php if (!$activity): ?>
                <p class="text-muted mb-0" style="font-size:13px">No recorded activity.</p>
<?php else: ?>
                <ul class="mx-timeline">
<?php foreach ($activity as $ev): ?>
                    <li>
                        <div style="font-size:13px"><?= e($ev['action']) ?></div>
                        <div class="mx-timeline-time"><?= e(date('j M Y H:i', strtotime($ev['created_at']))) ?></div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var mxPerson = <?= json_encode([
    'id' => (int)$person['id'],
    'first_name' => $person['first_name'],
    'last_name' => $person['last_name'],
    'email' => $person['email'],
    'phone' => $person['phone'],
    'job_title' => $person['job_title'],
    'department' => $person['department'],
    'manager_id' => $person['manager_id'] !== null ? (int)$person['manager_id'] : null,
    'start_date' => $person['start_date'],
]) ?>;
var mxManagers = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name']], $managers)) ?>;

function mxEditPerson() {
    var body =
        '<form id="pe-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">First name</label><input class="form-control" name="first_name" required maxlength="80" value="' + MX.escape(mxPerson.first_name) + '"></div>' +
        '<div class="col"><label class="form-label">Last name</label><input class="form-control" name="last_name" required maxlength="80" value="' + MX.escape(mxPerson.last_name) + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="190" value="' + MX.escape(mxPerson.email) + '"></div>' +
        '<div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="40" value="' + MX.escape(mxPerson.phone || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Job title</label><input class="form-control" name="job_title" maxlength="120" value="' + MX.escape(mxPerson.job_title || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Department</label><input class="form-control" name="department" maxlength="120" value="' + MX.escape(mxPerson.department || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Manager</label><select class="form-select" name="manager_id"><option value="">None</option>' +
        mxManagers.map(function (m) {
            return '<option value="' + m.id + '"' + (mxPerson.manager_id === m.id ? ' selected' : '') + '>' + MX.escape(m.name) + '</option>';
        }).join('') +
        '</select></div>' +
        '<div class="mb-3"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date" value="' + MX.escape(mxPerson.start_date || '') + '"></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Edit ' + mxPerson.first_name,
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSavePerson()">Save changes</button>'
    });
}

function mxSavePerson() {
    var form = document.getElementById('pe-form');
    if (!form.reportValidity()) return;
    MX.api('PATCH', '/people/' + mxPerson.id, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (err) { MX.fail(err.message); MX.showFieldErrors(form, err.fields); });
}

function mxTerminate() {
    MX.confirm(
        'Terminate employment?',
        'The record is kept for history. Any login is disabled and assigned assets are returned.',
        'Terminate'
    ).then(function (go) {
        if (!go) return;
        MX.api('POST', '/people/' + mxPerson.id + '/terminate', {})
            .then(function () { MX.ok('Employment terminated.'); setTimeout(function () { location.reload(); }, 700); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

function mxUploadDoc() {
    var body =
        '<form id="doc-form">' +
        '<div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="doc_type"><option>CV</option><option>Contract</option><option>Other</option></select></div>' +
        '<div class="mb-3"><label class="form-label">Version label</label><input class="form-control" name="version_label" maxlength="60" placeholder="For example v3, 2026 refresh"></div>' +
        '<div class="mb-3"><label class="form-label">File</label><input type="file" class="form-control" name="file" required accept=".pdf,.doc,.docx,.odt,.txt,.png,.jpg,.jpeg"></div>' +
        '<div class="mb-3"><label class="form-label">Note</label><input class="form-control" name="note" maxlength="255"></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Upload document',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitDoc()">Upload</button>'
    });
}

function mxSubmitDoc() {
    var form = document.getElementById('doc-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    fd.append('doc_type', form.doc_type.value);
    fd.append('version_label', MX.clean(form.version_label.value));
    fd.append('note', MX.clean(form.note.value));
    fd.append('file', form.file.files[0]);
    fetch('/people/' + mxPerson.id + '/documents', {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok === false) { MX.fail(data.error); return; }
        MX.ok('Uploaded.');
        setTimeout(function () { location.reload(); }, 600);
    }).catch(function () { MX.fail(); });
}

function mxDownloadDoc(id) {
    MX.api('POST', '/documents/' + id + '/link', {})
        .then(function (data) { window.location.href = data.url; })
        .catch(function (e) { MX.fail(e.message); });
}

function mxDownloadCertFile(id) {
    MX.api('POST', '/certifications/' + id + '/file-link', {})
        .then(function (data) { window.location.href = data.url; })
        .catch(function (e) { MX.fail(e.message); });
}

function mxDeleteDoc(id) {
    MX.confirm('Delete this document?', 'The file is removed permanently.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/documents/' + id)
            .then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}
</script>
