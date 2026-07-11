<?php
// Module and dashboard widget catalog. Order here is the sidebar and
// dashboard order. 'section' groups the sidebar. 'nav' false means the entry
// is a dashboard widget rather than a navigation item. 'permission' is the
// base permission that must also hold for the module to appear; rbac
// middleware enforces the real per-route checks server side.

return [
    // Navigation: Work
    'dashboard'      => ['nav' => true,  'section' => 'Work',   'label' => 'Dashboard',              'icon' => 'fa-gauge-high',      'path' => '/dashboard',      'permission' => 'dashboard.view'],
    'attendance'     => ['nav' => true,  'section' => 'Work',   'label' => 'Attendance',             'icon' => 'fa-clock',           'path' => '/attendance',     'permission' => 'attendance.view'],
    'leave'          => ['nav' => true,  'section' => 'Work',   'label' => 'Leave',                  'icon' => 'fa-umbrella-beach',  'path' => '/leave',          'permission' => 'leave.view'],
    'projects'       => ['nav' => true,  'section' => 'Work',   'label' => 'Projects and Tasks',     'icon' => 'fa-list-check',      'path' => '/projects',       'permission' => 'projects.view'],
    'timesheets'     => ['nav' => true,  'section' => 'Work',   'label' => 'Timesheets',             'icon' => 'fa-business-time',   'path' => '/timesheets',     'permission' => 'timesheets.log'],
    'helpdesk'       => ['nav' => true,  'section' => 'Work',   'label' => 'Helpdesk',               'icon' => 'fa-headset',         'path' => '/helpdesk',       'permission' => 'helpdesk.raise'],
    'bookings'       => ['nav' => true,  'section' => 'Work',   'label' => 'Room Booking',           'icon' => 'fa-calendar-check',  'path' => '/bookings',       'permission' => 'bookings.book'],

    // Navigation: People
    'people'         => ['nav' => true,  'section' => 'People', 'label' => 'Directory',              'icon' => 'fa-users',           'path' => '/people',         'permission' => 'people.view'],
    'certifications' => ['nav' => true,  'section' => 'People', 'label' => 'Certifications',         'icon' => 'fa-certificate',     'path' => '/certifications', 'permission' => 'certifications.view'],
    'payroll'        => ['nav' => true,  'section' => 'People', 'label' => 'Payroll and HR',         'icon' => 'fa-money-check-dollar', 'path' => '/payroll',     'permission' => 'payroll.view_own'],
    'appraisals'     => ['nav' => true,  'section' => 'People', 'label' => 'Appraisals',             'icon' => 'fa-star-half-stroke',  'path' => '/appraisals',  'permission' => 'appraisals.view_own'],
    'training'       => ['nav' => true,  'section' => 'People', 'label' => 'Training',               'icon' => 'fa-graduation-cap',  'path' => '/training',       'permission' => 'training.view_own'],

    // Navigation: Sales
    'clients'        => ['nav' => true,  'section' => 'Sales',  'label' => 'Clients',                'icon' => 'fa-handshake',       'path' => '/clients',        'permission' => 'clients.view'],
    'tenders'        => ['nav' => true,  'section' => 'Sales',  'label' => 'Tenders',                'icon' => 'fa-file-signature',  'path' => '/tenders',        'permission' => 'tenders.view'],

    // Navigation: Company
    'company_docs'   => ['nav' => true,  'section' => 'Company','label' => 'Compliance Library',     'icon' => 'fa-folder-tree',     'path' => '/company/documents', 'permission' => 'company_docs.view'],
    'contracts'      => ['nav' => true,  'section' => 'Company','label' => 'Contracts',              'icon' => 'fa-file-contract',   'path' => '/contracts',      'permission' => 'contracts.view'],
    'noticeboard'    => ['nav' => true,  'section' => 'Company','label' => 'Noticeboard',            'icon' => 'fa-bullhorn',        'path' => '/noticeboard',    'permission' => 'noticeboard.view'],

    // Navigation: Procurement
    'suppliers'      => ['nav' => true,  'section' => 'Procurement', 'label' => 'Suppliers',         'icon' => 'fa-truck-field',     'path' => '/suppliers',      'permission' => 'suppliers.view'],
    'procurement'    => ['nav' => true,  'section' => 'Procurement', 'label' => 'Procurement',       'icon' => 'fa-cart-flatbed',    'path' => '/procurement',    'permission' => 'procurement.view'],
    'budgets'        => ['nav' => true,  'section' => 'Procurement', 'label' => 'Budgets',           'icon' => 'fa-money-bill-trend-up', 'path' => '/budgets',    'permission' => 'budgets.view'],

    // Navigation: Finance
    'expenses'       => ['nav' => true,  'section' => 'Finance', 'label' => 'Expenses',              'icon' => 'fa-receipt',         'path' => '/expenses',       'permission' => 'expenses.submit'],

    // Navigation: Assets
    'assets'         => ['nav' => true,  'section' => 'Assets', 'label' => 'Asset Register',         'icon' => 'fa-laptop',          'path' => '/assets',         'permission' => 'assets.view'],
    'fleet'          => ['nav' => true,  'section' => 'Assets', 'label' => 'Fleet',                  'icon' => 'fa-car',             'path' => '/fleet',          'permission' => 'fleet.view'],

    // Navigation: Admin
    'admin_users'    => ['nav' => true,  'section' => 'Admin',  'label' => 'Users and Access',       'icon' => 'fa-user-shield',     'path' => '/admin/users',    'permission' => 'admin.users'],
    'admin_roles'    => ['nav' => true,  'section' => 'Admin',  'label' => 'Roles and Permissions',  'icon' => 'fa-key',             'path' => '/admin/roles',    'permission' => 'admin.roles'],
    'admin_audit'    => ['nav' => true,  'section' => 'Admin',  'label' => 'Audit Log',              'icon' => 'fa-clipboard-list',  'path' => '/admin/audit',    'permission' => 'admin.audit'],
    'admin_settings' => ['nav' => true,  'section' => 'Admin',  'label' => 'Settings',               'icon' => 'fa-gear',            'path' => '/admin/settings', 'permission' => 'admin.settings'],

    // Dashboard widgets (not navigation)
    'widget.attendance'        => ['nav' => false, 'label' => 'My attendance',          'permission' => 'attendance.record'],
    'widget.my_tasks'          => ['nav' => false, 'label' => 'My tasks',               'permission' => 'projects.view'],
    'widget.my_leave'          => ['nav' => false, 'label' => 'My leave',               'permission' => 'leave.view'],
    'widget.cert_expiry'       => ['nav' => false, 'label' => 'Certification expiry',   'permission' => 'certifications.view'],
    'widget.approvals'         => ['nav' => false, 'label' => 'Approvals queue',        'permission' => 'leave.approve'],
    'widget.org_overview'      => ['nav' => false, 'label' => 'Organization overview',  'permission' => 'attendance.view_all'],
    'widget.task_throughput'   => ['nav' => false, 'label' => 'Task throughput',        'permission' => 'projects.view'],
    'widget.asset_utilization' => ['nav' => false, 'label' => 'Asset utilization',      'permission' => 'assets.view'],
    'widget.compliance_expiry' => ['nav' => false, 'label' => 'Compliance documents',   'permission' => 'company_docs.view'],
    'widget.tender_deadlines'  => ['nav' => false, 'label' => 'Tender deadlines',        'permission' => 'tenders.view'],
    'widget.procurement_approvals' => ['nav' => false, 'label' => 'Requisition approvals', 'permission' => 'procurement.approve'],
    'widget.expense_approvals' => ['nav' => false, 'label' => 'Expense approvals',       'permission' => 'expenses.approve'],
    'widget.contract_renewals' => ['nav' => false, 'label' => 'Contract renewals',       'permission' => 'contracts.view'],
    'widget.helpdesk_queue'    => ['nav' => false, 'label' => 'Helpdesk queue',          'permission' => 'helpdesk.manage'],
    'widget.fleet_expiries'    => ['nav' => false, 'label' => 'Fleet expiries',          'permission' => 'fleet.view'],
];
