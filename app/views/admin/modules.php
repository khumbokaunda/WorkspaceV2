<?php // Instance module enablement. Core modules are locked on. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Modules</h1>
    <a href="/admin/settings" class="btn btn-outline-primary"><i class="fa-solid fa-gear me-2"></i>Settings</a>
</div>

<div class="mx-card">
    <div class="mx-card-header">
        <h2>Enabled modules</h2>
        <button class="btn btn-primary btn-sm" onclick="mxSaveModules()">Save changes</button>
    </div>
    <div class="mx-card-body">
        <p class="text-muted" style="font-size:13px">Turn optional modules on or off for this instance. A disabled module disappears from the navigation for everyone and its pages return not found. Core modules are always on.</p>
        <div class="row g-2">
<?php foreach ($modules as $m): ?>
            <div class="col-12 col-md-6">
                <div class="form-check p-3 m-0" style="border:1px solid var(--mx-border);border-radius:var(--mx-radius-input)">
                    <input class="form-check-input mx-module-box" type="checkbox" value="<?= e($m['key']) ?>" id="mod-<?= e($m['key']) ?>"
                        <?= $m['is_enabled'] ? 'checked' : '' ?> <?= $m['is_core'] ? 'disabled' : '' ?> style="margin-left:0">
                    <label class="form-check-label ms-2" for="mod-<?= e($m['key']) ?>">
                        <strong><?= e($m['label']) ?></strong>
<?php if ($m['is_core']): ?>
                        <span class="mx-chip mx-chip-plain ms-1">Core, always on</span>
<?php endif; ?>
                        <span class="text-muted d-block" style="font-size:12px"><code><?= e($m['key']) ?></code></span>
                    </label>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function mxSaveModules() {
    var selected = [];
    document.querySelectorAll('.mx-module-box:not([disabled])').forEach(function (b) { if (b.checked) selected.push(b.value); });
    MX.api('POST', '/admin/modules', { modules: selected })
        .then(function () { MX.ok('Modules updated.'); setTimeout(function () { location.reload(); }, 700); })
        .catch(function (e) { MX.fail(e.message); });
}
</script>
