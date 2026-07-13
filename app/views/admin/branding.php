<?php
// Admin: branding. Names, the four upload slots, and a dual-theme live preview
// that shows exactly how each asset renders in the sidebar, collapsed sidebar,
// login page and browser tab, so a dark-mode logo can be checked for legibility
// without switching the whole interface.
$p = $profile;
$initialsUri = brand_placeholder_uri();
$seed = [
    'iconLight' => brand_asset('icon', 'light'),
    'iconDark'  => brand_asset('icon', 'dark'),
    'fullLight' => brand_asset('full', 'light'),
    'fullDark'  => brand_asset('full', 'dark'),
];
$filled = [
    'logo_light' => brand_slot_filled('logo_light'),
    'logo_dark'  => brand_slot_filled('logo_dark'),
    'icon_light' => brand_slot_filled('icon_light'),
    'icon_dark'  => brand_slot_filled('icon_dark'),
];
$recommend = [
    'logo_light' => 'Transparent PNG, about 512 px wide',
    'logo_dark'  => 'Transparent PNG, about 512 px wide',
    'icon_light' => 'Square PNG, 512 by 512 px',
    'icon_dark'  => 'Square PNG, 512 by 512 px',
];
$fallbackNote = [
    'logo_light' => 'Falls back to the initials placeholder.',
    'logo_dark'  => 'Falls back to the light logo, then the placeholder.',
    'icon_light' => 'Falls back to the full logo, then the placeholder.',
    'icon_dark'  => 'Falls back to the light icon or logo, then the placeholder.',
];
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Branding</h1>
    <a href="/admin/settings" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Settings</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form id="branding-form">
            <div class="mx-card mb-3">
                <div class="mx-card-header"><h2>Name</h2></div>
                <div class="mx-card-body">
                    <div class="mb-3">
                        <label class="form-label">Legal name</label>
                        <input class="form-control" name="legal_name" required maxlength="200" value="<?= e($p['legal_name'] ?? '') ?>">
                        <div class="text-muted" style="font-size:12px">The full registered name. Used on tender packs, payslips and contracts.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Display name</label>
                        <input class="form-control" id="bd-display" name="display_name" maxlength="64" value="<?= e($p['display_name'] ?? '') ?>" oninput="mxBrandCount()">
                        <div class="d-flex justify-content-between">
                            <div class="text-muted" style="font-size:12px">The short label shown in the sidebar, top bar and browser tab. Leave empty to use the legal name.</div>
                            <div class="text-muted" style="font-size:12px"><span id="bd-count">0</span>/64</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mx-card mb-3">
                <div class="mx-card-header"><h2>Logos</h2></div>
                <div class="mx-card-body">
                    <p class="text-muted" style="font-size:12px">Accepts PNG, JPG or WEBP up to 2 MB. Export your logo as a transparent PNG for the best result.</p>
<?php foreach ($slots as $col => $label): ?>
                    <div class="mb-3 pb-3" style="border-bottom:1px solid var(--mx-border)">
                        <label class="form-label"><?= e($label) ?></label>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="file" class="form-control bd-file" name="<?= e($col) ?>" data-slot="<?= e($col) ?>" accept="image/png,image/jpeg,image/webp" style="max-width:320px">
                            <span class="mx-chip <?= $filled[$col] ? 'mx-chip-success' : 'mx-chip-plain' ?>" id="bd-status-<?= e($col) ?>"><?= $filled[$col] ? 'Set' : 'Empty' ?></span>
                        </div>
                        <div class="text-muted" style="font-size:12px"><?= e($recommend[$col]) ?>. <?= $filled[$col] ? '' : e($fallbackNote[$col]) ?></div>
                    </div>
<?php endforeach; ?>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" onclick="mxBrandSave()">Save branding</button>
                        <button type="button" class="btn btn-outline-primary" onclick="mxBrandReset()">Reset to default</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Live preview</h2></div>
            <div class="mx-card-body">
                <p class="text-muted" style="font-size:12px">Both themes are shown so you can confirm the dark logo is legible.</p>
                <div class="row g-2">
<?php foreach (['light' => 'Light', 'dark' => 'Dark'] as $mode => $modeLabel): ?>
                    <div class="col-6">
                        <div class="text-muted mb-1" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em"><?= e($modeLabel) ?></div>
                        <div class="bd-preview" data-mode="<?= e($mode) ?>" style="border:1px solid var(--mx-border);border-radius:10px;overflow:hidden;<?= $mode === 'dark' ? 'background:#0B1120;color:#E2E8F0' : 'background:#FFFFFF;color:#0F172A' ?>">
                            <div class="d-flex align-items-center gap-2 p-2" style="border-bottom:1px solid rgba(128,128,128,.25)">
                                <img class="bd-pv-icon" width="28" height="28" style="border-radius:6px;object-fit:cover" src="<?= e($mode === 'dark' ? $seed['iconDark'] : $seed['iconLight']) ?>" alt="">
                                <span class="bd-pv-name" style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($displayName) ?></span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center p-2" style="border-bottom:1px solid rgba(128,128,128,.25)" title="Collapsed sidebar">
                                <img class="bd-pv-icon" width="28" height="28" style="border-radius:6px;object-fit:cover" src="<?= e($mode === 'dark' ? $seed['iconDark'] : $seed['iconLight']) ?>" alt="">
                            </div>
                            <div class="d-flex flex-column align-items-center gap-1 p-3" title="Login">
                                <img class="bd-pv-full" width="40" height="40" style="object-fit:contain" src="<?= e($mode === 'dark' ? $seed['fullDark'] : $seed['fullLight']) ?>" alt="">
                                <span class="bd-pv-name" style="font-size:13px;font-weight:600"><?= e($displayName) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 p-2" style="border-top:1px solid rgba(128,128,128,.25);font-size:11px" title="Browser tab">
                                <img class="bd-pv-icon" width="16" height="16" style="border-radius:3px;object-fit:cover" src="<?= e($mode === 'dark' ? $seed['iconDark'] : $seed['iconLight']) ?>" alt="">
                                <span class="bd-pv-name" style="opacity:.7">Browser tab</span>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var mxBrandSeed = <?= json_encode($seed, JSON_UNESCAPED_SLASHES) ?>;
var mxBrandInitials = <?= json_encode($initialsUri, JSON_UNESCAPED_SLASHES) ?>;
var mxBrandPicked = {}; // slot -> object URL of a freshly chosen file

function mxBrandCount() {
    document.getElementById('bd-count').textContent = document.getElementById('bd-display').value.length;
    mxBrandRefreshPreview();
}

function mxBrandRefreshPreview() {
    var name = document.getElementById('bd-display').value.trim() || <?= json_encode($p['legal_name'] ?? 'Company') ?>;
    document.querySelectorAll('.bd-preview').forEach(function (box) {
        var mode = box.dataset.mode;
        var iconSlot = mode === 'dark' ? 'icon_dark' : 'icon_light';
        var logoSlot = mode === 'dark' ? 'logo_dark' : 'logo_light';
        // Icon: picked icon, else picked logo, else seeded resolved value.
        var icon = mxBrandPicked[iconSlot] || mxBrandPicked['icon_light'] || mxBrandPicked[logoSlot] || (mode === 'dark' ? mxBrandSeed.iconDark : mxBrandSeed.iconLight);
        var full = mxBrandPicked[logoSlot] || mxBrandPicked['logo_light'] || (mode === 'dark' ? mxBrandSeed.fullDark : mxBrandSeed.fullLight);
        box.querySelectorAll('.bd-pv-icon').forEach(function (img) { img.src = icon; });
        box.querySelectorAll('.bd-pv-full').forEach(function (img) { img.src = full; });
        box.querySelectorAll('.bd-pv-name').forEach(function (el) { if (!el.textContent.includes('Browser')) el.textContent = name; });
    });
}

document.querySelectorAll('.bd-file').forEach(function (input) {
    input.addEventListener('change', function () {
        var slot = this.dataset.slot;
        if (this.files && this.files[0]) {
            mxBrandPicked[slot] = URL.createObjectURL(this.files[0]);
            document.getElementById('bd-status-' + slot).textContent = 'Selected';
            document.getElementById('bd-status-' + slot).className = 'mx-chip mx-chip-info';
        }
        mxBrandRefreshPreview();
    });
});

function mxBrandSave() {
    var form = document.getElementById('branding-form');
    if (!form.reportValidity()) return;
    var fd = new FormData(form);
    fetch('/admin/branding', {
        method: 'POST',
        headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) { MX.ok(d.message || 'Saved.'); setTimeout(function () { location.reload(); }, 600); }
        else { MX.fail(d.error || 'Save failed.'); if (d.fields) MX.showFieldErrors(form, d.fields); }
    }).catch(function () { MX.fail('The save request failed.'); });
}

function mxBrandReset() {
    MX.confirm('Reset branding to the default placeholder?', 'This clears every uploaded logo and returns to the generated initials.', 'Reset').then(function (go) {
        if (!go) return;
        MX.api('POST', '/admin/branding/reset', {})
            .then(function (d) { MX.ok(d.message || 'Reset.'); setTimeout(function () { location.reload(); }, 600); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxBrandCount);
</script>
