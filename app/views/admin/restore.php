<?php
// Restore from an archive produced by the reset wizard. Gated exactly as reset.
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Restore from Archive</h1>
    <a class="btn btn-outline-primary btn-sm" href="/admin/reset"><i class="fa-solid fa-arrow-left me-1"></i>Back to reset</a>
</div>

<div class="mx-card mb-3" style="border-left:3px solid var(--mx-danger)">
    <div class="mx-card-body d-flex align-items-start gap-3">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:22px;color:var(--mx-danger)"></i>
        <div>
            <h2 style="font-size:16px" class="mb-1">Restore replaces everything</h2>
            <p class="text-muted mb-0" style="font-size:13px">Restoring an archive replaces all data and uploaded files with the archived state. The archive is verified against its checksum and version before anything changes. You will be signed out afterwards and should sign in with the credentials from the restored data.</p>
        </div>
    </div>
</div>

<div class="mx-card" style="max-width:640px">
    <div class="mx-card-body">
<?php if (!$hasTotp): ?>
        <div class="alert alert-warning py-2" style="font-size:13px"><i class="fa-solid fa-shield-halved me-1"></i>You must enrol in two-factor authentication before you can restore. <a href="/account/two-factor">Enrol now</a>.</div>
<?php else: ?>
        <form id="restore-form" autocomplete="off">
            <div class="mb-3"><label class="form-label">Archive file (.zip)</label><input type="file" class="form-control" name="archive" accept=".zip" required></div>
            <div class="mb-3"><label class="form-label">Account password</label><input type="password" class="form-control" name="password" autocomplete="off"></div>
            <div class="mb-3"><label class="form-label">Authenticator code</label><input class="form-control mx-mono" name="totp" inputmode="numeric" maxlength="6" autocomplete="off"></div>
            <div class="mb-3"><label class="form-label">Reset key</label><input type="password" class="form-control mx-mono" name="reset_key" autocomplete="off"></div>
            <div class="mb-3"><label class="form-label">Type <strong><?= e($confirmPhrase) ?></strong> to confirm</label><input class="form-control" name="confirm_phrase" autocomplete="off"></div>
            <button type="button" class="btn btn-danger" onclick="RESTORE.run()"><i class="fa-solid fa-upload me-1"></i>Verify and restore</button>
        </form>
<?php endif; ?>
    </div>
</div>

<script>
var RESTORE = {
    run: function () {
        var form = document.getElementById('restore-form');
        if (!form.reportValidity()) return;
        MX.confirm('Restore from this archive?', 'All current data and files will be replaced by the archived state. This cannot be undone.', 'Restore now').then(function (go) {
            if (!go) return;
            var fd = new FormData(form);
            fetch('/admin/restore/execute', {
                method: 'POST',
                headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d.ok) {
                    Swal.fire({ title: 'Restored', text: 'The archive has been restored. You will be signed out.', icon: 'success', allowOutsideClick: false })
                        .then(function () { window.location = d.redirect || '/login'; });
                } else {
                    MX.fail(d.error || 'The restore failed.');
                }
            }).catch(function () { MX.fail('The restore request failed.'); });
        });
    }
};
</script>
