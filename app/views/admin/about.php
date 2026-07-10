<?php // About and licensing. The license key is a soft record, not copy protection. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">About and licensing</h1>
    <a href="/admin/settings" class="btn btn-outline-primary"><i class="fa-solid fa-gear me-2"></i>Settings</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>This instance</h2></div>
            <div class="mx-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-5 text-muted fw-normal">Product</dt><dd class="col-7"><?= e($version) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Company</dt><dd class="col-7"><?= e($settings['legal_name'] ?? ($settings['org_name'] ?? 'Not set')) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Registration</dt><dd class="col-7"><?= e($settings['reg_number'] ?? 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Tax id</dt><dd class="col-7 mb-0"><?= e($settings['tax_id'] ?? 'Not set') ?></dd>
                </dl>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header"><h2>License</h2></div>
            <div class="mx-card-body">
                <p class="text-muted" style="font-size:13px">The license key records who this instance belongs to and which modules were purchased. It is a record for reference. Commercial terms are governed by your sales contract, not enforced by the software.</p>
                <form id="mx-license-form">
                    <label class="form-label" for="license_key">License key</label>
                    <textarea class="form-control mx-mono" id="license_key" name="license_key" rows="3" maxlength="255" placeholder="Paste the license key issued with your purchase"><?= e($settings['license_key'] ?? '') ?></textarea>
                    <button type="button" class="btn btn-primary mt-3" onclick="mxSaveLicense()">Save license key</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Enabled modules</h2>
                <a href="/admin/modules" class="btn btn-subtle btn-sm">Manage</a>
            </div>
            <div class="mx-card-body">
<?php if (!$enabledModules): ?>
                <p class="text-muted mb-0">No modules enabled.</p>
<?php else: ?>
                <div class="d-flex flex-wrap gap-2">
<?php foreach ($enabledModules as $m): ?>
                    <span class="mx-chip mx-chip-success"><?= e(ucwords(str_replace('_', ' ', $m['module_key']))) ?></span>
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function mxSaveLicense() {
    var form = document.getElementById('mx-license-form');
    MX.api('POST', '/admin/about/license', MX.formData(form))
        .then(function () { MX.ok('License key saved.'); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
