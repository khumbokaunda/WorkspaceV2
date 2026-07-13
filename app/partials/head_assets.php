<?php // Shared <head> assets. Included by the app layout and by bare pages.
// All third-party assets are pinned local copies under assets/vendor, so
// the app renders identically with or without internet access. ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($_SESSION['user_id'])): ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php endif; ?>
<script>
// Persisted interface state is applied before first paint from a root class,
// never by JavaScript after render, so nothing flashes its default then
// corrects. The theme and the collapsed sidebar are both handled here.
(function () {
    var t = localStorage.getItem('mx-theme');
    if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', t);
    // The collapsed sidebar only applies on the desktop layout.
    if (localStorage.getItem('mx-sidebar') === '1' && window.innerWidth >= 992) {
        document.documentElement.classList.add('mx-sidebar-collapsed');
    }
})();
</script>
<link rel="preload" href="/assets/vendor/fonts/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<?php
// Favicon: the set generated from the icon mark when present, otherwise the
// generated initials placeholder so the browser tab is always branded and never
// requests a missing file.
$fav32 = brand_favicon('32');
$fav16 = brand_favicon('16');
$fav180 = brand_favicon('180');
?>
<?php if ($fav32): ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= e($fav32) ?>">
<?php if ($fav16): ?><link rel="icon" type="image/png" sizes="16x16" href="<?= e($fav16) ?>"><?php endif; ?>
<?php if ($fav180): ?><link rel="apple-touch-icon" sizes="180x180" href="<?= e($fav180) ?>"><?php endif; ?>
<?php else: ?>
<link rel="icon" href="<?= e(brand_placeholder_uri()) ?>">
<?php endif; ?>
<link href="/assets/vendor/css/fonts.css" rel="stylesheet">
<link href="/assets/vendor/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/css/fontawesome.min.css" rel="stylesheet">
<link href="/assets/vendor/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="<?= e(asset_url('/assets/css/app.css')) ?>" rel="stylesheet">
