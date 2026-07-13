<?php
// Payroll and HR home: an employee reaches their own records and payslips here;
// HR and payroll roles reach the people list, pay components, runs and loans.
$fmt = fn($v) => $v === null || $v === '' ? 'Not set' : $currency . ' ' . number_format((float)$v, 2);
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Payroll and HR</h1>
    <div class="d-flex gap-2 flex-wrap">
<?php if ($myPid): ?>
        <a href="/payroll/records/<?= (int)$myPid ?>" class="btn btn-outline-primary"><i class="fa-solid fa-id-card me-2"></i>My records</a>
<?php endif; ?>
<?php if ($canManage): ?>
        <a href="/payroll/components" class="btn btn-outline-primary"><i class="fa-solid fa-sliders me-2"></i>Pay components</a>
        <a href="/payroll/loans" class="btn btn-outline-primary"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Loans</a>
<?php endif; ?>
<?php if ($canViewAll): ?>
        <a href="/payroll/runs" class="btn btn-primary"><i class="fa-solid fa-money-check-dollar me-2"></i>Payroll runs</a>
<?php endif; ?>
    </div>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-body">
        <p class="mb-1" style="font-size:13px"><strong>Configurable engine.</strong> Pay is built from a catalogue of components. The seeded components and any tax bands are placeholders. Confirm the deduction setup with your accountant and against current law before running payroll for real.</p>
        <p class="mb-0 text-muted" style="font-size:12px">Statutory calculation mode: <strong><?= e(ucfirst($statutoryMode)) ?></strong>. In simple mode the module records salaries and issues payslips; in full mode it applies the configured statutory deductions.</p>
    </div>
</div>

<?php if ($myPid): ?>
<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>My payslips</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$myPayslips): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-file-invoice-dollar"></i><p class="mb-0">No payslips available yet. They appear once a payroll run is approved.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Period</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net pay</th><th></th></tr></thead>
            <tbody>
<?php foreach ($myPayslips as $ps): ?>
                <tr>
                    <td class="mx-tabular"><?= e($ps['period']) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($ps['gross'])) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($ps['total_deductions'])) ?></td>
                    <td class="text-end mx-tabular"><strong><?= e($fmt($ps['net_pay'])) ?></strong></td>
                    <td class="text-end"><a href="/payroll/payslips/<?= (int)$ps['id'] ?>" target="_blank" rel="noopener" class="btn btn-subtle btn-sm"><i class="fa-solid fa-file-lines me-1"></i>View</a></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canViewAll): ?>
<div class="mx-card">
    <div class="mx-card-header"><h2>People</h2></div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search name, department" aria-label="Search people">
    </div>
    <div class="mx-card-body mx-flush">
<?php if (!$people): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-users"></i><p class="mb-0">No active people.</p></div>
<?php else: ?>
        <table id="mx-payroll-people" class="table dt-host mx-stack align-middle" style="width:100%;font-size:13px">
            <thead><tr><th>Name</th><th>Job title</th><th>Department</th><th class="text-end">Basic salary</th></tr></thead>
            <tbody>
<?php foreach ($people as $p): ?>
                <tr>
                    <td data-label="Name"><a href="/payroll/records/<?= (int)$p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></a></td>
                    <td data-label="Job title"><?= e($p['job_title'] ?: '') ?></td>
                    <td data-label="Department"><?= e($p['department'] ?: '') ?></td>
                    <td data-label="Basic salary" class="text-end mx-tabular"><?= e($fmt($p['basic_salary'])) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('mx-payroll-people') && window.DataTable) {
        window.mxPayrollPeople = MX.table('#mx-payroll-people', {});
    }
});
</script>
