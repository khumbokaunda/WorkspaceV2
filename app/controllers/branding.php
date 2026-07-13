<?php
// Admin: company branding. The legal and display names, the four logo slots
// (full and icon mark, each light and dark), and the favicon set generated from
// the icon mark. Every change is audited and bumps the branding version so
// cache-busted URLs refresh immediately.

declare(strict_types=1);

require_once APP_ROOT . '/app/helpers/branding_support.php';

// The upload slots and their profile columns.
function branding_slots(): array
{
    return [
        'logo_light' => 'Full logo (light background)',
        'logo_dark'  => 'Full logo (dark background)',
        'icon_light' => 'Icon mark (light background)',
        'icon_dark'  => 'Icon mark (dark background)',
    ];
}

function index(): void
{
    render('admin/branding', [
        'pageTitle' => 'Branding',
        'breadcrumbs' => ['Admin' => null, 'Settings' => '/admin/settings', 'Branding' => null],
        'profile' => company_profile(),
        'slots' => branding_slots(),
        'displayName' => brand_display_name(),
    ]);
}

function save(): void
{
    $legalName = trim((string)($_POST['legal_name'] ?? ''));
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    if ($legalName === '') {
        json_err('The legal name is required.', 422, ['legal_name' => 'Required.']);
    }
    if (mb_strlen($displayName) > 64) {
        json_err('The display name must be 64 characters or fewer.', 422, ['display_name' => 'Too long.']);
    }

    $profile = company_profile();
    $updates = ['legal_name' => $legalName, 'display_name' => ($displayName !== '' ? $displayName : null)];

    // Process each upload slot. A new file re-encodes and replaces, the old file
    // is removed. Any failure aborts before the row is written.
    $iconChanged = false;
    foreach (branding_slots() as $col => $label) {
        if (empty($_FILES[$col]) || ($_FILES[$col]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        try {
            $stored = brand_store_image($_FILES[$col]);
        } catch (Throwable $ex) {
            json_err($ex->getMessage(), 422, [$col => $ex->getMessage()]);
        }
        brand_delete_file($profile[$col] ?? null);
        $updates[$col] = $stored;
        if ($col === 'icon_light' || $col === 'icon_dark') {
            $iconChanged = true;
        }
    }

    // Persist the name and slot changes first so the icon source is current.
    branding_apply_updates($updates);
    company_profile_refresh();
    $profile = company_profile();

    // Regenerate the favicon set from the icon mark whenever the icon changed,
    // or when favicons are missing but an icon source now exists.
    $iconSource = $profile['icon_light'] ?: ($profile['icon_dark'] ?: ($profile['logo_light'] ?: $profile['logo_dark']));
    $haveFavicon = !empty($profile['favicon_32']);
    if ($iconSource && ($iconChanged || !$haveFavicon)) {
        foreach (['favicon_32', 'favicon_180', 'favicon_16'] as $fc) {
            // Only delete a generated favicon, never the icon it may point to.
            if (!empty($profile[$fc]) && $profile[$fc] !== $iconSource) {
                brand_delete_file($profile[$fc]);
            }
        }
        branding_apply_updates(brand_generate_favicons((string)$iconSource));
    }

    // Bump the version so every branding URL cache-busts.
    db_query('UPDATE company_profile SET branding_version = branding_version + 1 WHERE id = 1');
    company_profile_refresh();

    audit('company_branding.update', 'company_profile', 1, [
        'display_name' => $displayName,
        'slots_updated' => array_values(array_intersect(array_keys($updates), array_keys(branding_slots()))),
    ]);
    json_ok(['message' => 'Branding saved.']);
}

// Apply a set of company_profile column updates, ensuring the row exists.
function branding_apply_updates(array $updates): void
{
    if (!db_val('SELECT id FROM company_profile WHERE id = 1')) {
        db_query('INSERT INTO company_profile (id, legal_name) VALUES (1, ?)', [(string)($updates['legal_name'] ?? 'Company')]);
    }
    $cols = [];
    $vals = [];
    foreach ($updates as $col => $val) {
        $cols[] = "`$col` = ?";
        $vals[] = $val;
    }
    if (!$cols) {
        return;
    }
    db_query('UPDATE company_profile SET ' . implode(', ', $cols) . ' WHERE id = 1', $vals);
}

// Reset to the generated placeholder: clear every branding file and column.
function reset_branding(): void
{
    $profile = company_profile();
    foreach (['logo_light', 'logo_dark', 'icon_light', 'icon_dark', 'favicon_32', 'favicon_180', 'favicon_16'] as $col) {
        brand_delete_file($profile[$col] ?? null);
    }
    db_query(
        'UPDATE company_profile SET logo_light = NULL, logo_dark = NULL, icon_light = NULL, icon_dark = NULL,
            favicon_32 = NULL, favicon_180 = NULL, favicon_16 = NULL, branding_version = branding_version + 1
         WHERE id = 1'
    );
    company_profile_refresh();
    audit('company_branding.reset', 'company_profile', 1, []);
    json_ok(['message' => 'Branding reset to the default placeholder.']);
}
