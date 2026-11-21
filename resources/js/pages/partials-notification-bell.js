export function init(config) {
    (function () {
        const bell = document.getElementById('notifBell');
        if (!bell) return;

        const feedUrl   = bell.dataset.feed;
        const readAllUrl= bell.dataset.readall;
        const userId    = bell.dataset.user;
        const csrf      = bell.dataset.csrf;
        const countEl   = document.getElementById('notifCount');
        const listEl    = document.getElementById('notifList');

        const levelColor = { info: 'primary', success: 'success', warning: 'warning', danger: 'danger' };

        function setCount(n) {
            n = parseInt(n) || 0;
            if (n > 0) { countEl.textContent = n > 99 ? '99+' : n; countEl.classList.remove('d-none'); }
            else { countEl.classList.add('d-none'); }
        }

        function render(items) {
            if (!items.length) {
                listEl.innerHTML = '<div class="text-center text-muted small py-4">No notifications yet.</div>';
                return;
            }
            listEl.innerHTML = items.map(function (n) {
                const color = levelColor[n.level] || 'secondary';
                const unread = n.read ? '' : ' notif-unread';
                const href = n.url || '#';
                return '<a href="' + href + '" class="d-flex gap-2 px-3 py-2 text-decoration-none text-body border-bottom notif-item' + unread + '" data-id="' + n.id + '">'
                     + '<span class="text-' + color + '"><i class="fas ' + (n.icon || 'fa-bell') + '"></i></span>'
                     + '<span class="flex-grow-1 small"><span class="fw-semibold d-block">' + esc(n.title) + '</span>'
                     + '<span class="text-muted">' + esc(n.body) + '</span>'
                     + '<span class="text-muted d-block fs-07rem">' + esc(n.time) + '</span></span>'
                     + (n.read ? '' : '<span class="badge bg-' + color + ' align-self-start fs-05rem">new</span>')
                     + '</a>';
            }).join('');
            listEl.querySelectorAll('.notif-item').forEach(function (el) {
                el.addEventListener('click', function () {
                    fetch(bell.dataset.readBase + '/' + el.dataset.id + '/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json()).then(d => setCount(d.unread)).catch(() => {});
                });
            });
        }

        function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

        function loadFeed() {
            fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(d => { setCount(d.unread); render(d.items); })
                .catch(() => { listEl.innerHTML = '<div class="text-center text-muted small py-4">Unavailable.</div>'; });
        }

        document.getElementById('notifReadAll').addEventListener('click', function () {
            fetch(readAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json()).then(() => loadFeed()).catch(() => {});
        });

        loadFeed();

        // Live push via Echo (only if Pusher configured).
        function wireEcho() {
            if (!window.Echo) return;
            window.Echo.private('App.Models.User.' + userId)
                .notification(function () { loadFeed(); });
        }
        if (window.Echo) wireEcho();
        else window.addEventListener('load', wireEcho);

        // Fallback poll every 60s so counts stay fresh without Pusher.
        setInterval(loadFeed, 60000);
    })();
}
