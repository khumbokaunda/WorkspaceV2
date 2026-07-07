<?php
// Org chart rendered from the manager_id hierarchy as nested lists.
function mx_org_branch(array $byManager, int $managerId, int $depth = 0): void
{
    if (empty($byManager[$managerId]) || $depth > 8) {
        return;
    }
    echo '<ul class="list-unstyled ' . ($depth > 0 ? 'ms-4 mt-2 ps-3' : '') . '"' . ($depth > 0 ? ' style="border-left:2px solid var(--mx-border)"' : '') . '>';
    foreach ($byManager[$managerId] as $p) {
        echo '<li class="mb-2">';
        echo '<a class="mx-org-node" style="flex-direction:row;gap:10px;align-items:center" href="/people/' . (int)$p['id'] . '">';
        echo '<span class="mx-avatar">' . e(strtoupper(mb_substr($p['first_name'], 0, 1))) . '</span>';
        echo '<span class="text-start"><span style="font-weight:600;font-size:13px;display:block;color:var(--mx-text)">' . e($p['first_name'] . ' ' . $p['last_name']) . '</span>';
        echo '<span class="text-muted" style="font-size:12px">' . e($p['job_title'] ?: '') . ($p['department'] ? ' &middot; ' . e($p['department']) : '') . '</span></span>';
        echo '</a>';
        mx_org_branch($byManager, (int)$p['id'], $depth + 1);
        echo '</li>';
    }
    echo '</ul>';
}
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 style="font-size:20px" class="mb-0">Org chart</h1>
    <a href="/people" class="btn btn-outline-primary"><i class="fa-solid fa-table-list me-2"></i>Directory</a>
</div>

<div class="mx-card">
    <div class="mx-card-body">
<?php if (empty($byManager[0])): ?>
        <div class="mx-empty"><i class="fa-solid fa-sitemap"></i><p class="mb-0">No people without a manager found, so there is no root to draw. Add people or clear a manager to define the top of the tree.</p></div>
<?php else: ?>
<?php mx_org_branch($byManager, 0); ?>
<?php endif; ?>
    </div>
</div>
