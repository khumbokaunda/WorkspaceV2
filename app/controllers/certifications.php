<?php
// Certifications and skills: per-person management, the cross-team skills
// matrix, an in-progress view, and derived expiry status. Staff manage
// their own; certifications.manage_all manages anyone's.

declare(strict_types=1);

function certifications_rules(): array
{
    return [
        'name' => 'required|max:160',
        'code' => 'required|max:60',
        'issuing_body' => 'max:160',
        'earned_on' => 'date',
        'expires_on' => 'date',
        'credential_id' => 'max:120',
        'verify_url' => 'max:255',
        'status' => 'in:In Progress;Active;Expired',
    ];
}

// A user may edit a certification when they hold manage_all, or when it is
// their own record and they hold manage_own.
function certifications_can_touch(int $personId): bool
{
    if (user_can('certifications.manage_all')) {
        return true;
    }
    $me = current_user();
    return user_can('certifications.manage_own') && (int)($me['person_id'] ?? 0) === $personId;
}

function index(): void
{
    render('certifications/index', [
        'pageTitle' => 'Certifications and Skills',
        'breadcrumbs' => ['People' => null, 'Certifications' => null],
        'canManageAll' => user_can('certifications.manage_all'),
        'canManageOwn' => user_can('certifications.manage_own'),
        'people' => user_can('certifications.manage_all')
            ? db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name")
            : [],
        'myPersonId' => (int)(current_user()['person_id'] ?? 0),
    ]);
}

function list_json(): void
{
    $rows = db_all(
        "SELECT c.*, p.first_name, p.last_name, p.department
         FROM certifications c
         JOIN people p ON p.id = c.person_id
         WHERE p.employment_status <> 'Terminated'
         ORDER BY c.expires_on IS NULL, c.expires_on"
    );
    foreach ($rows as &$c) {
        $c['effective_status'] = cert_effective_status($c);
        $c['days_left'] = $c['expires_on'] !== null
            ? (int)floor((strtotime($c['expires_on']) - strtotime(date('Y-m-d'))) / 86400)
            : null;
    }
    json_out(['ok' => true, 'certifications' => $rows]);
}

function matrix(): void
{
    $rows = db_all(
        "SELECT c.person_id, c.code, c.status, c.expires_on, p.first_name, p.last_name, p.department
         FROM certifications c
         JOIN people p ON p.id = c.person_id
         WHERE p.employment_status = 'Active'
         ORDER BY p.first_name, c.code"
    );
    $codes = [];
    $people = [];
    $cells = [];
    foreach ($rows as $r) {
        $codes[$r['code']] = true;
        $people[$r['person_id']] = ['name' => $r['first_name'] . ' ' . $r['last_name'], 'department' => $r['department']];
        $status = cert_effective_status($r);
        // Best status wins if a person holds several with the same code.
        $rank = ['Active' => 3, 'In Progress' => 2, 'Expired' => 1];
        $key = $r['person_id'] . '|' . $r['code'];
        if (!isset($cells[$key]) || $rank[$status] > $rank[$cells[$key]]) {
            $cells[$key] = $status;
        }
    }
    ksort($codes);
    render('certifications/matrix', [
        'pageTitle' => 'Skills matrix',
        'breadcrumbs' => ['People' => null, 'Certifications' => '/certifications', 'Skills matrix' => null],
        'codes' => array_keys($codes),
        'people' => $people,
        'cells' => $cells,
    ]);
}

function create(): void
{
    $in = input();
    $errors = validate($in, certifications_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $personId = in_int('person_id') ?: (int)(current_user()['person_id'] ?? 0);
    if (!$personId || !db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('Choose a valid person.', 422, ['person_id' => 'Invalid person.']);
    }
    if (!certifications_can_touch($personId)) {
        json_err('You may only add certifications to your own profile.', 403);
    }
    $verifyUrl = in_str('verify_url');
    if ($verifyUrl !== '' && !preg_match('#^https?://#i', $verifyUrl)) {
        json_err('The verification link must start with http or https.', 422, ['verify_url' => 'Must be a web address.']);
    }
    db_query(
        'INSERT INTO certifications (person_id, name, issuing_body, code, earned_on, expires_on, credential_id, verify_url, status)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $personId, in_str('name'), in_str('issuing_body') ?: null, strtoupper(in_str('code')),
            in_str('earned_on') ?: null, in_str('expires_on') ?: null,
            in_str('credential_id') ?: null, $verifyUrl ?: null,
            in_str('status') ?: 'Active',
        ]
    );
    $certId = db_insert_id();
    audit('certification.create', 'certification', $certId, ['code' => strtoupper(in_str('code')), 'person_id' => $personId]);
    json_ok(['certification_id' => $certId]);
}

function update(string $id): void
{
    $certId = (int)$id;
    $cert = db_row('SELECT * FROM certifications WHERE id = ?', [$certId]);
    if (!$cert) {
        json_err('That certification does not exist.', 404);
    }
    if (!certifications_can_touch((int)$cert['person_id'])) {
        json_err('You may only edit your own certifications.', 403);
    }
    $in = input();
    $errors = validate($in, certifications_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $verifyUrl = in_str('verify_url');
    if ($verifyUrl !== '' && !preg_match('#^https?://#i', $verifyUrl)) {
        json_err('The verification link must start with http or https.', 422, ['verify_url' => 'Must be a web address.']);
    }
    db_query(
        'UPDATE certifications SET name = ?, issuing_body = ?, code = ?, earned_on = ?, expires_on = ?, credential_id = ?, verify_url = ?, status = ? WHERE id = ?',
        [
            in_str('name'), in_str('issuing_body') ?: null, strtoupper(in_str('code')),
            in_str('earned_on') ?: null, in_str('expires_on') ?: null,
            in_str('credential_id') ?: null, $verifyUrl ?: null,
            in_str('status') ?: $cert['status'], $certId,
        ]
    );
    audit('certification.update', 'certification', $certId, ['code' => strtoupper(in_str('code'))]);
    json_ok();
}

function destroy(string $id): void
{
    $certId = (int)$id;
    $cert = db_row('SELECT * FROM certifications WHERE id = ?', [$certId]);
    if (!$cert) {
        json_err('That certification does not exist.', 404);
    }
    if (!certifications_can_touch((int)$cert['person_id'])) {
        json_err('You may only remove your own certifications.', 403);
    }
    db_query('DELETE FROM certifications WHERE id = ?', [$certId]);
    audit('certification.delete', 'certification', $certId, ['code' => $cert['code'], 'person_id' => (int)$cert['person_id']]);
    json_ok();
}
