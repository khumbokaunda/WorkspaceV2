<?php
// Asset register: check-out style assignment workflow, per-asset history
// timeline, warranty tracking, and CSV bulk import with a preview step.

declare(strict_types=1);

const ASSET_CATEGORIES = ['Laptop', 'Desktop', 'Monitor', 'Phone', 'Network', 'Peripheral', 'Furniture', 'Other'];
const ASSET_STATUSES   = ['Available', 'Assigned', 'In Repair', 'Retired'];

function assets_rules(): array
{
    return [
        'asset_tag' => 'required|max:60',
        'name' => 'required|max:160',
        'category' => 'in:' . implode(';', ASSET_CATEGORIES),
        'serial_number' => 'max:120',
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'note' => 'max:500',
    ];
}

function index(): void
{
    render('assets/index', [
        'pageTitle' => 'Asset Register',
        'breadcrumbs' => ['Assets' => null, 'Asset Register' => null],
        'canManage' => user_can('assets.manage'),
        'canAssign' => user_can('assets.assign'),
        'people' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name"),
        'categories' => ASSET_CATEGORIES,
    ]);
}

function list_json(): void
{
    $assets = db_all(
        'SELECT a.*, p.first_name AS holder_first, p.last_name AS holder_last, aa.person_id AS holder_id
         FROM assets a
         LEFT JOIN asset_assignments aa ON aa.asset_id = a.id AND aa.returned_at IS NULL
         LEFT JOIN people p ON p.id = aa.person_id
         ORDER BY a.asset_tag'
    );
    json_out(['ok' => true, 'assets' => $assets, 'today' => date('Y-m-d')]);
}

function detail_json(string $id): void
{
    $asset = db_row('SELECT * FROM assets WHERE id = ?', [(int)$id]);
    if (!$asset) {
        json_err('That asset does not exist.', 404);
    }
    $history = db_all(
        'SELECT aa.assigned_at, aa.returned_at, p.first_name, p.last_name, u.username AS assigner
         FROM asset_assignments aa
         JOIN people p ON p.id = aa.person_id
         JOIN users u ON u.id = aa.assigned_by
         WHERE aa.asset_id = ? ORDER BY aa.assigned_at DESC',
        [(int)$id]
    );
    json_out(['ok' => true, 'asset' => $asset, 'history' => $history]);
}

function create(): void
{
    $in = input();
    $errors = validate($in, assets_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    if (db_val('SELECT id FROM assets WHERE asset_tag = ?', [in_str('asset_tag')])) {
        json_err('That asset tag already exists.', 422, ['asset_tag' => 'Already in use.']);
    }
    db_query(
        'INSERT INTO assets (asset_tag, name, category, serial_number, purchase_date, warranty_expiry, note)
         VALUES (?,?,?,?,?,?,?)',
        [
            in_str('asset_tag'), in_str('name'), in_str('category') ?: 'Other',
            in_str('serial_number') ?: null, in_str('purchase_date') ?: null,
            in_str('warranty_expiry') ?: null, in_str('note') ?: null,
        ]
    );
    $assetId = db_insert_id();
    audit('asset.create', 'asset', $assetId, ['tag' => in_str('asset_tag')]);
    json_ok(['asset_id' => $assetId]);
}

function update(string $id): void
{
    $assetId = (int)$id;
    $asset = db_row('SELECT * FROM assets WHERE id = ?', [$assetId]);
    if (!$asset) {
        json_err('That asset does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, assets_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    if (db_val('SELECT id FROM assets WHERE asset_tag = ? AND id <> ?', [in_str('asset_tag'), $assetId])) {
        json_err('That asset tag belongs to another asset.', 422, ['asset_tag' => 'Already in use.']);
    }
    $status = in_str('status');
    if ($status !== '' && !in_array($status, ASSET_STATUSES, true)) {
        json_err('Invalid status.', 422, ['status' => 'Invalid status.']);
    }
    // Assigned status is driven by the assignment workflow, not edited directly.
    if ($status === 'Assigned' && $asset['status'] !== 'Assigned') {
        json_err('Use the assign action to hand an asset to a person.', 422, ['status' => 'Use the assign workflow.']);
    }
    if ($asset['status'] === 'Assigned' && $status !== '' && $status !== 'Assigned') {
        json_err('Return the asset first, then change its status.', 409);
    }
    db_query(
        'UPDATE assets SET asset_tag = ?, name = ?, category = ?, serial_number = ?, purchase_date = ?, warranty_expiry = ?, status = ?, note = ? WHERE id = ?',
        [
            in_str('asset_tag'), in_str('name'), in_str('category') ?: $asset['category'],
            in_str('serial_number') ?: null, in_str('purchase_date') ?: null,
            in_str('warranty_expiry') ?: null, $status !== '' ? $status : $asset['status'],
            in_str('note') ?: null, $assetId,
        ]
    );
    audit('asset.update', 'asset', $assetId, ['tag' => in_str('asset_tag')]);
    json_ok();
}

function assign(string $id): void
{
    $assetId = (int)$id;
    $asset = db_row('SELECT * FROM assets WHERE id = ?', [$assetId]);
    if (!$asset) {
        json_err('That asset does not exist.', 404);
    }
    if ($asset['status'] !== 'Available') {
        json_err('Only an Available asset can be assigned. This one is ' . $asset['status'] . '.', 409);
    }
    $personId = in_int('person_id');
    if (!$personId || !db_val("SELECT id FROM people WHERE id = ? AND employment_status = 'Active'", [$personId])) {
        json_err('Choose an active person.', 422, ['person_id' => 'Choose an active person.']);
    }
    db_query(
        'INSERT INTO asset_assignments (asset_id, person_id, assigned_by) VALUES (?,?,?)',
        [$assetId, $personId, (int)current_user()['id']]
    );
    db_query("UPDATE assets SET status = 'Assigned' WHERE id = ?", [$assetId]);
    audit('asset.assign', 'asset', $assetId, ['person_id' => $personId, 'tag' => $asset['asset_tag']]);
    notify_person($personId, 'The asset ' . $asset['asset_tag'] . ' (' . $asset['name'] . ') was assigned to you.', '/assets?asset=' . $assetId, 'assets');
    json_ok();
}

function return_asset(string $id): void
{
    $assetId = (int)$id;
    $asset = db_row('SELECT * FROM assets WHERE id = ?', [$assetId]);
    if (!$asset) {
        json_err('That asset does not exist.', 404);
    }
    $open = db_row('SELECT * FROM asset_assignments WHERE asset_id = ? AND returned_at IS NULL', [$assetId]);
    if (!$open) {
        json_err('This asset is not currently assigned.', 409);
    }
    db_query('UPDATE asset_assignments SET returned_at = NOW() WHERE id = ?', [(int)$open['id']]);
    db_query("UPDATE assets SET status = 'Available' WHERE id = ?", [$assetId]);
    audit('asset.return', 'asset', $assetId, ['person_id' => (int)$open['person_id'], 'tag' => $asset['asset_tag']]);
    json_ok();
}

// CSV import. mode=preview parses and validates without writing; mode=commit
// inserts the valid rows. Expected header:
// asset_tag,name,category,serial_number,purchase_date,warranty_expiry,note
function import_csv(): void
{
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_err('No CSV file received.', 422);
    }
    if ($_FILES['file']['size'] > 1048576) {
        json_err('The CSV exceeds the 1 MB limit.', 422);
    }
    $mode = ($_POST['mode'] ?? 'preview') === 'commit' ? 'commit' : 'preview';

    $handle = fopen($_FILES['file']['tmp_name'], 'r');
    $header = fgetcsv($handle, 2048, ',', '"', '\\');
    $expected = ['asset_tag', 'name', 'category', 'serial_number', 'purchase_date', 'warranty_expiry', 'note'];
    if (!$header || array_map('strtolower', array_map('trim', $header)) !== $expected) {
        fclose($handle);
        json_err('The header row must be exactly: ' . implode(',', $expected), 422);
    }

    $rows = [];
    $seenTags = [];
    $line = 1;
    while (($cols = fgetcsv($handle, 2048, ',', '"', '\\')) !== false && count($rows) < 500) {
        $line++;
        if (count($cols) === 1 && trim((string)$cols[0]) === '') {
            continue;
        }
        $cols = array_pad(array_map(fn($c) => trim((string)$c), $cols), 7, '');
        [$tag, $name, $category, $serial, $purchase, $warranty, $note] = $cols;
        $problems = [];
        if ($tag === '' || mb_strlen($tag) > 60) {
            $problems[] = 'asset_tag is required (max 60)';
        }
        if ($name === '' || mb_strlen($name) > 160) {
            $problems[] = 'name is required (max 160)';
        }
        if ($category !== '' && !in_array($category, ASSET_CATEGORIES, true)) {
            $problems[] = 'unknown category';
        }
        foreach ([['purchase_date', $purchase], ['warranty_expiry', $warranty]] as [$label, $value]) {
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $problems[] = $label . ' must be YYYY-MM-DD';
            }
        }
        if (isset($seenTags[$tag])) {
            $problems[] = 'duplicate tag inside the file';
        }
        $seenTags[$tag] = true;
        if ($tag !== '' && db_val('SELECT id FROM assets WHERE asset_tag = ?', [$tag])) {
            $problems[] = 'tag already exists in the register';
        }
        $rows[] = [
            'line' => $line, 'asset_tag' => $tag, 'name' => $name,
            'category' => $category !== '' ? $category : 'Other',
            'serial_number' => $serial, 'purchase_date' => $purchase,
            'warranty_expiry' => $warranty, 'note' => $note,
            'problems' => $problems,
        ];
    }
    fclose($handle);

    $validRows = array_filter($rows, fn($r) => !$r['problems']);
    if ($mode === 'preview') {
        json_out(['ok' => true, 'mode' => 'preview', 'rows' => $rows,
            'valid' => count($validRows), 'invalid' => count($rows) - count($validRows)]);
    }

    $inserted = 0;
    foreach ($validRows as $r) {
        db_query(
            'INSERT INTO assets (asset_tag, name, category, serial_number, purchase_date, warranty_expiry, note)
             VALUES (?,?,?,?,?,?,?)',
            [
                $r['asset_tag'], $r['name'], $r['category'],
                $r['serial_number'] ?: null, $r['purchase_date'] ?: null,
                $r['warranty_expiry'] ?: null, $r['note'] ?: null,
            ]
        );
        $inserted++;
    }
    audit('asset.import', 'asset', null, ['inserted' => $inserted, 'skipped' => count($rows) - $inserted]);
    json_ok(['mode' => 'commit', 'inserted' => $inserted, 'skipped' => count($rows) - $inserted]);
}
