<?php
// Primary navigation, rendered from visible_modules() so role and per-user
// visibility apply. The server-side rbac and module middleware remain the
// real gate; this is presentation only.
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navSections = [];
foreach (visible_modules() as $key => $meta) {
    if (!empty($meta['nav'])) {
        $navSections[$meta['section']][] = $meta;
    }
}
?>
<?php $brandName = brand_display_name(); ?>
<aside class="mx-sidebar" aria-label="Primary navigation">
    <a class="mx-brand" href="/dashboard" title="<?= e($brandName) ?>">
        <span class="mx-brand-mark">
            <img class="brand-logo brand-logo-light" src="<?= e(brand_asset('icon', 'light')) ?>" alt="<?= e($brandName) ?>" width="32" height="32">
            <img class="brand-logo brand-logo-dark" src="<?= e(brand_asset('icon', 'dark')) ?>" alt="" width="32" height="32" aria-hidden="true">
        </span>
        <span class="mx-brand-name"><?= e($brandName) ?></span>
    </a>
    <nav>
<?php foreach ($navSections as $section => $items): ?>
        <div class="mx-nav-section"><?= e($section) ?></div>
<?php foreach ($items as $item):
        $active = $item['path'] === '/dashboard'
            ? in_array($currentPath, ['/', '/dashboard'], true)
            : str_starts_with($currentPath, $item['path']);
?>
        <a class="mx-nav-link<?= $active ? ' active' : '' ?>" href="<?= e($item['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= e($item['icon']) ?>"></i>
            <span class="mx-nav-label"><?= e($item['label']) ?></span>
        </a>
<?php endforeach; ?>
<?php endforeach; ?>
    </nav>
</aside>
