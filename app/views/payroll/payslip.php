<?php
// Printable payslip, rendered without the app shell. The browser prints it to
// PDF. Earnings and deductions come from the stored breakdown.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
$earnings = $breakdown['earnings'] ?? [];
$deductions = $breakdown['deductions'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip <?= e($payslip['period']) ?> | <?= e($payslip['person_name']) ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: Inter, Arial, Helvetica, sans-serif; color: #0F172A; background: #fff; margin: 0; padding: 32px; font-size: 13px; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #4F46E5; margin: 20px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 8px; border-bottom: 1px solid #E2E8F0; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; color: #475569; }
        .right { text-align: right; }
        .muted { color: #64748B; }
        .totals td { border: none; padding: 3px 8px; }
        .net { font-size: 16px; font-weight: 700; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #4F46E5; padding-bottom: 12px; margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .toolbar { margin-bottom: 16px; }
        .btn { font: inherit; padding: 8px 16px; border-radius: 8px; border: 1px solid #4F46E5; background: #4F46E5; color: #fff; cursor: pointer; text-decoration: none; }
        .btn.secondary { background: #fff; color: #4F46E5; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button class="btn" onclick="window.print()">Print or save as PDF</button>
    <a class="btn secondary" href="/payroll">Back to payroll</a>
</div>

<div class="head">
    <div>
        <h1><?= e($profile['trading_name'] ?? ($profile['legal_name'] ?? 'Company')) ?></h1>
        <div class="muted"><?= e($profile['legal_name'] ?? '') ?></div>
    </div>
    <div class="right">
        <div><strong>Payslip</strong></div>
        <div class="muted">Period <?= e($payslip['period']) ?></div>
        <div class="muted">Status <?= e($payslip['run_status']) ?></div>
    </div>
</div>

<div class="grid" style="margin-bottom:8px">
    <div>
        <div><strong><?= e($payslip['person_name']) ?></strong></div>
        <div class="muted"><?= e($payslip['job_title'] ?: '') ?><?= $payslip['department'] ? ' &middot; ' . e($payslip['department']) : '' ?></div>
    </div>
    <div class="right">
        <div class="muted">Basic salary</div>
        <div><strong><?= e($fmt($payslip['basic'])) ?></strong></div>
    </div>
</div>

<div class="grid">
    <div>
        <h2>Earnings</h2>
        <table>
            <tbody>
                <tr><td>Basic salary</td><td class="right"><?= e($fmt($payslip['basic'])) ?></td></tr>
<?php foreach ($earnings as $e): ?>
                <tr><td><?= e($e['name']) ?></td><td class="right"><?= e($fmt($e['amount'])) ?></td></tr>
<?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Gross</th><th class="right"><?= e($fmt($payslip['gross'])) ?></th></tr></tfoot>
        </table>
    </div>
    <div>
        <h2>Deductions</h2>
        <table>
            <tbody>
<?php if (!$deductions): ?>
                <tr><td class="muted" colspan="2">None</td></tr>
<?php else: foreach ($deductions as $d): ?>
                <tr><td><?= e($d['name']) ?></td><td class="right"><?= e($fmt($d['amount'])) ?></td></tr>
<?php endforeach; endif; ?>
            </tbody>
            <tfoot><tr><th>Total deductions</th><th class="right"><?= e($fmt($payslip['total_deductions'])) ?></th></tr></tfoot>
        </table>
    </div>
</div>

<table class="totals" style="margin-top:20px;max-width:320px;margin-left:auto">
    <tr><td class="muted">Gross</td><td class="right"><?= e($fmt($payslip['gross'])) ?></td></tr>
    <tr><td class="muted">Deductions</td><td class="right"><?= e($fmt($payslip['total_deductions'])) ?></td></tr>
    <tr><td class="net">Net pay</td><td class="right net"><?= e($fmt($payslip['net_pay'])) ?></td></tr>
</table>

<p class="muted" style="margin-top:24px;font-size:11px">This payslip is computer generated. Deductions are based on the configured pay components; confirm statutory figures against current law.</p>
</body>
</html>
