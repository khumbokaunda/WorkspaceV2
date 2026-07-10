<?php
// Tender pipeline analytics: win rate, value won and lost, and win rate by
// client type and by category, so the sales team sees where they compete well.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
$rate = fn($won, $lost) => ($won + $lost) > 0 ? round($won / ($won + $lost) * 100) : 0;
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Tender analytics</h1>
    <a href="/tenders" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-2"></i>All tenders</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value"><?= round($winRate) ?>%</span><span class="mx-stat-label">Win rate (decided)</span></div></div></div>
    <div class="col-6 col-md-3"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-success)"><?= (int)$won['count'] ?></span><span class="mx-stat-label">Won</span></div></div></div>
    <div class="col-6 col-md-3"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-danger)"><?= (int)$lost['count'] ?></span><span class="mx-stat-label">Lost</span></div></div></div>
    <div class="col-6 col-md-3"><div class="mx-card mx-card-body p-3"><div class="mx-stat"><span class="mx-stat-value" style="color:var(--mx-success)"><?= e($fmt($won['value'])) ?></span><span class="mx-stat-label">Value won</span></div></div></div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Pipeline by status</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$byStatus): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-chart-simple"></i><p class="mb-0">No tenders yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($byStatus as $s): ?>
                    <li class="d-flex justify-content-between px-3 py-2 border-bottom" style="font-size:13px"><span><?= e($s['status']) ?></span><span class="mx-tabular"><?= (int)$s['c'] ?></span></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Win rate by client type</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$byClientType): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-building"></i><p class="mb-0">No decided tenders yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($byClientType as $r): $wr = $rate((int)$r['won'], (int)$r['lost']); ?>
                    <li class="px-3 py-2 border-bottom" style="font-size:13px">
                        <div class="d-flex justify-content-between"><span><?= e($r['client_type']) ?></span><span class="mx-tabular"><?= $wr ?>% (<?= (int)$r['won'] ?>W / <?= (int)$r['lost'] ?>L)</span></div>
                        <div class="mt-1" style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden"><div style="width:<?= $wr ?>%;height:100%;background:var(--mx-success)"></div></div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="mx-card h-100">
            <div class="mx-card-header"><h2>Win rate by category</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$byCategory): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-tags"></i><p class="mb-0">No decided tenders yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($byCategory as $r): $wr = $rate((int)$r['won'], (int)$r['lost']); ?>
                    <li class="px-3 py-2 border-bottom" style="font-size:13px">
                        <div class="d-flex justify-content-between"><span><?= e($r['category']) ?></span><span class="mx-tabular"><?= $wr ?>% (<?= (int)$r['won'] ?>W / <?= (int)$r['lost'] ?>L)</span></div>
                        <div class="mt-1" style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden"><div style="width:<?= $wr ?>%;height:100%;background:var(--mx-primary)"></div></div>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>
