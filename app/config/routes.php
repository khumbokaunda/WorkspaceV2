<?php
// Route table. Each route: [method, path, 'controller_file@function', [middleware...]].
// Middleware runs in order. 'auth' requires a session. 'csrf' verifies the
// token (declare on every write). 'rbac:<permission>' checks the permission.
// 'module:<key>' additionally requires the module to be visible to the user,
// so hiding a module also blocks direct URL access.
// {id} matches digits, {token} matches url-safe base64.

return [
    // First-run setup (reachable only until setup is completed)
    ['GET',    '/setup',                      'setup@index',                []],
    ['POST',   '/setup',                      'setup@submit',               ['csrf']],

    // Auth
    ['GET',    '/login',                      'auth@login_form',            []],
    ['POST',   '/login',                      'auth@login_submit',          ['throttle']],
    ['POST',   '/logout',                     'auth@logout',                ['auth', 'csrf']],
    ['GET',    '/account/password',           'auth@password_form',         ['auth']],
    ['POST',   '/account/password',           'auth@password_submit',       ['auth', 'csrf']],

    // Two-factor: challenge at login, and enrolment from the account area.
    ['GET',    '/login/verify',               'auth@totp_challenge_form',   []],
    ['POST',   '/login/verify',               'auth@totp_challenge_submit', ['throttle', 'csrf']],
    ['GET',    '/account/two-factor',          'auth@totp_setup_form',           ['auth']],
    ['POST',   '/account/two-factor/enable',   'auth@totp_enable',               ['auth', 'csrf']],
    ['POST',   '/account/two-factor/disable',  'auth@totp_disable',              ['auth', 'csrf']],
    ['POST',   '/account/two-factor/recovery', 'auth@totp_regenerate_recovery',  ['auth', 'csrf']],
    ['GET',    '/forgot-password',            'auth@forgot_form',           []],
    ['POST',   '/forgot-password',            'auth@forgot_submit',         ['throttle']],
    ['GET',    '/reset-password/{token}',     'auth@reset_form',            []],
    ['POST',   '/reset-password',             'auth@reset_submit',          ['throttle']],

    // Dashboard and shell services
    ['GET',    '/',                           'dashboard@home',             ['auth']],
    ['GET',    '/dashboard',                  'dashboard@index',            ['auth', 'rbac:dashboard.view', 'module:dashboard']],
    ['GET',    '/api/search',                 'search@palette',             ['auth']],
    ['GET',    '/notifications',              'notifications@list_json',    ['auth']],
    ['GET',    '/notifications/unseen-count', 'notifications@unseen_count',  ['auth']],
    ['POST',   '/notifications/seen',         'notifications@mark_seen',     ['auth', 'csrf']],
    ['POST',   '/notifications/read-all',     'notifications@mark_all_read', ['auth', 'csrf']],
    ['POST',   '/notifications/{id}/read',    'notifications@mark_one_read', ['auth', 'csrf']],

    // People
    ['GET',    '/people',                     'people@index',               ['auth', 'rbac:people.view', 'module:people']],
    ['GET',    '/people/org-chart',           'people@org_chart',           ['auth', 'rbac:people.view', 'module:people']],
    ['GET',    '/people/{id}',                'people@show',                ['auth', 'rbac:people.view', 'module:people']],
    ['GET',    '/api/people',                 'people@list_json',           ['auth', 'rbac:people.view']],
    ['GET',    '/api/people/{id}',            'people@detail_json',         ['auth', 'rbac:people.view']],
    ['POST',   '/people',                     'people@create',              ['auth', 'csrf', 'rbac:people.create']],
    ['PATCH',  '/people/{id}',                'people@update',              ['auth', 'csrf', 'rbac:people.edit']],
    ['POST',   '/people/{id}/terminate',      'people@terminate',           ['auth', 'csrf', 'rbac:people.terminate']],

    // Documents (vault on the person)
    ['POST',   '/people/{id}/documents',      'documents@upload',           ['auth', 'csrf', 'rbac:documents.upload']],
    ['GET',    '/api/people/{id}/documents',  'documents@list_json',        ['auth', 'rbac:documents.view']],
    ['POST',   '/documents/{id}/link',        'documents@issue_link',       ['auth', 'csrf', 'rbac:documents.view']],
    ['DELETE', '/documents/{id}',             'documents@destroy',          ['auth', 'csrf', 'rbac:documents.view_all']],
    ['GET',    '/files/{token}',              'documents@download',         ['auth']],

    // Attendance
    ['GET',    '/attendance',                 'attendance@index',           ['auth', 'rbac:attendance.view', 'module:attendance']],
    ['POST',   '/attendance/check-in',        'attendance@check_in',        ['auth', 'csrf', 'rbac:attendance.record']],
    ['POST',   '/attendance/check-out',       'attendance@check_out',       ['auth', 'csrf', 'rbac:attendance.record']],
    ['GET',    '/api/attendance/me',          'attendance@me_json',         ['auth', 'rbac:attendance.view']],
    ['GET',    '/api/attendance/team',        'attendance@team_json',       ['auth', 'rbac:attendance.view_all']],
    ['PATCH',  '/attendance/{id}',            'attendance@correct',         ['auth', 'csrf', 'rbac:attendance.correct']],

    // Leave
    ['GET',    '/leave',                      'leave@index',                ['auth', 'rbac:leave.view', 'module:leave']],
    ['POST',   '/leave',                      'leave@request_leave',        ['auth', 'csrf', 'rbac:leave.request']],
    ['POST',   '/leave/{id}/approve',         'leave@approve',              ['auth', 'csrf', 'rbac:leave.approve']],
    ['POST',   '/leave/{id}/reject',          'leave@reject',               ['auth', 'csrf', 'rbac:leave.approve']],
    ['POST',   '/leave/{id}/cancel',          'leave@cancel',               ['auth', 'csrf', 'rbac:leave.request']],
    ['GET',    '/api/leave/balance',          'leave@balance_json',         ['auth', 'rbac:leave.view']],
    ['GET',    '/api/leave/calendar',         'leave@calendar_json',        ['auth', 'rbac:leave.view']],
    ['GET',    '/api/leave/list',             'leave@list_json',            ['auth', 'rbac:leave.view']],

    // Projects and tasks
    ['GET',    '/projects',                   'projects@index',             ['auth', 'rbac:projects.view', 'module:projects']],
    ['GET',    '/projects/{id}',              'projects@show',              ['auth', 'rbac:projects.view', 'module:projects']],
    ['POST',   '/projects',                   'projects@create',            ['auth', 'csrf', 'rbac:projects.manage']],
    ['PATCH',  '/projects/{id}',              'projects@update',            ['auth', 'csrf', 'rbac:projects.manage']],
    ['GET',    '/api/tasks',                  'tasks@list_json',            ['auth', 'rbac:projects.view']],
    ['GET',    '/api/tasks/{id}',             'tasks@detail_json',          ['auth', 'rbac:projects.view']],
    ['POST',   '/tasks',                      'tasks@create',               ['auth', 'csrf', 'rbac:tasks.create']],
    ['PATCH',  '/tasks/{id}',                 'tasks@update',               ['auth', 'csrf', 'rbac:tasks.create']],
    ['PATCH',  '/tasks/{id}/status',          'tasks@set_status',           ['auth', 'csrf', 'rbac:tasks.create']],
    ['POST',   '/tasks/{id}/comments',        'tasks@add_comment',          ['auth', 'csrf', 'rbac:tasks.comment']],

    // Assets
    ['GET',    '/assets',                     'assets@index',               ['auth', 'rbac:assets.view', 'module:assets']],
    ['GET',    '/api/assets',                 'assets@list_json',           ['auth', 'rbac:assets.view']],
    ['GET',    '/api/assets/{id}',            'assets@detail_json',         ['auth', 'rbac:assets.view']],
    ['POST',   '/assets',                     'assets@create',              ['auth', 'csrf', 'rbac:assets.manage']],
    ['PATCH',  '/assets/{id}',                'assets@update',              ['auth', 'csrf', 'rbac:assets.manage']],
    ['POST',   '/assets/{id}/assign',         'assets@assign',              ['auth', 'csrf', 'rbac:assets.assign']],
    ['POST',   '/assets/{id}/return',         'assets@return_asset',        ['auth', 'csrf', 'rbac:assets.assign']],
    ['POST',   '/assets/import',              'assets@import_csv',          ['auth', 'csrf', 'rbac:assets.manage']],

    // Certifications
    ['GET',    '/certifications',             'certifications@index',       ['auth', 'rbac:certifications.view', 'module:certifications']],
    ['GET',    '/certifications/matrix',      'certifications@matrix',      ['auth', 'rbac:certifications.view', 'module:certifications']],
    ['GET',    '/api/certifications',         'certifications@list_json',   ['auth', 'rbac:certifications.view']],
    ['POST',   '/certifications',             'certifications@create',      ['auth', 'csrf', 'rbac:certifications.manage_own']],
    ['PATCH',  '/certifications/{id}',        'certifications@update',      ['auth', 'csrf', 'rbac:certifications.manage_own']],
    ['DELETE', '/certifications/{id}',        'certifications@destroy',     ['auth', 'csrf', 'rbac:certifications.manage_own']],

    // Admin
    ['GET',    '/admin/users',                'admin_users@index',          ['auth', 'rbac:admin.users', 'module:admin_users']],
    ['POST',   '/admin/users',                'admin_users@create',         ['auth', 'csrf', 'rbac:admin.users']],
    ['PATCH',  '/admin/users/{id}',           'admin_users@update',         ['auth', 'csrf', 'rbac:admin.users']],
    ['POST',   '/admin/users/{id}/reset-password', 'admin_users@reset_password', ['auth', 'csrf', 'rbac:admin.users']],
    ['GET',    '/admin/roles',                'admin_roles@index',          ['auth', 'rbac:admin.roles', 'module:admin_roles']],
    ['POST',   '/admin/roles/permissions',    'admin_roles@save_permissions', ['auth', 'csrf', 'rbac:admin.roles']],
    ['POST',   '/admin/roles/visibility',     'admin_roles@save_visibility',  ['auth', 'csrf', 'rbac:admin.roles']],
    ['POST',   '/admin/users/{id}/overrides', 'admin_roles@save_user_overrides', ['auth', 'csrf', 'rbac:admin.roles']],
    ['GET',    '/api/admin/users/{id}/access','admin_roles@user_access_json', ['auth', 'rbac:admin.roles']],
    ['GET',    '/admin/audit',                'admin_audit@index',          ['auth', 'rbac:admin.audit', 'module:admin_audit']],
    ['GET',    '/api/admin/audit',            'admin_audit@list_json',      ['auth', 'rbac:admin.audit']],
    ['GET',    '/admin/settings',             'admin_settings@index',       ['auth', 'rbac:admin.settings', 'module:admin_settings']],
    ['POST',   '/admin/settings',             'admin_settings@save',        ['auth', 'csrf', 'rbac:admin.settings']],
    ['POST',   '/admin/settings/leave-types', 'admin_settings@save_leave_type', ['auth', 'csrf', 'rbac:admin.settings']],
    ['GET',    '/admin/modules',              'admin_settings@modules',         ['auth', 'rbac:admin.settings', 'module:admin_settings']],
    ['POST',   '/admin/modules',              'admin_settings@save_modules',    ['auth', 'csrf', 'rbac:admin.settings']],
    ['GET',    '/admin/about',                'admin_settings@about',           ['auth', 'rbac:admin.settings', 'module:admin_settings']],
    ['POST',   '/admin/about/license',        'admin_settings@save_license',    ['auth', 'csrf', 'rbac:admin.settings']],
    ['GET',    '/api/admin/system-check',     'admin_settings@system_check',    ['auth', 'rbac:admin.settings']],
    ['POST',   '/api/admin/test-asset',       'admin_settings@test_asset_save', ['auth', 'csrf', 'rbac:admin.settings']],
];
