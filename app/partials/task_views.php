<?php
// Shared task surface: Kanban board with drag and drop, list, and calendar,
// plus the task drawer with comments. Include after setting:
//   $taskProjectId (int|null)  restrict to one project, or null for all
//   $people, $canCreateTask, $canComment, and for the new-task form $projects
//     (optional; when absent the project select is hidden).
$taskProjects = $taskProjects ?? [];
?>
<div class="mx-card">
    <div class="mx-table-toolbar">
        <div class="btn-group" role="group" aria-label="Task view">
            <input type="radio" class="btn-check" name="task-view" id="tv-board" checked>
            <label class="btn btn-outline-primary btn-sm" for="tv-board"><i class="fa-solid fa-table-columns me-1"></i>Board</label>
            <input type="radio" class="btn-check" name="task-view" id="tv-list">
            <label class="btn btn-outline-primary btn-sm" for="tv-list"><i class="fa-solid fa-list me-1"></i>List</label>
            <input type="radio" class="btn-check" name="task-view" id="tv-cal">
            <label class="btn btn-outline-primary btn-sm" for="tv-cal"><i class="fa-regular fa-calendar me-1"></i>Calendar</label>
        </div>
        <select id="tv-filter-assignee" class="form-select form-select-sm" style="max-width:180px" aria-label="Filter by assignee">
            <option value="">Everyone</option>
<?php foreach ($people as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></option>
<?php endforeach; ?>
        </select>
        <div class="flex-grow-1"></div>
<?php if ($canCreateTask): ?>
        <button class="btn btn-primary btn-sm" onclick="mxNewTask()"><i class="fa-solid fa-plus me-1"></i>New task</button>
<?php endif; ?>
    </div>
    <div class="mx-card-body">
        <div id="task-board" class="mx-board" aria-label="Task board"></div>
        <div id="task-list" style="display:none">
            <table id="task-table" class="table mx-stack align-middle" style="width:100%">
                <thead><tr><th>Title</th><th>Project</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Due</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="task-cal" style="display:none">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                <button class="btn btn-subtle btn-sm" onclick="mxTaskCalShift(-1)" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="task-cal-label" style="font-size:14px;font-weight:600;min-width:130px;text-align:center"></span>
                <button class="btn btn-subtle btn-sm" onclick="mxTaskCalShift(1)" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div id="task-cal-grid"></div>
        </div>
    </div>
</div>

<script>
var mxTaskProject = <?= isset($taskProjectId) && $taskProjectId ? (int)$taskProjectId : 'null' ?>;
var mxTaskPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;
var mxTaskProjects = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name']], $taskProjects)) ?>;
var mxCanComment = <?= $canComment ? 'true' : 'false' ?>;
var mxCanCreateTask = <?= $canCreateTask ? 'true' : 'false' ?>;
var mxTasks = [];
var mxToday = '';
// Current local year and month. Computed inline rather than through MX.today,
// because this line runs as the page parses, before app.js has loaded.
var mxTaskCalMonth = (function () {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
})();
var TASK_STATUSES = ['To Do', 'In Progress', 'Blocked', 'Done'];

function mxPrioChip(p) {
    var cls = { Critical: 'mx-chip-danger', High: 'mx-chip-warning', Medium: 'mx-chip-info', Low: 'mx-chip-plain' }[p];
    return '<span class="mx-chip ' + cls + '">' + p + '</span>';
}
function mxStatusChipT(s) {
    var cls = { 'Done': 'mx-chip-success', 'In Progress': 'mx-chip-info', 'Blocked': 'mx-chip-danger', 'To Do': 'mx-chip-plain' }[s];
    return '<span class="mx-chip ' + cls + '">' + s + '</span>';
}

function mxLoadTasks() {
    var url = '/api/tasks' + (mxTaskProject ? '?project_id=' + mxTaskProject : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            mxTasks = data.tasks;
            mxToday = data.today;
            mxRenderAll();
            var openId = new URLSearchParams(location.search).get('task');
            if (openId) { mxOpenTask(parseInt(openId, 10)); history.replaceState(null, '', location.pathname); }
            if (new URLSearchParams(location.search).get('action') === 'new-task' && mxCanCreateTask) mxNewTask();
        });
}

function mxFiltered() {
    var a = document.getElementById('tv-filter-assignee').value;
    return a ? mxTasks.filter(function (t) { return String(t.assignee_id) === a; }) : mxTasks;
}

function mxRenderAll() { mxRenderBoard(); mxRenderList(); mxRenderCal(); }

// ------------------------------------------------------------- board --
function mxRenderBoard() {
    var box = document.getElementById('task-board');
    var tasks = mxFiltered();
    box.innerHTML = TASK_STATUSES.map(function (s) {
        var cards = tasks.filter(function (t) { return t.status === s; });
        return '<div class="mx-board-col" data-status="' + s + '">' +
            '<div class="mx-board-col-header">' + s + '<span class="mx-chip mx-chip-plain ms-auto">' + cards.length + '</span></div>' +
            '<div class="mx-board-col-body" data-status="' + s + '">' +
            cards.map(mxTaskCard).join('') +
            '</div></div>';
    }).join('');

    box.querySelectorAll('.mx-task-card').forEach(function (el) {
        el.addEventListener('click', function () { mxOpenTask(parseInt(el.dataset.id, 10)); });
        el.addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('text/plain', el.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
        });
    });
    box.querySelectorAll('.mx-board-col-body').forEach(function (col) {
        col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('mx-drag-over'); });
        col.addEventListener('dragleave', function () { col.classList.remove('mx-drag-over'); });
        col.addEventListener('drop', function (e) {
            e.preventDefault();
            col.classList.remove('mx-drag-over');
            var id = parseInt(e.dataTransfer.getData('text/plain'), 10);
            var status = col.dataset.status;
            var task = mxTasks.find(function (t) { return t.id == id; });
            if (!task || task.status === status) return;
            var old = task.status;
            task.status = status; // optimistic
            mxRenderAll();
            MX.api('PATCH', '/tasks/' + id + '/status', { status: status })
                .then(function () { MX.ok('Moved to ' + status + '.'); })
                .catch(function (err) { task.status = old; mxRenderAll(); MX.fail(err.message); });
        });
    });
}

function mxTaskCard(t) {
    var overdue = t.due_date && t.due_date < mxToday && t.status !== 'Done';
    return '<div class="mx-task-card' + (overdue ? ' mx-overdue' : '') + '" draggable="true" data-id="' + t.id + '" role="button" tabindex="0">' +
        '<div class="mx-task-title">' + MX.escape(t.title) + '</div>' +
        '<div class="mx-task-meta">' + mxPrioChip(t.priority) +
        (t.asg_first ? '<span><i class="fa-regular fa-user me-1"></i>' + MX.escape(t.asg_first) + '</span>' : '') +
        (t.due_date ? '<span style="' + (overdue ? 'color:var(--mx-danger)' : '') + '"><i class="fa-regular fa-clock me-1"></i>' + t.due_date.slice(5) + '</span>' : '') +
        '</div></div>';
}

// -------------------------------------------------------------- list --
function mxRenderList() {
    var labels = ['Title', 'Project', 'Assignee', 'Priority', 'Status', 'Due'];
    var rows = mxFiltered().map(function (t) {
        var overdue = t.due_date && t.due_date < mxToday && t.status !== 'Done';
        return ['<a href="javascript:void(0)" onclick="mxOpenTask(' + t.id + ')">' + MX.escape(t.title) + '</a>',
            MX.escape(t.project_name || ''),
            MX.escape(t.asg_first ? t.asg_first + ' ' + t.asg_last : ''),
            mxPrioChip(t.priority), mxStatusChipT(t.status),
            t.due_date ? '<span style="' + (overdue ? 'color:var(--mx-danger);font-weight:600' : '') + '">' + t.due_date + '</span>' : ''];
    });
    if (window.mxTaskTable) { window.mxTaskTable.clear(); window.mxTaskTable.rows.add(rows).draw(); }
    else {
        window.mxTaskTable = MX.table('#task-table', {
            data: rows,
            columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }]
        });
    }
}

// ---------------------------------------------------------- calendar --
function mxRenderCal() {
    var first = new Date(mxTaskCalMonth + '-01T00:00:00');
    document.getElementById('task-cal-label').textContent = first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    var daysInMonth = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    var lead = (first.getDay() + 6) % 7;
    var byDay = {};
    mxFiltered().forEach(function (t) {
        if (t.due_date && t.due_date.slice(0, 7) === mxTaskCalMonth) (byDay[t.due_date] = byDay[t.due_date] || []).push(t);
    });
    var html = '<div class="mx-cal">' + ['Mo','Tu','We','Th','Fr','Sa','Su'].map(function (d) { return '<div class="mx-cal-head">' + d + '</div>'; }).join('');
    for (var i = 0; i < lead; i++) html += '<div></div>';
    for (var d = 1; d <= daysInMonth; d++) {
        var iso = mxTaskCalMonth + '-' + String(d).padStart(2, '0');
        var items = byDay[iso] || [];
        var overdue = items.some(function (t) { return iso < mxToday && t.status !== 'Done'; });
        var cls = items.length ? (overdue ? 'mx-absent' : 'mx-present') : '';
        if (iso === mxToday) cls += ' mx-today';
        html += '<div class="mx-cal-day ' + cls + '" title="' + MX.escape(items.map(function (t) { return t.title; }).join(', ')) + '"' +
            (items.length ? ' role="button" onclick="mxOpenTask(' + items[0].id + ')"' : '') + '>' + d +
            (items.length ? '<span style="position:absolute;bottom:2px;right:4px;font-size:9px">' + items.length + '</span>' : '') + '</div>';
    }
    document.getElementById('task-cal-grid').innerHTML = html + '</div>';
}
function mxTaskCalShift(delta) {
    var d = new Date(mxTaskCalMonth + '-01T00:00:00');
    d.setMonth(d.getMonth() + delta);
    mxTaskCalMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    mxRenderCal();
}

// ------------------------------------------------------------ drawer --
function mxTaskForm(t) {
    t = t || {};
    var projectSelect = '';
    if (mxTaskProjects.length) {
        projectSelect = '<div class="mb-3"><label class="form-label">Project</label><select class="form-select" name="project_id"><option value="">None</option>' +
            mxTaskProjects.map(function (p) { return '<option value="' + p.id + '"' + (t.project_id == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') +
            '</select></div>';
    } else if (mxTaskProject) {
        projectSelect = '<input type="hidden" name="project_id" value="' + mxTaskProject + '">';
    }
    return '<form id="task-form">' +
        '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="200" value="' + MX.escape(t.title || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="5000">' + MX.escape(t.description || '') + '</textarea></div>' +
        projectSelect +
        '<div class="mb-3"><label class="form-label">Assignee</label><select class="form-select" name="assignee_id"><option value="">Unassigned</option>' +
        mxTaskPeople.map(function (p) { return '<option value="' + p.id + '"' + (t.assignee_id == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Priority</label><select class="form-select" name="priority">' +
        ['Low', 'Medium', 'High', 'Critical'].map(function (p) { return '<option' + ((t.priority || 'Medium') === p ? ' selected' : '') + '>' + p + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="col"><label class="form-label">Due date</label><input type="date" class="form-control" name="due_date" value="' + (t.due_date || '') + '"></div></div>' +
        '</form>';
}

function mxNewTask() {
    MX.drawer.open({
        title: 'New task',
        body: mxTaskForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitTask(null)">Create task</button>'
    });
}

function mxOpenTask(id) {
    MX.drawer.skeleton('Task');
    fetch('/api/tasks/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { MX.drawer.close(); MX.fail(data.error); return; }
            var t = data.task;
            var overdue = t.due_date && t.due_date < mxToday && t.status !== 'Done';
            var completedBanner = '';
            if (t.status === 'Done' && t.completed_at) {
                completedBanner =
                    '<div class="mx-card-body p-2 mb-3" style="background:var(--mx-success-tint);color:var(--mx-success);border-radius:var(--mx-radius-input);display:flex;align-items:center;gap:8px;font-size:13px">' +
                    '<i class="fa-solid fa-circle-check"></i><span>Completed on ' + MX.escape(t.completed_at) + '</span></div>';
            }
            var body =
                '<div class="d-flex gap-2 align-items-center mb-3 flex-wrap">' + mxStatusChipT(t.status) + mxPrioChip(t.priority) +
                (overdue ? '<span class="mx-chip mx-chip-danger">Overdue</span>' : '') + '</div>' +
                completedBanner +
                (t.description ? '<p style="font-size:13px;white-space:pre-wrap">' + MX.escape(t.description) + '</p>' : '<p class="text-muted" style="font-size:13px">No description.</p>') +
                '<dl class="row" style="font-size:13px">' +
                '<dt class="col-4 text-muted fw-normal">Project</dt><dd class="col-8">' + MX.escape(t.project_name || 'None') + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Assignee</dt><dd class="col-8">' + MX.escape(t.asg_first ? t.asg_first + ' ' + t.asg_last : 'Unassigned') + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Due</dt><dd class="col-8">' + (t.due_date || 'No due date') + '</dd>' +
                '<dt class="col-4 text-muted fw-normal">Created by</dt><dd class="col-8">' + MX.escape(t.creator) + '</dd>' +
                (t.completed_at ? '<dt class="col-4 text-muted fw-normal">Completed</dt><dd class="col-8">' + t.completed_at + '</dd>' : '') +
                '</dl>' +
                '<div class="mb-3"><label class="form-label">Status</label><select class="form-select form-select-sm" id="task-status-select">' +
                TASK_STATUSES.map(function (s) { return '<option' + (t.status === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') +
                '</select><div class="form-text">Set this to Done to mark the task complete, or drag its card to the Done column on the board.</div></div>' +
                '<hr><h3 style="font-size:14px" class="mb-2">Comments and activity</h3>' +
                '<div id="task-comments">' +
                (data.comments.length ? data.comments.map(function (c) {
                    return '<div class="mb-2" style="font-size:13px"><strong>' + MX.escape(c.author) + '</strong> <span class="text-muted" style="font-size:11px">' + c.created_at + '</span><div style="white-space:pre-wrap">' + MX.escape(c.body) + '</div></div>';
                }).join('') : '<p class="text-muted" style="font-size:12px">No comments yet.</p>') +
                '</div>' +
                (mxCanComment ? '<div class="d-flex gap-2 mt-2"><input class="form-control form-control-sm" id="task-comment-input" placeholder="Write a comment" maxlength="2000">' +
                    '<button class="btn btn-outline-primary btn-sm" onclick="mxAddComment(' + t.id + ')">Send</button></div>' : '');
            MX.drawer.open({
                title: t.title,
                body: body,
                footer: (mxCanCreateTask ? '<button class="btn btn-outline-primary" onclick="mxEditTask(' + t.id + ')">Edit</button>' : '') +
                        '<button class="btn btn-primary" onclick="MX.drawer.close()">Close</button>'
            });
            document.getElementById('task-status-select').addEventListener('change', function () {
                var status = this.value;
                MX.api('PATCH', '/tasks/' + t.id + '/status', { status: status })
                    .then(function () { MX.ok('Status updated.'); mxLoadTasks(); })
                    .catch(function (e) { MX.fail(e.message); });
            });
        });
}

function mxEditTask(id) {
    var t = mxTasks.find(function (x) { return x.id == id; });
    if (!t) return;
    MX.drawer.open({
        title: 'Edit task',
        body: mxTaskForm(t),
        footer: '<button class="btn btn-outline-primary" onclick="mxOpenTask(' + id + ')">Back</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitTask(' + id + ')">Save changes</button>'
    });
}

function mxSubmitTask(id) {
    var form = document.getElementById('task-form');
    if (!form.reportValidity()) return;
    var payload = MX.formData(form);
    var call = id ? MX.api('PATCH', '/tasks/' + id, payload) : MX.api('POST', '/tasks', payload);
    call.then(function () { MX.drawer.close(); MX.ok(id ? 'Task updated.' : 'Task created.'); mxLoadTasks(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxAddComment(id) {
    var input = document.getElementById('task-comment-input');
    var body = MX.clean(input.value.trim());
    if (!body) return;
    MX.api('POST', '/tasks/' + id + '/comments', { body: body })
        .then(function () { mxOpenTask(id); })
        .catch(function (e) { MX.fail(e.message); });
}

document.addEventListener('DOMContentLoaded', function () {
    mxLoadTasks();
    document.getElementById('tv-filter-assignee').addEventListener('change', mxRenderAll);
    [['tv-board', 'task-board'], ['tv-list', 'task-list'], ['tv-cal', 'task-cal']].forEach(function (pair) {
        document.getElementById(pair[0]).addEventListener('change', function () {
            ['task-board', 'task-list', 'task-cal'].forEach(function (idn) {
                document.getElementById(idn).style.display = idn === pair[1] ? '' : 'none';
            });
            if (pair[1] === 'task-list' && window.mxTaskTable) window.mxTaskTable.columns.adjust();
        });
    });
});
</script>
