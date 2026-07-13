<?php
// Compiled tender pack: a standalone printable page assembled from the company
// profile, the compliance matrix, the price schedule, the document checklist
// and the proposed team. Client facing, so internal cost and margin never
// appear. The browser prints this to PDF. Rendered without the app shell.
$fmt = fn($v) => $v === null || $v === '' ? '' : $currency . ' ' . number_format((float)$v, 2);
$num = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tender pack | <?= e($tender['title']) ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: Inter, Arial, Helvetica, sans-serif; color: #0F172A; background: #fff; margin: 0; padding: 32px; font-size: 13px; line-height: 1.5; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 28px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #4F46E5; color: #4F46E5; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
        th { background: #F8FAFC; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #475569; }
        .right { text-align: right; }
        .muted { color: #64748B; }
        .tag { display: inline-block; font-size: 11px; padding: 1px 8px; border: 1px solid #CBD5E1; border-radius: 999px; }
        .cover { border: 1px solid #E2E8F0; border-radius: 8px; padding: 20px; margin-bottom: 12px; }
        .logo { max-height: 64px; margin-bottom: 12px; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: 2px 12px; margin: 8px 0 0; }
        dt { color: #64748B; }
        .toolbar { position: sticky; top: 0; background: #fff; padding-bottom: 12px; margin-bottom: 8px; border-bottom: 1px solid #E2E8F0; }
        .btn { font: inherit; padding: 8px 16px; border-radius: 8px; border: 1px solid #4F46E5; background: #4F46E5; color: #fff; cursor: pointer; }
        .btn.secondary { background: #fff; color: #4F46E5; }
        tfoot th, tfoot td { border-top: 2px solid #CBD5E1; }
        @media print { .toolbar { display: none; } body { padding: 0; } h2 { break-after: avoid; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button class="btn" onclick="window.print()">Print or save as PDF</button>
    <a class="btn secondary" href="/tenders/<?= (int)$tender['id'] ?>" style="text-decoration:none">Back to tender</a>
</div>

<div class="cover">
<?php
// Documents use the branding light logo when set, then the legacy logo path.
// The legal name, never the display name, identifies the entity on documents.
$docLogo = brand_slot_filled('logo_light') ? brand_asset('full', 'light') : (!empty($profile['logo_path']) ? $profile['logo_path'] : null);
?>
<?php if ($docLogo): ?>
    <img class="logo" src="<?= e($docLogo) ?>" alt="Company logo">
<?php endif; ?>
    <h1><?= e($profile['trading_name'] ?? ($profile['legal_name'] ?? 'Company')) ?></h1>
    <div class="muted">
        <?= e($profile['legal_name'] ?? '') ?>
        <?= !empty($profile['reg_number']) ? ' &middot; Reg ' . e($profile['reg_number']) : '' ?>
        <?= !empty($profile['tax_id']) ? ' &middot; Tax ' . e($profile['tax_id']) : '' ?>
    </div>
    <div class="muted">
        <?= e($profile['phys_address'] ?? '') ?>
        <?= !empty($profile['phone']) ? ' &middot; ' . e($profile['phone']) : '' ?>
        <?= !empty($profile['email']) ? ' &middot; ' . e($profile['email']) : '' ?>
    </div>
<?php if (!empty($profile['overview'])): ?>
    <p style="margin-bottom:0"><?= e($profile['overview']) ?></p>
<?php endif; ?>
</div>

<h1 style="font-size:18px"><?= e($tender['title']) ?></h1>
<dl>
    <dt>Tender reference</dt><dd><?= e($tender['reference_number'] ?: 'Not set') ?></dd>
    <dt>Procuring entity</dt><dd><?= e($tender['client_name'] ?: 'Not set') ?></dd>
    <dt>Closing date</dt><dd><?= e($tender['closing_date'] ?: 'Not set') ?></dd>
    <dt>Submission method</dt><dd><?= e($tender['submission_method']) ?></dd>
    <dt>Tender validity</dt><dd><?= $tender['tender_validity_days'] !== null ? (int)$tender['tender_validity_days'] . ' days' : 'Not set' ?></dd>
</dl>

<h2>Compliance matrix</h2>
<?php if (!$requirements): ?>
<p class="muted">No requirements recorded.</p>
<?php else: ?>
<table>
    <thead><tr><th>Requirement</th><th>Category</th><th>Compliance</th><th>Evidence</th></tr></thead>
    <tbody>
<?php foreach ($requirements as $r): ?>
        <tr>
            <td><?= (int)$r['is_mandatory'] === 1 ? '<strong>* </strong>' : '' ?><?= e($r['requirement_text']) ?></td>
            <td><?= e($r['category']) ?></td>
            <td><?= e($r['our_compliance']) ?></td>
            <td class="muted"><?= e($r['evidence_reference'] ?: '') ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<p class="muted" style="font-size:11px">* Mandatory requirement.</p>
<?php endif; ?>

<h2>Price schedule</h2>
<?php if (!$boq): ?>
<p class="muted">No priced lines recorded.</p>
<?php else: ?>
<table>
    <thead><tr><th>Item</th><th>Description</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Line total</th></tr></thead>
    <tbody>
<?php foreach ($boq as $b): ?>
        <tr>
            <td class="muted"><?= e($b['item_no'] ?: '') ?></td>
            <td><?= e($b['description']) ?><?= (int)$b['is_optional'] === 1 ? ' <span class="tag">optional</span>' : '' ?><?= $b['specification'] ? '<div class="muted" style="font-size:11px">' . e($b['specification']) . '</div>' : '' ?></td>
            <td class="right"><?= $num($b['quantity']) ?><?= $b['unit'] ? ' ' . e($b['unit']) : '' ?></td>
            <td class="right"><?= e($fmt($b['unit_price'])) ?></td>
            <td class="right"><?= e($fmt($b['line_total'])) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr><th colspan="4" class="right">Subtotal</th><th class="right"><?= e($fmt($boqTotals['subtotal'])) ?></th></tr>
<?php if ($tender['vat_percent'] !== null): ?>
        <tr><td colspan="4" class="right muted">VAT (<?= $num($tender['vat_percent']) ?>%)</td><td class="right"><?= e($fmt($boqTotals['vat'])) ?></td></tr>
<?php endif; ?>
        <tr><th colspan="4" class="right">Grand total</th><th class="right"><?= e($fmt($boqTotals['grand_total'])) ?></th></tr>
<?php if ($boqTotals['optional'] > 0): ?>
        <tr><td colspan="4" class="right muted">Optional lines (not in total)</td><td class="right muted"><?= e($fmt($boqTotals['optional'])) ?></td></tr>
<?php endif; ?>
    </tfoot>
</table>
<?php endif; ?>

<h2>Document checklist</h2>
<?php if (!$checklist): ?>
<p class="muted">No documents listed.</p>
<?php else: ?>
<table>
    <thead><tr><th>Document</th><th>Source</th><th>Status</th></tr></thead>
    <tbody>
<?php foreach ($checklist as $d): ?>
        <tr>
            <td><?= e($d['item_label']) ?></td>
            <td class="muted"><?= e($d['source_kind']) ?><?= $d['source_label'] ? ': ' . e($d['source_label']) : '' ?></td>
            <td><?= (int)$d['is_ready'] === 1 ? 'Ready' : 'Outstanding' ?><?= $d['expiry_flag'] === 'Expired' ? ' <span class="tag">expired</span>' : '' ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Proposed team</h2>
<?php if (!$team): ?>
<p class="muted">No team proposed.</p>
<?php else: ?>
<table>
    <thead><tr><th>Name</th><th>Proposed role</th><th>Position</th></tr></thead>
    <tbody>
<?php foreach ($team as $m): ?>
        <tr>
            <td><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td>
            <td><?= e($m['proposed_role']) ?></td>
            <td class="muted"><?= e($m['job_title'] ?: '') ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>
