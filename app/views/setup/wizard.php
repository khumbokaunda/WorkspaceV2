<?php $appName = config('app.name', 'Meridian'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>
    (function () {
        var t = localStorage.getItem('mx-theme');
        if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
    </script>
    <link href="/assets/vendor/css/fonts.css" rel="stylesheet">
    <link href="/assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/css/fontawesome.min.css" rel="stylesheet">
    <link href="<?= e(asset_url('/assets/css/app.css')) ?>" rel="stylesheet">
    <title>Set up <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card" style="max-width:640px">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="mx-brand-mark" style="width:36px;height:36px;border-radius:9px;background:var(--mx-primary);color:#fff;display:inline-flex;align-items:center;justify-content:center"><i class="fa-solid fa-compass"></i></span>
            <h1 style="font-size:20px;margin:0">Welcome to <?= e($appName) ?></h1>
        </div>
        <p class="text-muted mb-3">A short setup gets your instance ready. It runs once.</p>

        <div class="d-flex gap-2 mb-4" id="mx-steps">
            <div class="mx-setup-step active" data-step="1">1. Company</div>
            <div class="mx-setup-step" data-step="2">2. Administrator</div>
            <div class="mx-setup-step" data-step="3">3. Modules</div>
            <div class="mx-setup-step" data-step="4">4. Basics</div>
        </div>

        <form id="mx-setup-form" onsubmit="return false">
            <!-- Step 1: Company -->
            <div class="mx-setup-panel" data-panel="1">
                <div class="mb-3"><label class="form-label">Legal name</label><input class="form-control" name="legal_name" required></div>
                <div class="mb-3"><label class="form-label">Trading name</label><input class="form-control" name="trading_name" placeholder="Defaults to the legal name"></div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Registration number</label><input class="form-control" name="reg_number"></div>
                    <div class="col"><label class="form-label">Tax identification number</label><input class="form-control" name="tax_id"></div>
                </div>
                <div class="mb-3"><label class="form-label">Physical address</label><input class="form-control" name="phys_address"></div>
                <div class="mb-3"><label class="form-label">Postal address</label><input class="form-control" name="postal_address"></div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Phone</label><input class="form-control" name="company_phone"></div>
                    <div class="col"><label class="form-label">Company email</label><input type="email" class="form-control" name="company_email"></div>
                </div>
                <div class="mb-2"><label class="form-label">Logo (optional, PNG or JPG)</label><input type="file" class="form-control" name="logo" accept=".png,.jpg,.jpeg"></div>
            </div>

            <!-- Step 2: Administrator -->
            <div class="mx-setup-panel" data-panel="2" style="display:none">
                <p class="text-muted" style="font-size:13px">This is the first administrator account. Choose a strong password of at least 12 characters.</p>
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="admin_username" minlength="3" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="admin_email" required></div>
                <div class="mb-2"><label class="form-label">Password</label><input type="password" class="form-control" name="admin_password" minlength="12" required autocomplete="new-password"></div>
            </div>

            <!-- Step 3: Modules -->
            <div class="mx-setup-panel" data-panel="3" style="display:none">
                <p class="text-muted" style="font-size:13px">Turn on the modules this company needs. You can change these later under Settings, Modules. Dashboard, People and the admin area are always on.</p>
<?php foreach ($optionalModules as $key => $desc): ?>
                <div class="form-check mb-2 p-2" style="border:1px solid var(--mx-border);border-radius:var(--mx-radius-input)">
                    <input class="form-check-input" type="checkbox" name="modules[]" value="<?= e($key) ?>" id="mod-<?= e($key) ?>" style="margin-left:0">
                    <label class="form-check-label ms-2" for="mod-<?= e($key) ?>">
                        <strong style="text-transform:capitalize"><?= e(str_replace('_', ' ', $key)) ?></strong>
                        <span class="text-muted d-block" style="font-size:12px"><?= e($desc) ?></span>
                    </label>
                </div>
<?php endforeach; ?>
            </div>

            <!-- Step 4: Basics -->
            <div class="mx-setup-panel" data-panel="4" style="display:none">
                <div class="mb-3"><label class="form-label">Timezone</label>
                    <select class="form-select" name="timezone">
<?php foreach ($timezones as $tz): ?>
                        <option<?= $tz === 'Africa/Blantyre' ? ' selected' : '' ?>><?= e($tz) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" value="MWK" maxlength="3" style="text-transform:uppercase"></div>
                    <div class="col"><label class="form-label">Financial year starts (MM-DD)</label><input class="form-control" name="financial_year_start" value="01-01" pattern="\d{2}-\d{2}"></div>
                </div>
                <div class="mb-3"><label class="form-label">Attendance late threshold</label><input type="time" class="form-control" name="late_threshold" value="08:30"></div>
                <p class="text-muted" style="font-size:12px">Leave types come seeded with sensible defaults. Adjust them later under Settings.</p>
            </div>
        </form>

        <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-outline-primary" id="mx-setup-back" onclick="mxSetupStep(-1)" style="visibility:hidden">Back</button>
            <button type="button" class="btn btn-primary" id="mx-setup-next" onclick="mxSetupStep(1)">Next</button>
        </div>
    </div>
</div>

<style>
.mx-setup-step { flex:1; text-align:center; font-size:12px; padding:6px 4px; border-radius:var(--mx-radius-pill); background:var(--mx-bg); color:var(--mx-muted); border:1px solid var(--mx-border); }
.mx-setup-step.active { background:var(--mx-primary-tint); color:var(--mx-primary); border-color:transparent; font-weight:600; }
.mx-setup-step.done { color:var(--mx-success); }
</style>

<script src="/assets/vendor/js/sweetalert2.all.min.js"></script>
<script>
var mxStep = 1, mxMaxStep = 4;
var mxCsrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');

function mxSetupShow() {
    document.querySelectorAll('.mx-setup-panel').forEach(function (p) {
        p.style.display = parseInt(p.dataset.panel, 10) === mxStep ? '' : 'none';
    });
    document.querySelectorAll('.mx-setup-step').forEach(function (s) {
        var n = parseInt(s.dataset.step, 10);
        s.classList.toggle('active', n === mxStep);
        s.classList.toggle('done', n < mxStep);
    });
    document.getElementById('mx-setup-back').style.visibility = mxStep === 1 ? 'hidden' : 'visible';
    document.getElementById('mx-setup-next').textContent = mxStep === mxMaxStep ? 'Finish setup' : 'Next';
}

function mxSetupValidateStep() {
    var panel = document.querySelector('.mx-setup-panel[data-panel="' + mxStep + '"]');
    var fields = panel.querySelectorAll('input[required], select[required]');
    for (var i = 0; i < fields.length; i++) {
        if (!fields[i].reportValidity()) return false;
    }
    return true;
}

function mxSetupStep(dir) {
    if (dir > 0 && !mxSetupValidateStep()) return;
    if (dir > 0 && mxStep === mxMaxStep) { mxSetupSubmit(); return; }
    mxStep = Math.min(mxMaxStep, Math.max(1, mxStep + dir));
    mxSetupShow();
}

function mxSetupSubmit() {
    var form = document.getElementById('mx-setup-form');
    var fd = new FormData(form);
    var btn = document.getElementById('mx-setup-next');
    btn.disabled = true; btn.textContent = 'Setting up...';
    fetch('/setup', {
        method: 'POST',
        headers: { 'X-CSRF-Token': mxCsrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd,
        redirect: 'manual'
    }).then(async function (res) {
        var data = null; try { data = JSON.parse(await res.text()); } catch (e) {}
        if (!res.ok || !data || data.ok === false) {
            throw new Error((data && data.error) || 'Setup could not complete.');
        }
        Swal.fire({ icon: 'success', title: 'Setup complete', text: 'Sign in with the administrator account you created.', confirmButtonText: 'Go to sign in' })
            .then(function () { window.location.href = data.redirect || '/login'; });
    }).catch(function (e) {
        btn.disabled = false; btn.textContent = 'Finish setup';
        Swal.fire({ icon: 'error', title: 'Could not complete setup', text: e.message });
    });
}

mxSetupShow();
</script>
</body>
</html>
