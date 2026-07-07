        </main>
    </div>
</div>

<?php // Shared slide-over drawer shell, filled by MX.drawer.open(). ?>
<div id="mx-drawer-backdrop" class="mx-drawer-backdrop" style="display:none"></div>
<div id="mx-drawer" class="mx-drawer" style="display:none" role="dialog" aria-modal="true" aria-labelledby="mx-drawer-title">
    <div class="mx-drawer-header">
        <h2 class="mx-drawer-title" id="mx-drawer-title"></h2>
        <button type="button" class="btn btn-subtle btn-sm mx-drawer-close" aria-label="Close panel"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mx-drawer-body"></div>
    <div class="mx-drawer-footer" style="display:none"></div>
</div>

<?php // Command palette shell. ?>
<div id="mx-palette-backdrop" class="mx-drawer-backdrop" style="display:none"></div>
<div id="mx-palette" class="mx-palette" style="display:none" role="dialog" aria-modal="true" aria-label="Command palette">
    <input type="text" placeholder="Search or run a quick action" aria-label="Search" autocomplete="off">
    <div class="mx-palette-results" role="listbox"></div>
</div>

<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
</body>
</html>
