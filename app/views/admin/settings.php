<?php // Admin: organization, attendance, leave and notification settings. ?>
<h1 style="font-size:20px" class="mb-4">Settings</h1>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Organization</h2></div>
            <div class="mx-card-body">
                <form id="set-form">
                    <div class="mb-3">
                        <label class="form-label" for="s-org">Organization name</label>
                        <input class="form-control" id="s-org" name="org_name" maxlength="100" value="<?= e($settings['org_name'] ?? 'Meridian') ?>">
                        <div class="form-text">Shown in the sidebar, page titles and email. Renaming the product is this one value.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="s-tz">Timezone</label>
                        <select class="form-select" id="s-tz" name="timezone">
<?php foreach (DateTimeZone::listIdentifiers(DateTimeZone::AFRICA) as $tz): ?>
                            <option<?= ($settings['timezone'] ?? '') === $tz ? ' selected' : '' ?>><?= e($tz) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label" for="s-late">Attendance late threshold</label>
                            <input type="time" class="form-control" id="s-late" name="late_threshold" value="<?= e($settings['late_threshold'] ?? '08:30') ?>">
                        </div>
                        <div class="col">
                            <label class="form-label" for="s-lys">Leave year starts (MM-DD)</label>
                            <input class="form-control" id="s-lys" name="leave_year_start" pattern="\d{2}-\d{2}" value="<?= e($settings['leave_year_start'] ?? '01-01') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="s-marks">Certification reminder day marks</label>
                        <input class="form-control" id="s-marks" name="cert_reminder_days" pattern="\d{1,3}(,\d{1,3})*" value="<?= e($settings['cert_reminder_days'] ?? '90,30,7') ?>">
                        <div class="form-text">Comma separated days before expiry, for the daily cron job.</div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="s-mgr" name="cert_notify_manager" <?= ($settings['cert_notify_manager'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="s-mgr">Also email the manager about expiring certifications</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="s-mail" name="mail_notifications" <?= ($settings['mail_notifications'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="s-mail">Send transactional email</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="s-totp" name="totp_required_admin" <?= ($settings['totp_required_admin'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="s-totp">Require two-factor for administrators (takes effect once TOTP is enrolled)</label>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="mxSaveSettings()">Save settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Leave types</h2>
                <button class="btn btn-outline-primary btn-sm" onclick="mxEditLeaveType(null)"><i class="fa-solid fa-plus me-1"></i>Add type</button>
            </div>
            <div class="mx-card-body mx-flush">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Name</th><th>Paid</th><th>Default days per year</th><th></th></tr></thead>
                    <tbody>
<?php foreach ($leaveTypes as $lt): ?>
                        <tr>
                            <td><?= e($lt['name']) ?></td>
                            <td><span class="mx-chip <?= (int)$lt['is_paid'] === 1 ? 'mx-chip-success' : 'mx-chip-plain' ?>"><?= (int)$lt['is_paid'] === 1 ? 'Paid' : 'Unpaid' ?></span></td>
                            <td class="mx-tabular"><?= e(rtrim(rtrim((string)$lt['default_annual_allocation'], '0'), '.')) ?></td>
                            <td><button class="btn btn-subtle btn-sm" onclick='mxEditLeaveType(<?= json_encode([
                                'id' => (int)$lt['id'], 'name' => $lt['name'],
                                'is_paid' => (int)$lt['is_paid'],
                                'default_annual_allocation' => (float)$lt['default_annual_allocation'],
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' aria-label="Edit leave type"><i class="fa-solid fa-pen"></i></button></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-muted px-3 py-2 mb-0" style="font-size:12px">Changing a default applies to balances created after the change. Existing yearly balances keep their allocation.</p>
            </div>
        </div>

        <div class="mx-card mt-3">
            <div class="mx-card-header">
                <h2>System check</h2>
                <button class="btn btn-outline-primary btn-sm" onclick="mxSystemCheck()"><i class="fa-solid fa-stethoscope me-1"></i>Run check</button>
            </div>
            <div class="mx-card-body" id="mx-syscheck">
                <p class="text-muted mb-0" style="font-size:13px">Confirms which database this instance writes to, whether writes actually persist, and whether the autocommit fix is present. Use this if a saved record does not appear.</p>
            </div>
        </div>
    </div>
</div>

<script>
function mxSystemCheck() {
    var box = document.getElementById('mx-syscheck');
    box.innerHTML = '<span class="mx-skeleton mb-2" style="width:70%"></span><span class="mx-skeleton" style="width:50%"></span>';
    fetch('/api/admin/system-check', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { box.innerHTML = '<p style="color:var(--mx-danger)">' + MX.escape(data.error || 'Check failed.') + '</p>'; return; }
            var c = data.check;
            var writeOk = c.write_test === 'passed';
            function row(label, value, good) {
                var color = good === undefined ? 'var(--mx-text)' : (good ? 'var(--mx-success)' : 'var(--mx-danger)');
                return '<div class="d-flex justify-content-between py-1 border-bottom" style="font-size:13px">' +
                    '<span class="text-muted">' + label + '</span>' +
                    '<span class="mx-mono" style="color:' + color + '">' + MX.escape(String(value)) + '</span></div>';
            }
            box.innerHTML =
                (writeOk
                    ? '<div class="mx-chip mx-chip-success mb-3">Writes persist correctly</div>'
                    : '<div class="mx-chip mx-chip-danger mb-3">Writes are NOT persisting</div>') +
                row('Database host', c.db_host) +
                row('Database name', c.db_name) +
                row('Server version', c.db_version) +
                row('Autocommit', c.autocommit === '1' ? '1 (on)' : c.autocommit + ' (off)', c.autocommit === '1') +
                row('Autocommit fix present', c.has_autocommit_fix ? 'yes' : 'no (redeploy needed)', c.has_autocommit_fix) +
                row('Round-trip write test', c.write_test, writeOk) +
                row('Assets in this database', c.asset_count) +
                row('People in this database', c.people_count) +
                (c.write_error ? '<p class="mt-2" style="color:var(--mx-danger);font-size:12px">Write error: ' + MX.escape(c.write_error) + '</p>' : '') +
                (!writeOk && c.autocommit !== '1'
                    ? '<p class="mt-2" style="font-size:12px">Autocommit is off on this server and the fix is ' + (c.has_autocommit_fix ? 'present but not taking effect (restart the web server or clear the PHP opcode cache)' : 'missing (deploy the latest app/bootstrap.php)') + '.</p>'
                    : '');
        })
        .catch(function () { box.innerHTML = '<p style="color:var(--mx-danger)">The check could not run.</p>'; });
}

function mxSaveSettings() {
    var form = document.getElementById('set-form');
    var payload = MX.formData(form);
    ['cert_notify_manager', 'mail_notifications', 'totp_required_admin'].forEach(function (k) {
        payload[k] = payload[k] ? '1' : '0';
    });
    MX.api('POST', '/admin/settings', payload)
        .then(function () { MX.ok('Settings saved.'); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxEditLeaveType(lt) {
    lt = lt || {};
    var body = '<form id="lt-form">' +
        (lt.id ? '<input type="hidden" name="id" value="' + lt.id + '">' : '') +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="80" value="' + MX.escape(lt.name || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Default days per year</label><input type="number" step="0.5" min="0" max="366" class="form-control" name="default_annual_allocation" required value="' + (lt.default_annual_allocation != null ? lt.default_annual_allocation : '') + '"></div>' +
        '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_paid" id="lt-paid"' + (lt.is_paid !== 0 ? ' checked' : '') + '><label class="form-check-label" for="lt-paid">Paid leave (counts against the balance)</label></div>' +
        '</form>';
    MX.drawer.open({
        title: lt.id ? 'Edit leave type' : 'Add leave type',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSaveLeaveType()">Save</button>'
    });
}
function mxSaveLeaveType() {
    var form = document.getElementById('lt-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/admin/settings/leave-types', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
