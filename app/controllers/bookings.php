<?php
// Meeting room and resource booking. A simple shared calendar with overlap
// prevention so two people cannot book the same room at the same time. Anyone
// with bookings.book reserves and cancels their own; bookings.manage manages
// resources and any booking.

declare(strict_types=1);

function booking_resource_types(): array { return ['Room', 'Equipment', 'Vehicle', 'Other']; }
function bookings_can_manage(): bool { return user_can('bookings.manage'); }

function index(): void
{
    render('bookings/index', [
        'pageTitle' => 'Bookings',
        'breadcrumbs' => ['Work' => null, 'Bookings' => null],
        'canManage' => bookings_can_manage(),
        'resourceTypes' => booking_resource_types(),
        'resources' => db_all('SELECT * FROM resources ORDER BY is_active DESC, name'),
    ]);
}

function list_json(): void
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] . ' 00:00:00' : date('Y-m-d 00:00:00');
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] . ' 23:59:59' : date('Y-m-d 23:59:59', strtotime('+14 days'));
    $rows = db_all(
        "SELECT b.*, r.name AS resource_name, r.resource_type,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS booker
         FROM bookings b
         JOIN resources r ON r.id = b.resource_id
         LEFT JOIN users u ON u.id = b.booked_by LEFT JOIN people p ON p.id = u.person_id
         WHERE b.end_at >= ? AND b.start_at <= ?
         ORDER BY b.start_at",
        [$from, $to]
    );
    $myUid = (int)current_user()['id'];
    foreach ($rows as &$b) {
        $b['is_mine'] = (int)$b['booked_by'] === $myUid;
    }
    json_out(['ok' => true, 'bookings' => $rows]);
}

function create_booking(): void
{
    $resourceId = in_int('resource_id');
    if (!$resourceId || !db_val('SELECT id FROM resources WHERE id = ? AND is_active = 1', [$resourceId])) {
        json_err('Choose an available resource.', 422, ['resource_id' => 'Invalid resource.']);
    }
    $title = in_str('title');
    if ($title === '') {
        json_err('A booking title is required.', 422, ['title' => 'Required.']);
    }
    $start = str_replace('T', ' ', in_str('start_at'));
    $end = str_replace('T', ' ', in_str('end_at'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $end)) {
        json_err('Enter a valid start and end time.', 422, ['start_at' => 'Invalid.', 'end_at' => 'Invalid.']);
    }
    if (strtotime($end) <= strtotime($start)) {
        json_err('The end time must be after the start time.', 422, ['end_at' => 'Must be after start.']);
    }
    // Overlap check: any existing booking for the same resource whose window
    // intersects the requested one blocks the booking.
    $clash = db_row(
        'SELECT b.id, b.title, b.start_at FROM bookings b WHERE b.resource_id = ? AND b.start_at < ? AND b.end_at > ? LIMIT 1',
        [$resourceId, $end, $start]
    );
    if ($clash) {
        json_err('That resource is already booked for an overlapping time (' . $clash['title'] . ' at ' . $clash['start_at'] . ').', 409);
    }
    db_query(
        'INSERT INTO bookings (resource_id, person_id, booked_by, title, start_at, end_at, notes) VALUES (?,?,?,?,?,?,?)',
        [$resourceId, (int)(current_user()['person_id'] ?? 0) ?: null, (int)current_user()['id'], mb_substr($title, 0, 200), $start, $end, in_str('notes') ?: null]
    );
    $id = db_insert_id();
    audit('booking.create', 'booking', $id, ['resource_id' => $resourceId, 'title' => $title]);
    json_ok(['booking_id' => $id]);
}

function cancel_booking(string $id): void
{
    $booking = db_row('SELECT * FROM bookings WHERE id = ?', [(int)$id]);
    if (!$booking) {
        json_err('That booking does not exist.', 404);
    }
    // The person who booked it, or a manager, may cancel.
    if (!bookings_can_manage() && (int)$booking['booked_by'] !== (int)current_user()['id']) {
        json_err('You may only cancel your own bookings.', 403);
    }
    db_query('DELETE FROM bookings WHERE id = ?', [(int)$id]);
    audit('booking.cancel', 'booking', (int)$id, ['title' => $booking['title']]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Resources (manage)
// ---------------------------------------------------------------------------

function save_resource(): void
{
    if (!bookings_can_manage()) {
        json_err('You do not have permission to manage resources.', 403);
    }
    $name = in_str('name');
    if ($name === '') {
        json_err('A resource name is required.', 422, ['name' => 'Required.']);
    }
    $type = in_array(in_str('resource_type'), booking_resource_types(), true) ? in_str('resource_type') : 'Room';
    $id = in_int('id');
    if ($id && db_val('SELECT id FROM resources WHERE id = ?', [$id])) {
        db_query(
            'UPDATE resources SET name = ?, resource_type = ?, location = ?, capacity = ?, is_active = ?, notes = ? WHERE id = ?',
            [mb_substr($name, 0, 160), $type, in_str('location') ?: null, in_int('capacity') ?: null, in_int('is_active') !== null ? (in_int('is_active') ? 1 : 0) : 1, in_str('notes') ?: null, $id]
        );
        audit('resource.update', 'resource', $id, ['name' => $name]);
        json_ok(['resource_id' => $id]);
    }
    db_query(
        'INSERT INTO resources (name, resource_type, location, capacity, is_active, notes) VALUES (?,?,?,?,?,?)',
        [mb_substr($name, 0, 160), $type, in_str('location') ?: null, in_int('capacity') ?: null, in_int('is_active') !== null ? (in_int('is_active') ? 1 : 0) : 1, in_str('notes') ?: null]
    );
    $newId = db_insert_id();
    audit('resource.create', 'resource', $newId, ['name' => $name]);
    json_ok(['resource_id' => $newId]);
}

function delete_resource(string $id): void
{
    if (!bookings_can_manage()) {
        json_err('You do not have permission to manage resources.', 403);
    }
    $r = db_row('SELECT * FROM resources WHERE id = ?', [(int)$id]);
    if (!$r) {
        json_err('That resource does not exist.', 404);
    }
    db_query('DELETE FROM resources WHERE id = ?', [(int)$id]);
    audit('resource.delete', 'resource', (int)$id, ['name' => $r['name']]);
    json_ok();
}
