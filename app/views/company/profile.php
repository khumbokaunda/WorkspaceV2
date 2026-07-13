<?php
// Company profile editor, reached from Settings. The single company identity
// that branding, tenders and payroll draw from. Submitted as multipart so the
// optional logo can accompany the text fields.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Company profile</h1>
    <a href="/admin/settings" class="btn btn-outline-primary"><i class="fa-solid fa-gear me-2"></i>Settings</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Identity and contact</h2></div>
            <div class="mx-card-body">
                <form id="cp-form">
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-legal">Legal name</label>
                            <input class="form-control" id="cp-legal" name="legal_name" required maxlength="200" value="<?= e($profile['legal_name'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-trading">Trading name</label>
                            <input class="form-control" id="cp-trading" name="trading_name" maxlength="200" value="<?= e($profile['trading_name'] ?? '') ?>">
                            <div class="form-text">Shown in the sidebar and page titles. Defaults to the legal name.</div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-reg">Registration number</label>
                            <input class="form-control" id="cp-reg" name="reg_number" maxlength="120" value="<?= e($profile['reg_number'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-tax">Tax identification number</label>
                            <input class="form-control" id="cp-tax" name="tax_id" maxlength="120" value="<?= e($profile['tax_id'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-phys">Physical address</label>
                            <textarea class="form-control" id="cp-phys" name="phys_address" rows="2" maxlength="400"><?= e($profile['phys_address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-postal">Postal address</label>
                            <textarea class="form-control" id="cp-postal" name="postal_address" rows="2" maxlength="400"><?= e($profile['postal_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-phone">Phone</label>
                            <input class="form-control" id="cp-phone" name="phone" maxlength="60" value="<?= e($profile['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cp-email">Email</label>
                            <input type="email" class="form-control" id="cp-email" name="email" maxlength="190" value="<?= e($profile['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cp-overview">Company overview</label>
                        <textarea class="form-control" id="cp-overview" name="overview" rows="4" maxlength="4000"><?= e($profile['overview'] ?? '') ?></textarea>
                        <div class="form-text">Used in tender cover material. A short description of the company and what it does.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cp-mission">Mission statement</label>
                        <textarea class="form-control" id="cp-mission" name="mission" rows="2" maxlength="1000"><?= e($profile['mission'] ?? '') ?></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="mxSaveProfile()">Save profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Logo</h2></div>
            <div class="mx-card-body">
<?php if (!empty($profile['logo_path'])): ?>
                <!-- Reserve the box height so the logo loading does not shift the card. -->
                <div class="mb-3" style="height:120px;display:flex;align-items:center">
                    <img src="<?= e($profile['logo_path']) ?>" alt="Company logo" height="120" style="max-width:100%;max-height:120px;width:auto">
                </div>
<?php else: ?>
                <p class="text-muted" style="font-size:13px">No logo uploaded yet.</p>
<?php endif; ?>
                <form id="cp-logo-form">
                    <div class="mb-2">
                        <label class="form-label" for="cp-logo">Replace logo</label>
                        <input type="file" class="form-control" id="cp-logo" name="logo" accept=".png,.jpg,.jpeg">
                        <div class="form-text">PNG or JPG, under 2 MB. Saved when you press Save profile.</div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function mxSaveProfile() {
    var form = document.getElementById('cp-form');
    if (!form.reportValidity()) return;
    var fd = new FormData();
    form.querySelectorAll('input[name], textarea[name]').forEach(function (el) {
        if (el.type === 'file') return;
        fd.append(el.name, MX.clean(el.value));
    });
    var logo = document.getElementById('cp-logo');
    if (logo.files[0]) fd.append('logo', logo.files[0]);
    fetch('/admin/company-profile', {
        method: 'POST',
        headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd,
        redirect: 'manual'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.ok === false) { MX.fail(data.error); MX.showFieldErrors(form, data.fields); return; }
        MX.ok('Profile saved.');
        setTimeout(function () { location.reload(); }, 700);
    }).catch(function () { MX.fail(); });
}
</script>
