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
            body: body === undefined ? undefined : JSON.stringify(body),
            // Do not silently follow redirects: a server that 301s a write
            // (for example Apache redirecting POST /assets to /assets/) would
            // turn it into a GET and drop the body, and the redirected page
            // would look like a success. Surface it as an error instead.
            redirect: 'manual'
        }).then(async function (res) {
            if (res.type === 'opaqueredirect' || res.redirected || (res.status >= 300 && res.status < 400)) {
                const err = new Error('The request was redirected by the server, so it did not save. This is usually a web server rewrite issue. Reload the page and try again.');
                err.status = res.status;
                throw err;
            }
            let data = null;
            const text = await res.text();
            try { data = JSON.parse(text); } catch (e) { /* not JSON */ }
            // A write must return a JSON object. A non JSON body (an HTML page
            // or a redirect target) means the request never reached the
            // controller, so it must not be reported as success.
            if (!res.ok || !data || data.ok === false) {
                const err = new Error(
                    (data && data.error) ||
                    (data ? ('Request failed (' + res.status + ')') : 'The server returned an unexpected response. Your page may be out of date; reload it and try again.')
                );
                err.fields = (data && data.fields) || {};
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

    // Show field errors returned by the server on a form. The message is
    // positioned absolutely just under its input so it never pushes the
    // surrounding fields down; it clears on the next submit or when the field
    // is edited.
    MX.showFieldErrors = function (root, fields) {
        root.querySelectorAll('.mx-server-error').forEach(function (el) { el.remove(); });
        root.querySelectorAll('.parsley-error').forEach(function (el) { el.classList.remove('parsley-error'); });
        Object.keys(fields || {}).forEach(function (name) {
            const input = root.querySelector('[name="' + name + '"]');
            if (!input) return;
            input.classList.add('parsley-error');
            const parent = input.parentElement;
            if (!parent) return;
            if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
            const msg = document.createElement('div');
            msg.className = 'mx-server-error';
            msg.textContent = fields[name];
            // Anchor the message to the input's box, in the gap below it, out of
            // the normal flow so it moves nothing.
            msg.style.top = (input.offsetTop + input.offsetHeight) + 'px';
            msg.style.left = input.offsetLeft + 'px';
            parent.appendChild(msg);
            input.addEventListener('input', function clear() {
                msg.remove();
                input.classList.remove('parsley-error');
                input.removeEventListener('input', clear);
            });
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

    // Format a Date as a local YYYY-MM-DD string. Never use toISOString for
    // calendar keys: it converts to UTC, which shifts the day for any viewer
    // whose timezone is ahead of or behind UTC. These read the local
    // calendar components directly, so the date shown always matches the date
    // the user picked.
    MX.isoDate = function (d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    };
    MX.today = function () { return MX.isoDate(new Date()); };

    // ----------------------------------------------------------- tables --
    // Standard DataTable init: our toolbar handles search, so the built-in
    // filter box is hidden and wired to our input.
    MX.table = function (selector, opts) {
        const el = document.querySelector(selector);
        // Hide the table from the first frame so the raw, unpaginated table
        // never paints and then gets rebuilt. Revealed on initComplete below.
        if (el) el.classList.add('dt-host');
        opts = opts || {};
        const userInit = opts.initComplete;
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
            dom: 'rt<"d-flex justify-content-between align-items-center px-3 py-2"ip>',
            initComplete: function (settings, json) {
                // Fade the finished table in once DataTables has built it.
                const node = this.closest ? this : $(this).closest('table')[0];
                (el || node).classList.add('dt-ready');
                if (typeof userInit === 'function') userInit.call(this, settings, json);
            }
        };
        const dt = new DataTable(selector, Object.assign(defaults, opts));
        const card = el ? el.closest('.mx-card') : null;
        if (card) {
            const search = card.querySelector('.mx-table-search');
            if (search) search.addEventListener('input', function () { dt.search(this.value).draw(); });
        }
        return dt;
    };

    // Brief highlight on a row (or any element) whose status changed in place,
    // to draw the eye to what changed. Uses the token-driven flash keyframe.
    MX.flashRow = function (el) {
        if (!el) return;
        el.classList.remove('mx-flash-row');
        void el.offsetWidth; // restart the animation if it is already applied
        el.classList.add('mx-flash-row');
        el.addEventListener('animationend', function handler() {
            el.classList.remove('mx-flash-row');
            el.removeEventListener('animationend', handler);
        });
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
    // Two-state model. seen drives the badge, read drives the highlight.
    MX.notifications = {
        // Badge only: count of unseen rows. Polled and refreshed on load.
        refreshCount: function () {
            fetch('/notifications/unseen-count', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { unseen: 0 }; })
                .then(function (data) {
                    const badge = document.getElementById('mx-notif-count');
                    if (!badge) return;
                    const n = data.unseen || 0;
                    badge.style.display = n > 0 ? 'flex' : 'none';
                    badge.textContent = n > 99 ? '99+' : n;
                }).catch(function () { });
        },
        // Panel contents. Unread rows carry a tinted background and a marker dot.
        loadPanel: function () {
            const list = document.getElementById('mx-notif-list');
            if (!list) return;
            fetch('/notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { items: [] }; })
                .then(function (data) {
                    if (!data.items.length) {
                        list.innerHTML = '<div class="mx-empty py-4"><i class="fa-regular fa-bell-slash"></i><p class="mb-0">You are all caught up.</p></div>';
                        return;
                    }
                    list.innerHTML = data.items.map(function (n) {
                        return '<a href="' + MX.escape(n.link || '#') + '" data-id="' + n.id + '" ' +
                            'class="mx-notif-item ' + (n.is_read ? '' : 'mx-unread') + '" ' +
                            'onclick="return MX.notifications.clickItem(event, this, ' + n.id + ')">' +
                            (n.is_read ? '' : '<span class="mx-notif-dot" aria-label="Unread"></span>') +
                            '<div><div>' + MX.escape(n.body) + '</div><small>' + MX.escape(n.when) + '</small></div></a>';
                    }).join('');
                }).catch(function () { });
        },
        // Opening the panel marks everything seen. Clear the badge optimistically,
        // then tell the server. Seeing is not reading, so the list still shows
        // any unread rows highlighted.
        onOpen: function () {
            const badge = document.getElementById('mx-notif-count');
            if (badge) badge.style.display = 'none';
            MX.api('POST', '/notifications/seen', {}).catch(function () { });
            MX.notifications.loadPanel();
        },
        // Clicking an item marks that one read (scoped server-side to the owner),
        // clears its highlight, then navigates to the linked record.
        clickItem: function (event, el, id) {
            event.preventDefault();
            const href = el.getAttribute('href');
            el.classList.remove('mx-unread');
            const dot = el.querySelector('.mx-notif-dot');
            if (dot) dot.remove();
            const go = function () { if (href && href !== '#') window.location.href = href; };
            MX.api('POST', '/notifications/' + id + '/read', {}).then(go).catch(go);
            return false;
        },
        // Mark every unread row read, then repaint the panel.
        markAllRead: function () {
            MX.api('POST', '/notifications/read-all', {}).then(function () {
                MX.notifications.loadPanel();
                MX.notifications.refreshCount();
            });
        }
    };

    // One frame after the DOM is ready (so the first frame has painted with
    // transitions suppressed), release the suppression and trigger the content
    // entrance. Doing both together means the entrance plays exactly once and no
    // transition animates the initial state settling in. The content stays
    // visible at rest until this runs, so a scripting failure never blanks it.
    function mxRelease() {
        document.documentElement.classList.remove('preload');
        var content = document.querySelector('.mx-content');
        if (content) content.classList.add('mx-enter');
    }
    if (document.readyState !== 'loading') {
        requestAnimationFrame(mxRelease);
    } else {
        document.addEventListener('DOMContentLoaded', function () { requestAnimationFrame(mxRelease); });
    }

    // ------------------------------------------------------------ boot --
    document.addEventListener('DOMContentLoaded', function () {
        // Theme icon reflects the theme applied before first paint.
        const themeIcon = document.getElementById('mx-theme-icon');
        if (themeIcon) {
            const current = document.documentElement.getAttribute('data-bs-theme');
            themeIcon.className = current === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }

        // Sidebar toggles. The collapsed state lives on the root element and is
        // applied before paint by the head script, so here we only flip it and
        // persist the value. Mobile uses the off-canvas open class on the body.
        const collapseBtn = document.getElementById('mx-sidebar-toggle');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    document.body.classList.toggle('mx-sidebar-open');
                } else {
                    document.documentElement.classList.toggle('mx-sidebar-collapsed');
                    localStorage.setItem('mx-sidebar', document.documentElement.classList.contains('mx-sidebar-collapsed') ? '1' : '0');
                }
            });
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

        // Notification bell. Refresh the badge on load and poll it every 45
        // seconds so a new arrival brings the badge back. Opening the panel
        // marks everything seen and clears the badge.
        if (document.getElementById('mx-notif-count')) {
            MX.notifications.refreshCount();
            setInterval(MX.notifications.refreshCount, 45000);
            const notifDropdown = document.getElementById('mx-notif-dropdown');
            if (notifDropdown) {
                notifDropdown.addEventListener('show.bs.dropdown', MX.notifications.onOpen);
            }
        }
    });
})();
