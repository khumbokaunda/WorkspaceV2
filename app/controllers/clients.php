<?php
// Clients and the opportunity pipeline. Clients are the organisations you sell
// to or bid for (the procuring entities in tenders); opportunities are the
// light sales pipeline that may or may not become a formal tender. A tender
// links to an opportunity so the pipeline and the formal bid stay connected.

declare(strict_types=1);

function client_types(): array
{
    return ['Government', 'Parastatal', 'Private', 'NGO'];
}

function opportunity_stages(): array
{
    return ['Lead', 'Qualifying', 'Bidding', 'Won', 'Lost'];
}

// Active users with a readable name, for the opportunity owner selector.
function clients_owner_options(): array
{
    return db_all(
        "SELECT u.id, u.username, p.first_name, p.last_name
         FROM users u LEFT JOIN people p ON p.id = u.person_id
         WHERE u.is_active = 1 ORDER BY p.first_name, u.username"
    );
}

function clients_rules(): array
{
    return [
        'name' => 'required|max:200',
        'client_type' => 'in:Government;Parastatal;Private;NGO',
        'email' => 'email',
        'sector' => 'max:120',
        'main_contact' => 'max:160',
        'phone' => 'max:60',
    ];
}

function index(): void
{
    render('clients/index', [
        'pageTitle' => 'Clients',
        'breadcrumbs' => ['Sales' => null, 'Clients' => null],
        'canManage' => user_can('clients.manage'),
        'clientTypes' => client_types(),
        'stages' => opportunity_stages(),
        'owners' => clients_owner_options(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function list_json(): void
{
    $rows = db_all(
        "SELECT c.*,
                (SELECT COUNT(*) FROM client_contacts cc WHERE cc.client_id = c.id) AS contact_count,
                (SELECT COUNT(*) FROM opportunities o WHERE o.client_id = c.id) AS opportunity_count
         FROM clients c ORDER BY c.name"
    );
    json_out(['ok' => true, 'clients' => $rows]);
}

function show(string $id): void
{
    $clientId = (int)$id;
    $client = db_row('SELECT * FROM clients WHERE id = ?', [$clientId]);
    if (!$client) {
        render_error(404, 'Not found', 'That client does not exist.');
    }
    render('clients/show', [
        'pageTitle' => $client['name'],
        'breadcrumbs' => ['Sales' => null, 'Clients' => '/clients', $client['name'] => null],
        'client' => $client,
        'contacts' => db_all('SELECT * FROM client_contacts WHERE client_id = ? ORDER BY name', [$clientId]),
        'canManage' => user_can('clients.manage'),
        'stages' => opportunity_stages(),
        'owners' => clients_owner_options(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function create(): void
{
    $in = input();
    $errors = validate($in, clients_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $type = in_array(in_str('client_type'), client_types(), true) ? in_str('client_type') : 'Private';
    db_query(
        'INSERT INTO clients (name, client_type, sector, address, main_contact, phone, email, notes)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            in_str('name'), $type, in_str('sector') ?: null, in_str('address') ?: null,
            in_str('main_contact') ?: null, in_str('phone') ?: null, in_str('email') ?: null,
            in_str('notes') ?: null,
        ]
    );
    $clientId = db_insert_id();
    audit('client.create', 'client', $clientId, ['name' => in_str('name')]);
    json_ok(['client_id' => $clientId]);
}

function update(string $id): void
{
    $clientId = (int)$id;
    if (!db_val('SELECT id FROM clients WHERE id = ?', [$clientId])) {
        json_err('That client does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, clients_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $type = in_array(in_str('client_type'), client_types(), true) ? in_str('client_type') : 'Private';
    db_query(
        'UPDATE clients SET name = ?, client_type = ?, sector = ?, address = ?, main_contact = ?, phone = ?, email = ?, notes = ? WHERE id = ?',
        [
            in_str('name'), $type, in_str('sector') ?: null, in_str('address') ?: null,
            in_str('main_contact') ?: null, in_str('phone') ?: null, in_str('email') ?: null,
            in_str('notes') ?: null, $clientId,
        ]
    );
    audit('client.update', 'client', $clientId, ['name' => in_str('name')]);
    json_ok();
}

function destroy(string $id): void
{
    $clientId = (int)$id;
    $client = db_row('SELECT * FROM clients WHERE id = ?', [$clientId]);
    if (!$client) {
        json_err('That client does not exist.', 404);
    }
    db_query('DELETE FROM clients WHERE id = ?', [$clientId]);
    audit('client.delete', 'client', $clientId, ['name' => $client['name']]);
    json_ok();
}

function add_contact(string $id): void
{
    $clientId = (int)$id;
    if (!db_val('SELECT id FROM clients WHERE id = ?', [$clientId])) {
        json_err('That client does not exist.', 404);
    }
    $name = in_str('name');
    if ($name === '') {
        json_err('A contact name is required.', 422, ['name' => 'Required.']);
    }
    $email = in_str('email');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        json_err('Enter a valid email address.', 422, ['email' => 'Invalid email.']);
    }
    db_query(
        'INSERT INTO client_contacts (client_id, name, title, phone, email, notes) VALUES (?,?,?,?,?,?)',
        [$clientId, mb_substr($name, 0, 160), in_str('title') ?: null, in_str('phone') ?: null, $email ?: null, in_str('notes') ?: null]
    );
    $contactId = db_insert_id();
    audit('client_contact.create', 'client_contact', $contactId, ['client_id' => $clientId, 'name' => $name]);
    json_ok(['contact_id' => $contactId]);
}

function delete_contact(string $id): void
{
    $contactId = (int)$id;
    $contact = db_row('SELECT * FROM client_contacts WHERE id = ?', [$contactId]);
    if (!$contact) {
        json_err('That contact does not exist.', 404);
    }
    db_query('DELETE FROM client_contacts WHERE id = ?', [$contactId]);
    audit('client_contact.delete', 'client_contact', $contactId, ['client_id' => (int)$contact['client_id']]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Opportunities
// ---------------------------------------------------------------------------

function opportunities_json(): void
{
    // Filter comes in as a query string parameter on a GET request, so it is
    // read from $_GET, not the request body.
    $clientId = isset($_GET['client_id']) && ctype_digit((string)$_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    $where = $clientId ? 'WHERE o.client_id = ?' : '';
    $params = $clientId ? [$clientId] : [];
    $rows = db_all(
        "SELECT o.*, c.name AS client_name, c.client_type,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS owner_name
         FROM opportunities o
         LEFT JOIN clients c ON c.id = o.client_id
         LEFT JOIN users u ON u.id = o.owner_id
         LEFT JOIN people p ON p.id = u.person_id
         $where
         ORDER BY FIELD(o.stage,'Lead','Qualifying','Bidding','Won','Lost'), o.expected_decision_date IS NULL, o.expected_decision_date",
        $params
    );
    json_out(['ok' => true, 'opportunities' => $rows]);
}

function opportunity_input(): array
{
    $title = in_str('title');
    $errors = [];
    if ($title === '') {
        $errors['title'] = 'Required.';
    }
    $stage = in_str('stage');
    if ($stage !== '' && !in_array($stage, opportunity_stages(), true)) {
        $errors['stage'] = 'Invalid stage.';
    }
    $value = in_str('estimated_value');
    if ($value !== '' && !is_numeric($value)) {
        $errors['estimated_value'] = 'Enter a number.';
    }
    $decision = in_str('expected_decision_date');
    if ($decision !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $decision)) {
        $errors['expected_decision_date'] = 'Invalid date.';
    }
    $clientId = in_int('client_id');
    if ($clientId && !db_val('SELECT id FROM clients WHERE id = ?', [$clientId])) {
        $errors['client_id'] = 'Unknown client.';
    }
    $ownerId = in_int('owner_id');
    if ($ownerId && !db_val('SELECT id FROM users WHERE id = ? AND is_active = 1', [$ownerId])) {
        $errors['owner_id'] = 'Unknown owner.';
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'client_id' => $clientId ?: null,
        'title' => mb_substr($title, 0, 250),
        'estimated_value' => $value !== '' ? (float)$value : null,
        'currency' => strtoupper(in_str('currency')) ?: null,
        'source' => in_str('source') ?: null,
        'stage' => $stage ?: 'Lead',
        'expected_decision_date' => $decision ?: null,
        'owner_id' => $ownerId ?: null,
        'notes' => in_str('notes') ?: null,
    ];
}

function create_opportunity(): void
{
    $o = opportunity_input();
    if ($o['owner_id'] === null) {
        $o['owner_id'] = (int)current_user()['id'];
    }
    db_query(
        'INSERT INTO opportunities (client_id, title, estimated_value, currency, source, stage, expected_decision_date, owner_id, notes)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $o['client_id'], $o['title'], $o['estimated_value'], $o['currency'], $o['source'],
            $o['stage'], $o['expected_decision_date'], $o['owner_id'], $o['notes'],
        ]
    );
    $oppId = db_insert_id();
    audit('opportunity.create', 'opportunity', $oppId, ['title' => $o['title'], 'stage' => $o['stage']]);
    json_ok(['opportunity_id' => $oppId]);
}

function update_opportunity(string $id): void
{
    $oppId = (int)$id;
    $existing = db_row('SELECT * FROM opportunities WHERE id = ?', [$oppId]);
    if (!$existing) {
        json_err('That opportunity does not exist.', 404);
    }
    $o = opportunity_input();
    db_query(
        'UPDATE opportunities SET client_id = ?, title = ?, estimated_value = ?, currency = ?, source = ?,
                stage = ?, expected_decision_date = ?, owner_id = ?, notes = ? WHERE id = ?',
        [
            $o['client_id'], $o['title'], $o['estimated_value'], $o['currency'], $o['source'],
            $o['stage'], $o['expected_decision_date'], $o['owner_id'], $o['notes'], $oppId,
        ]
    );
    audit('opportunity.update', 'opportunity', $oppId, ['title' => $o['title'], 'stage' => $o['stage']]);
    json_ok();
}

function delete_opportunity(string $id): void
{
    $oppId = (int)$id;
    $opp = db_row('SELECT * FROM opportunities WHERE id = ?', [$oppId]);
    if (!$opp) {
        json_err('That opportunity does not exist.', 404);
    }
    db_query('DELETE FROM opportunities WHERE id = ?', [$oppId]);
    audit('opportunity.delete', 'opportunity', $oppId, ['title' => $opp['title']]);
    json_ok();
}
