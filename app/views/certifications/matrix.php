<?php // Skills matrix: people against certification codes, answering "who can cover X". ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 style="font-size:20px" class="mb-0">Skills matrix</h1>
    <a href="/certifications" class="btn btn-outline-primary"><i class="fa-solid fa-list me-2"></i>All certifications</a>
</div>

<div class="mx-card">
    <div class="mx-card-body <?= $codes ? 'mx-matrix-wrap' : '' ?>">
<?php if (!$codes): ?>
        <div class="mx-empty">
            <i class="fa-solid fa-table-cells"></i>
            <p class="mb-0">The matrix appears once certifications with codes are recorded.</p>
        </div>
<?php else: ?>
        <table class="mx-matrix">
            <thead>
                <tr>
                    <th style="text-align:left;font-family:var(--mx-font)">Person</th>
<?php foreach ($codes as $code): ?>
                    <th scope="col"><?= e($code) ?></th>
<?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($people as $pid => $p): ?>
                <tr>
                    <th scope="row">
                        <a href="/people/<?= (int)$pid ?>" style="color:var(--mx-text)"><?= e($p['name']) ?></a>
                        <span class="text-muted d-block" style="font-size:11px;font-weight:400"><?= e($p['department'] ?: '') ?></span>
                    </th>
<?php foreach ($codes as $code): $cell = $cells[$pid . '|' . $code] ?? null; ?>
                    <td>
<?php if ($cell === 'Active'): ?>
                        <i class="fa-solid fa-circle-check mx-hold" title="Active" aria-label="Holds <?= e($code) ?>"></i>
<?php elseif ($cell === 'In Progress'): ?>
                        <i class="fa-solid fa-circle-half-stroke mx-hold-progress" title="In progress" aria-label="Working toward <?= e($code) ?>"></i>
<?php elseif ($cell === 'Expired'): ?>
                        <i class="fa-solid fa-circle-xmark mx-hold-expired" title="Expired" aria-label="Expired <?= e($code) ?>"></i>
<?php endif; ?>
                    </td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
        <div class="d-flex gap-3 mt-3 flex-wrap" style="font-size:12px">
            <span><i class="fa-solid fa-circle-check mx-hold me-1"></i>Active</span>
            <span><i class="fa-solid fa-circle-half-stroke mx-hold-progress me-1"></i>In progress</span>
            <span><i class="fa-solid fa-circle-xmark mx-hold-expired me-1"></i>Expired</span>
        </div>
<?php endif; ?>
    </div>
</div>
