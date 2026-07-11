<?php
// Internal helpdesk. A lightweight ticket system for staff to report IT or
// facilities issues, with assignment, status and a comment thread. A raiser
// sees and comments on their own tickets; helpdesk.manage sees all, assigns and
// resolves.

declare(strict_types=1);

function hd_categories(): array { return ['IT', 'Facilities', 'HR', 'Finance', 'Other']; }
function hd_priorities(): array { return ['Low', 'Normal', 'High', 'Urgent']; }
function hd_statuses(): array { return ['Open', 'In Progress', 'Resolved', 'Closed']; }
function hd_can_manage(): bool { return user_can('helpdesk.manage'); }

function index(): void
{
    $uid = (int)current_user()['id'];
    render('helpdesk/index', [
        'pageTitle' => 'Helpdesk',
        'breadcrumbs' => ['Work' => null, 'Helpdesk' => null],
        'canManage' => hd_can_manage(),
        'categories' => hd_categories(),
        'priorities' => hd_priorities(),
        'statuses' => hd_statuses(),
        'myTickets' => db_all(
            "SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(ap.first_name,''),' ',COALESCE(ap.last_name,''))),''), au.username) AS assignee
             FROM tickets t LEFT JOIN users au ON au.id = t.assigned_to LEFT JOIN people ap ON ap.id = au.person_id
             WHERE t.raised_by = ? ORDER BY t.created_at DESC",
            [$uid]
        ),
        'queue' => hd_can_manage()
            ? db_all(
                "SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(rp.first_name,''),' ',COALESCE(rp.last_name,''))),''), ru.username) AS raiser,
                        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(ap.first_name,''),' ',COALESCE(ap.last_name,''))),''), au.username) AS assignee
                 FROM tickets t
                 LEFT JOIN users ru ON ru.id = t.raised_by LEFT JOIN people rp ON rp.id = ru.person_id
                 LEFT JOIN users au ON au.id = t.assigned_to LEFT JOIN people ap ON ap.id = au.person_id
                 WHERE t.status <> 'Closed' ORDER BY FIELD(t.priority,'Urgent','High','Normal','Low'), t.created_at",
                []
              )
            : [],
        'agents' => hd_can_manage()
            ? db_all("SELECT u.id, u.username, p.first_name, p.last_name FROM users u LEFT JOIN people p ON p.id = u.person_id WHERE u.is_active = 1 ORDER BY p.first_name, u.username")
            : [],
    ]);
}

function ticket_can_view(array $ticket): bool
{
    return hd_can_manage() || (int)$ticket['raised_by'] === (int)current_user()['id'];
}

function show(string $id): void
{
    $ticketId = (int)$id;
    $ticket = db_row(
        "SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(rp.first_name,''),' ',COALESCE(rp.last_name,''))),''), ru.username) AS raiser,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(ap.first_name,''),' ',COALESCE(ap.last_name,''))),''), au.username) AS assignee
         FROM tickets t
         LEFT JOIN users ru ON ru.id = t.raised_by LEFT JOIN people rp ON rp.id = ru.person_id
         LEFT JOIN users au ON au.id = t.assigned_to LEFT JOIN people ap ON ap.id = au.person_id
         WHERE t.id = ?",
        [$ticketId]
    );
    if (!$ticket) {
        render_error(404, 'Not found', 'That ticket does not exist.');
    }
    if (!ticket_can_view($ticket)) {
        render_error(403, 'Access denied', 'You may only view your own tickets.');
    }
    render('helpdesk/show', [
        'pageTitle' => $ticket['subject'],
        'breadcrumbs' => ['Work' => null, 'Helpdesk' => '/helpdesk', 'Ticket' => null],
        'ticket' => $ticket,
        'comments' => db_all(
            "SELECT c.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS author
             FROM ticket_comments c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN people p ON p.id = u.person_id
             WHERE c.ticket_id = ? ORDER BY c.created_at",
            [$ticketId]
        ),
        'canManage' => hd_can_manage(),
        'statuses' => hd_statuses(),
        'agents' => hd_can_manage()
            ? db_all("SELECT u.id, u.username, p.first_name, p.last_name FROM users u LEFT JOIN people p ON p.id = u.person_id WHERE u.is_active = 1 ORDER BY p.first_name, u.username")
            : [],
    ]);
}

function create(): void
{
    $subject = in_str('subject');
    if ($subject === '') {
        json_err('A subject is required.', 422, ['subject' => 'Required.']);
    }
    $category = in_array(in_str('category'), hd_categories(), true) ? in_str('category') : 'IT';
    $priority = in_array(in_str('priority'), hd_priorities(), true) ? in_str('priority') : 'Normal';
    db_query(
        'INSERT INTO tickets (subject, description, category, priority, status, raised_by) VALUES (?,?,?,?,?,?)',
        [mb_substr($subject, 0, 200), in_str('description') ?: null, $category, $priority, 'Open', (int)current_user()['id']]
    );
    $id = db_insert_id();
    notify_role('admin', 'New helpdesk ticket: ' . $subject, '/helpdesk/' . $id, 'helpdesk');
    audit('ticket.create', 'ticket', $id, ['subject' => $subject, 'category' => $category]);
    json_ok(['ticket_id' => $id]);
}

// Assign and set status (manage only).
function update(string $id): void
{
    if (!hd_can_manage()) {
        json_err('You do not have permission to manage tickets.', 403);
    }
    $ticket = db_row('SELECT * FROM tickets WHERE id = ?', [(int)$id]);
    if (!$ticket) {
        json_err('That ticket does not exist.', 404);
    }
    $status = in_array(in_str('status'), hd_statuses(), true) ? in_str('status') : $ticket['status'];
    $assignee = in_int('assigned_to');
    if ($assignee && !db_val('SELECT id FROM users WHERE id = ? AND is_active = 1', [$assignee])) {
        json_err('Unknown assignee.', 422, ['assigned_to' => 'Unknown.']);
    }
    $resolvedAt = in_array($status, ['Resolved', 'Closed'], true)
        ? ($ticket['resolved_at'] ?: date('Y-m-d H:i:s'))
        : null;
    db_query(
        'UPDATE tickets SET status = ?, assigned_to = ?, resolved_at = ? WHERE id = ?',
        [$status, $assignee ?: null, $resolvedAt, (int)$id]
    );
    if ($ticket['raised_by'] && (int)$ticket['raised_by'] !== (int)current_user()['id']) {
        notify((int)$ticket['raised_by'], 'Your ticket "' . $ticket['subject'] . '" is now ' . $status . '.', '/helpdesk/' . (int)$id, 'helpdesk');
    }
    audit('ticket.update', 'ticket', (int)$id, ['status' => $status, 'assigned_to' => $assignee]);
    json_ok();
}

function add_comment(string $id): void
{
    $ticket = db_row('SELECT * FROM tickets WHERE id = ?', [(int)$id]);
    if (!$ticket) {
        json_err('That ticket does not exist.', 404);
    }
    if (!ticket_can_view($ticket)) {
        json_err('You may only comment on your own tickets.', 403);
    }
    $body = in_str('body');
    if ($body === '') {
        json_err('Write a comment.', 422, ['body' => 'Required.']);
    }
    db_query(
        'INSERT INTO ticket_comments (ticket_id, user_id, body) VALUES (?,?,?)',
        [(int)$id, (int)current_user()['id'], mb_substr($body, 0, 2000)]
    );
    // Keep the other party informed.
    $me = (int)current_user()['id'];
    if ((int)$ticket['raised_by'] !== $me && $ticket['raised_by']) {
        notify((int)$ticket['raised_by'], 'New reply on your ticket "' . $ticket['subject'] . '".', '/helpdesk/' . (int)$id, 'helpdesk');
    } elseif ($ticket['assigned_to'] && (int)$ticket['assigned_to'] !== $me) {
        notify((int)$ticket['assigned_to'], 'New reply on ticket "' . $ticket['subject'] . '".', '/helpdesk/' . (int)$id, 'helpdesk');
    }
    audit('ticket.comment', 'ticket', (int)$id, []);
    json_ok(['comment_id' => db_insert_id()]);
}
