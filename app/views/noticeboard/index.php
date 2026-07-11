<?php
// Noticeboard: announcements everyone reads, and the read-only policy library.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Noticeboard</h1>
<?php if ($canManage): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="mxUploadPolicy()"><i class="fa-solid fa-file-arrow-up me-2"></i>Add policy</button>
        <button class="btn btn-primary" onclick="mxNewAnnouncement()"><i class="fa-solid fa-plus me-2"></i>New announcement</button>
    </div>
<?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Announcements</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$announcements): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-bullhorn"></i><p class="mb-0">No announcements yet.</p></div>
<?php else: ?>
<?php foreach ($announcements as $a): ?>
                <div class="px-3 py-3 border-bottom">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div style="font-size:14px"><?= (int)$a['is_pinned'] === 1 ? '<i class="fa-solid fa-thumbtack me-1" style="color:var(--mx-warning)"></i>' : '' ?><strong><?= e($a['title']) ?></strong></div>
<?php if (!empty($a['body'])): ?>
                            <div class="mt-1" style="font-size:13px;white-space:pre-line"><?= e($a['body']) ?></div>
<?php endif; ?>
                            <small class="text-muted"><?= e($a['author'] ?: '') ?> &middot; <?= e(date('j M Y, H:i', strtotime($a['created_at']))) ?></small>
                        </div>
<?php if ($canManage): ?>
                        <div class="d-flex">
                            <button class="btn btn-subtle btn-sm" onclick='mxEditAnnouncement(<?= json_encode(["id"=>(int)$a["id"],"title"=>$a["title"],"body"=>$a["body"],"is_pinned"=>(int)$a["is_pinned"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteAnnouncement(<?= (int)$a['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
                        </div>
<?php endif; ?>
                    </div>
                </div>
<?php endforeach; ?>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Policies and procedures</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$policies): ?>
                <div class="mx-empty py-4"><i class="fa-regular fa-file-lines"></i><p class="mb-0">No policies published.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($policies as $p): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <i class="fa-regular fa-file-lines text-muted"></i>
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($p['title']) ?></strong><?= $p['version_label'] ? ' <span class="mx-chip mx-chip-plain" style="font-size:10px">' . e($p['version_label']) . '</span>' : '' ?></div>
                            <small class="text-muted"><?= e($p['category'] ?: '') ?><?= $p['effective_date'] ? ' &middot; effective ' . e($p['effective_date']) : '' ?></small>
                        </div>
                        <button class="btn btn-subtle btn-sm" onclick="mxDownloadPolicy(<?= (int)$p['id'] ?>)" aria-label="Download"><i class="fa-solid fa-download"></i></button>
<?php if ($canManage): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeletePolicy(<?= (int)$p['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function mxAnnForm(a) {
    a = a || {};
    return '<form id="ann-form">' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="200" value="' + MX.escape(a.title || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Body</label><textarea class="form-control" name="body" rows="5" maxlength="5000">' + MX.escape(a.body || '') + '</textarea></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_pinned" id="ann-pin"' + (a.is_pinned == 1 ? ' checked' : '') + '><label class="form-check-label" for="ann-pin">Pin to the top</label></div>' +
        '</form>';
}
function mxNewAnnouncement() {
    MX.drawer.open({ title: 'New announcement', body: mxAnnForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitAnn(null)">Post</button>' });
}
function mxEditAnnouncement(a) {
    MX.drawer.open({ title: 'Edit announcement', body: mxAnnForm(a),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitAnn(' + a.id + ')">Save</button>' });
}
function mxSubmitAnn(id) {
    var form = document.getElementById('ann-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/noticeboard/announcements/' + id, MX.formData(form)) : MX.api('POST', '/noticeboard/announcements', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteAnnouncement(id) {
    MX.confirm('Delete this announcement?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/noticeboard/announcements/' + id).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxUploadPolicy() {
    var body = '<form id="policy-form">' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="200"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Category</label><input class="form-control" name="category" maxlength="120"></div>' +
        '<div class="col"><label class="form-label">Version</label><input class="form-control" name="version_label" maxlength="60"></div></div>' +
        '<div class="mb-3"><label class="form-label">Effective date</label><input type="date" class="form-control" name="effective_date"></div>' +
        '<div class="mb-3"><label class="form-label">File</label><input type="file" class="form-control" id="policy-file" required accept=".pdf,.doc,.docx,.odt,.txt,.png,.jpg,.jpeg"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500"></div>' +
        '</form>';
    MX.drawer.open({ title: 'Add policy', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitPolicy()">Upload</button>' });
}
function mxSubmitPolicy() {
    var form = document.getElementById('policy-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    ['title', 'category', 'version_label', 'effective_date', 'notes'].forEach(function (k) { fd.append(k, MX.clean(form[k].value)); });
    fd.append('file', document.getElementById('policy-file').files[0]);
    fetch('/noticeboard/policies', { method: 'POST', headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: fd, redirect: 'manual' })
        .then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok === false) { MX.fail(data.error); MX.showFieldErrors(form, data.fields); return; }
            MX.drawer.close(); MX.ok('Uploaded.'); setTimeout(function () { location.reload(); }, 500);
        }).catch(function () { MX.fail(); });
}
function mxDownloadPolicy(id) {
    MX.api('POST', '/noticeboard/policies/' + id + '/link', {}).then(function (d) { window.location.href = d.url; }).catch(function (e) { MX.fail(e.message); });
}
function mxDeletePolicy(id) {
    MX.confirm('Delete this policy?', 'The file is removed too.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/noticeboard/policies/' + id).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
