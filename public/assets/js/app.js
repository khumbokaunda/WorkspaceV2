/* Meridian shared client logic: theme, sidebar, AJAX with CSRF, toasts,
   confirmations, the slide-over drawer, the command palette, table helpers
   and the notification bell. One toolkit, reused by every page. */

(function () {
    'use strict';

    const MX = window.MX = {};

    // ------------------------------------------------------------ theme --
    // The inline snippet in layout_top sets the attribute before first paint.
    MX.toggleTheme = function () {
        const html = document.documentElement;
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('mx-theme', next);
        const icon = document.getElementById('mx-theme-icon');
        if (icon) icon.className = next === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    };

    // ------------------------------------------------------------- ajax --
    const csrf = document.querySelector('meta[name="csrf-token"]');
    MX.csrf = csrf ? csrf.getAttribute('content') : '';

    if (window.jQuery) {
        $.ajaxSetup({
            headers: { 'X-CSRF-Token': MX.csrf, 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    // MX.api('PATCH', '/tasks/4', {title: 'x'}).then(data => ...)
    // Rejects with an Error carrying .fields for validation errors.
    MX.api = function (method, url, body) {
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': MX.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: body === undefined ? undefined : JSON.stringify(body)
        }).then(async function (res) {
            let data = {};
            try { data = await res.json(); } catch (e) { /* non JSON error page */ }
            if (!res.ok || data.ok === false) {
                const err = new Error(data.error || ('Request failed (' + res.status + ')'));
                err.fields = data.fields || {};
                err.status = res.status;
                throw err;
            }
            return data;
        });
    };

    // Sanitize free-text inputs client side before sending. The server
    // validates and escapes again; this is defence in depth, not the gate.
    MX.clean = function (value) {
        if (typeof value !== 'string') return value;
        if (window.DOMPurify) {
            return DOMPurify.sanitize(value, { ALLOWED_TAGS: [], ALLOWED_ATTR: [] });
        }
        return value;
    };

    // Collect named fields from a form or container into an object, cleaned.
    MX.formData = function (root) {
        const out = {};
        root.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
            if (el.type === 'checkbox') { out[el.name] = el.checked ? 1 : 0; return; }
            if (el.type === 'radio' && !el.checked) return;
            out[el.name] = MX.clean(el.value);
        });
        return out;
    };

    // ------------------------------------------------------------ toasts --
    MX.toast = function (icon, title) {
        Swal.fire({
            toast: true, position: 'top-end', icon: icon, title: title,
            showConfirmButton: false, timer: 2600, timerProgressBar: true
        });
    };
    MX.ok = function (t) { MX.toast('success', t); };
    MX.fail = function (t) { MX.toast('error', t || 'Something went wrong.'); };

    // Destructive confirmation. MX.confirm('Delete this?').then(go => ...)
    MX.confirm = function (title, text, confirmText) {
        return Swal.fire({
            title: title,
            text: text || 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText || 'Yes, continue',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(function (r) { return r.isConfirmed; });
    };

    // Show Parsley-style field errors returned by the server on a form.
    MX.showFieldErrors = function (root, fields) {
        root.querySelectorAll('.mx-server-error').forEach(function (el) { el.remove(); });
        Object.keys(fields || {}).forEach(function (name) {
            const input = root.querySelector('[name="' + name + '"]');
            if (!input) return;
            const msg = document.createElement('div');
            msg.className = 'mx-server-error parsley-errors-list';
            msg.textContent = fields[name];
            input.classList.add('parsley-error');
            input.insertAdjacentElement('afterend', msg);
        });
    };

    // ----------------------------------------------------------- drawer --
    // One drawer shell lives in the layout. MX.drawer.open({title, body, footer})
    // fills and shows it; body and footer accept HTML strings or nodes.
    const drawerEl = () => document.getElementById('mx-drawer');
    const backdropEl = () => document.getElementById('mx-drawer-backdrop');
    let lastFocused = null;

    MX.drawer = {
        open: function (opts) {
            const d = drawerEl(), b = backdropEl();
            if (!d) return;
            lastFocused = document.activeElement;
            d.querySelector('.mx-drawer-title').textContent = opts.title || '';
            const body = d.querySelector('.mx-drawer-body');
            const foot = d.querySelector('.mx-drawer-footer');
            if (typeof opts.body === 'string') body.innerHTML = opts.body; else { body.innerHTML = ''; if (opts.body) body.appendChild(opts.body); }
            if (opts.footer === undefined || opts.footer === null) { foot.style.display = 'none'; foot.innerHTML = ''; }
            else { foot.style.display = ''; if (typeof opts.footer === 'string') foot.innerHTML = opts.footer; else { foot.innerHTML = ''; foot.appendChild(opts.footer); } }
            b.style.display = 'block';
            d.style.display = 'flex';
            requestAnimationFrame(function () { b.classList.add('show'); d.classList.add('show'); });
            const focusable = d.querySelector('input, select, textarea, button:not(.mx-drawer-close)');
            if (focusable) focusable.focus(); else d.querySelector('.mx-drawer-close').focus();
            document.addEventListener('keydown', MX.drawer._esc);
            d.addEventListener('keydown', MX.drawer._trap);
        },
        close: function () {
            const d = drawerEl(), b = backdropEl();
            if (!d) return;
            d.classList.remove('show'); b.classList.remove('show');
            setTimeout(function () { d.style.display = 'none'; b.style.display = 'none'; }, 200);
            document.removeEventListener('keydown', MX.drawer._esc);
            d.removeEventListener('keydown', MX.drawer._trap);
            if (lastFocused) lastFocused.focus();
        },
        skeleton: function (title) {
            MX.drawer.open({
                title: title || 'Loading',
                body: '<span class="mx-skeleton mb-3" style="width:60%"></span>' +
                      '<span class="mx-skeleton mb-3" style="width:90%"></span>' +
                      '<span class="mx-skeleton mb-3" style="width:75%"></span>' +
                      '<span class="mx-skeleton mb-3" style="width:85%"></span>'
            });
        },
        _esc: function (e) { if (e.key === 'Escape') MX.drawer.close(); },
        _trap: function (e) {
            if (e.key !== 'Tab') return;
            const d = drawerEl();
            const items = d.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])');
            if (!items.length) return;
            const first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    };

    // ---------------------------------------------------------- palette --
    let paletteIndex = -1, paletteItems = [], paletteTimer = null;

    MX.palette = {
        open: function () {
            const p = document.getElementById('mx-palette');
            const bd = document.getElementById('mx-palette-backdrop');
            if (!p) return;
            p.style.display = 'block'; bd.style.display = 'block';
            const input = p.querySelector('input');
            input.value = '';
            MX.palette.render([]);
            MX.palette.search('');
            input.focus();
        },
        close: function () {
            const p = document.getElementById('mx-palette');
            const bd = document.getElementById('mx-palette-backdrop');
            if (!p) return;
            p.style.display = 'none'; bd.style.display = 'none';
        },
        search: function (q) {
            clearTimeout(paletteTimer);
            paletteTimer = setTimeout(function () {
                fetch('/api/search?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { MX.palette.render(data.results || []); })
                    .catch(function () { MX.palette.render([]); });
            }, 140);
        },
        render: function (results) {
            const box = document.querySelector('#mx-palette .mx-palette-results');
            paletteItems = results; paletteIndex = -1;
            if (!results.length) {
                box.innerHTML = '<div class="mx-palette-empty">Type to search people, tasks, projects, assets and certifications, or run a quick action.</div>';
                return;
            }
            box.innerHTML = results.map(function (r, i) {
                return '<div class="mx-palette-item" data-i="' + i + '" role="option">' +
                    '<i class="fa-solid ' + r.icon + '"></i>' +
                    '<span>' + MX.escape(r.label) + '</span>' +
                    '<span class="mx-palette-kind">' + MX.escape(r.kind) + '</span></div>';
            }).join('');
            box.querySelectorAll('.mx-palette-item').forEach(function (el) {
                el.addEventListener('click', function () { MX.palette.go(parseInt(el.dataset.i, 10)); });
            });
        },
        go: function (i) {
            const r = paletteItems[i];
            if (!r) return;
            MX.palette.close();
            if (r.url) window.location.href = r.url;
        },
        move: function (delta) {
            const items = document.querySelectorAll('#mx-palette .mx-palette-item');
            if (!items.length) return;
            paletteIndex = (paletteIndex + delta + items.length) % items.length;
            items.forEach(function (el, i) { el.classList.toggle('active', i === paletteIndex); });
            items[paletteIndex].scrollIntoView({ block: 'nearest' });
        }
    };

    MX.escape = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    // ----------------------------------------------------------- tables --
    // Standard DataTable init: our toolbar handles search, so the built-in
    // filter box is hidden and wired to our input.
    MX.table = function (selector, opts) {
        const defaults = {
            paging: true,
            pageLength: 15,
            lengthChange: false,
            info: true,
            ordering: true,
            autoWidth: false,
            language: {
                emptyTable: ' ',
                zeroRecords: 'No matching records',
                info: '_START_ to _END_ of _TOTAL_',
                infoEmpty: '',
                paginate: { previous: '&lsaquo;', next: '&rsaquo;' }
            },
            dom: 'rt<"d-flex justify-content-between align-items-center px-3 py-2"ip>'
        };
        const dt = new DataTable(selector, Object.assign(defaults, opts || {}));
        const card = document.querySelector(selector).closest('.mx-card');
        if (card) {
            const search = card.querySelector('.mx-table-search');
            if (search) search.addEventListener('input', function () { dt.search(this.value).draw(); });
        }
        return dt;
    };

    // CSV export of a DataTable's current (filtered) data.
    MX.exportCsv = function (dt, filename) {
        const header = dt.columns().header().toArray().map(function (th) { return '"' + th.textContent.trim().replace(/"/g, '""') + '"'; });
        const rows = dt.rows({ search: 'applied' }).nodes().toArray().map(function (tr) {
            return Array.prototype.map.call(tr.children, function (td) {
                return '"' + td.textContent.trim().replace(/"/g, '""') + '"';
            }).join(',');
        });
        const csv = header.join(',') + '\n' + rows.join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (filename || 'export') + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    };

    // ------------------------------------------------------ notifications --
    MX.notifications = {
        refresh: function () {
            fetch('/api/notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { items: [], unread: 0 }; })
                .then(function (data) {
                    const badge = document.getElementById('mx-notif-count');
                    if (badge) {
                        badge.style.display = data.unread > 0 ? 'flex' : 'none';
                        badge.textContent = data.unread > 99 ? '99+' : data.unread;
                    }
                    const list = document.getElementById('mx-notif-list');
                    if (!list) return;
                    if (!data.items.length) {
                        list.innerHTML = '<div class="mx-empty py-4"><i class="fa-regular fa-bell-slash"></i><p class="mb-0">You are all caught up.</p></div>';
                        return;
                    }
                    list.innerHTML = data.items.map(function (n) {
                        return '<a href="' + MX.escape(n.link || '#') + '" class="mx-notif-item ' + (n.is_read ? '' : 'mx-unread') + '">' +
                            '<div><div>' + MX.escape(n.body) + '</div><small>' + MX.escape(n.when) + '</small></div></a>';
                    }).join('');
                }).catch(function () { });
        },
        markAllRead: function () {
            MX.api('POST', '/api/notifications/read', {}).then(function () { MX.notifications.refresh(); });
        }
    };

    // ------------------------------------------------------------ boot --
    document.addEventListener('DOMContentLoaded', function () {
        // Sidebar toggles.
        const collapseBtn = document.getElementById('mx-sidebar-toggle');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    document.body.classList.toggle('mx-sidebar-open');
                } else {
                    document.body.classList.toggle('mx-sidebar-collapsed');
                    localStorage.setItem('mx-sidebar', document.body.classList.contains('mx-sidebar-collapsed') ? '1' : '0');
                }
            });
        }
        if (localStorage.getItem('mx-sidebar') === '1' && window.innerWidth >= 992) {
            document.body.classList.add('mx-sidebar-collapsed');
        }
        document.addEventListener('click', function (e) {
            if (window.innerWidth < 992 && document.body.classList.contains('mx-sidebar-open')) {
                const sidebar = document.querySelector('.mx-sidebar');
                if (sidebar && !sidebar.contains(e.target) && e.target.id !== 'mx-sidebar-toggle' && !e.target.closest('#mx-sidebar-toggle')) {
                    document.body.classList.remove('mx-sidebar-open');
                }
            }
        });

        // Drawer close wiring.
        const d = drawerEl();
        if (d) {
            d.querySelector('.mx-drawer-close').addEventListener('click', MX.drawer.close);
            backdropEl().addEventListener('click', MX.drawer.close);
        }

        // Palette wiring.
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                MX.palette.open();
            }
            if (e.key === 'Escape') MX.palette.close();
        });
        const paletteInput = document.querySelector('#mx-palette input');
        if (paletteInput) {
            paletteInput.addEventListener('input', function () { MX.palette.search(this.value); });
            paletteInput.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); MX.palette.move(1); }
                if (e.key === 'ArrowUp') { e.preventDefault(); MX.palette.move(-1); }
                if (e.key === 'Enter' && paletteIndex >= 0) { e.preventDefault(); MX.palette.go(paletteIndex); }
            });
        }
        const paletteBackdrop = document.getElementById('mx-palette-backdrop');
        if (paletteBackdrop) paletteBackdrop.addEventListener('click', MX.palette.close);

        // Flash message from the server becomes a toast.
        const flashEl = document.getElementById('mx-flash');
        if (flashEl) {
            const type = flashEl.dataset.type === 'danger' ? 'error' : flashEl.dataset.type;
            MX.toast(['success', 'error', 'warning', 'info'].includes(type) ? type : 'info', flashEl.dataset.message);
        }

        // Notification bell.
        if (document.getElementById('mx-notif-count')) {
            MX.notifications.refresh();
            setInterval(MX.notifications.refresh, 60000);
        }
    });
})();
