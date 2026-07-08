<?php
// Top command bar: collapse toggle, breadcrumbs, palette trigger, theme
// toggle, notification bell, user menu.
$crumbs = $breadcrumbs ?? [($pageTitle ?? 'Home') => null];
?>
<header class="mx-topbar">
    <button type="button" id="mx-sidebar-toggle" class="mx-icon-btn" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
    </button>
    <nav aria-label="Breadcrumb" class="d-none d-sm-block">
        <ol class="breadcrumb">
<?php foreach ($crumbs as $label => $url): ?>
<?php if ($url): ?>
            <li class="breadcrumb-item"><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
<?php else: ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li>
<?php endif; ?>
<?php endforeach; ?>
        </ol>
    </nav>
    <div class="flex-grow-1"></div>
    <button type="button" class="mx-search-btn" onclick="MX.palette.open()" aria-label="Open search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span class="mx-search-hint">Search</span>
        <kbd>Ctrl K</kbd>
    </button>
    <button type="button" class="mx-icon-btn" onclick="MX.toggleTheme()" aria-label="Toggle theme">
        <i id="mx-theme-icon" class="fa-solid fa-moon"></i>
    </button>
    <div class="dropdown" id="mx-notif-dropdown">
        <button type="button" class="mx-icon-btn" data-bs-toggle="dropdown" aria-label="Notifications" aria-expanded="false">
            <i class="fa-regular fa-bell"></i>
            <span id="mx-notif-count" class="mx-badge-dot" style="display:none">0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong style="font-size:13px">Notifications</strong>
                <button type="button" class="btn btn-subtle btn-sm" onclick="MX.notifications.markAllRead()">Mark all read</button>
            </div>
            <div id="mx-notif-list" style="max-height:360px;overflow-y:auto"></div>
        </div>
    </div>
    <div class="dropdown">
        <button type="button" class="btn btn-subtle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="mx-avatar"><?= e(strtoupper(mb_substr(user_display_name(), 0, 1))) ?></span>
            <span class="d-none d-md-inline"><?= e(user_display_name()) ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size:10px"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted" style="font-size:12px"><?= e($user['role_name'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
<?php if (!empty($user['person_id']) && user_can('people.view')): ?>
            <li><a class="dropdown-item" href="/people/<?= (int)$user['person_id'] ?>"><i class="fa-regular fa-user me-2"></i>My profile</a></li>
<?php endif; ?>
            <li><a class="dropdown-item" href="/account/password"><i class="fa-solid fa-key me-2"></i>Change password</a></li>
            <li><a class="dropdown-item" href="/account/two-factor"><i class="fa-solid fa-shield-halved me-2"></i>Two-factor authentication</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="/logout" class="m-0">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="dropdown-item"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sign out</button>
                </form>
            </li>
        </ul>
    </div>
</header>
