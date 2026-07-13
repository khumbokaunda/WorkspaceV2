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
<link href="/assets/vendor/css/fonts.css" rel="stylesheet">
<link href="/assets/vendor/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/css/fontawesome.min.css" rel="stylesheet">
<link href="/assets/vendor/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="<?= e(asset_url('/assets/css/app.css')) ?>" rel="stylesheet">
