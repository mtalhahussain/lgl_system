{{-- Real-time notification bell. Drop into any authenticated topbar. --}}
<div class="nav-item dropdown" id="notifBell"
     data-feed="{{ route('notifications.feed') }}"
     data-readall="{{ route('notifications.readAll') }}"
     data-index="{{ route('notifications.index') }}"
     data-user="{{ auth()->id() }}"
     data-csrf="{{ csrf_token() }}">
    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none" id="notifCount" style="font-size:.6rem;">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow" style="width:340px;max-width:92vw;">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <strong class="small text-uppercase text-muted">Notifications</strong>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="notifReadAll">Mark all read</button>
        </div>
        <div id="notifList" style="max-height:60vh;overflow-y:auto;">
            <div class="text-center text-muted small py-4">Loading…</div>
        </div>
        <a href="{{ route('notifications.index') }}" class="dropdown-item text-center small border-top py-2">View all</a>
    </div>
</div>

@once
@push('scripts')
<script>
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
            const unread = n.read ? '' : 'background:rgba(13,110,253,.06);';
            const href = n.url || '#';
            return '<a href="' + href + '" class="d-flex gap-2 px-3 py-2 text-decoration-none text-body border-bottom notif-item" data-id="' + n.id + '" style="' + unread + '">'
                 + '<span class="text-' + color + '"><i class="fas ' + (n.icon || 'fa-bell') + '"></i></span>'
                 + '<span class="flex-grow-1 small"><span class="fw-semibold d-block">' + esc(n.title) + '</span>'
                 + '<span class="text-muted">' + esc(n.body) + '</span>'
                 + '<span class="text-muted d-block" style="font-size:.7rem;">' + esc(n.time) + '</span></span>'
                 + (n.read ? '' : '<span class="badge bg-' + color + ' align-self-start" style="font-size:.5rem;">new</span>')
                 + '</a>';
        }).join('');
        listEl.querySelectorAll('.notif-item').forEach(function (el) {
            el.addEventListener('click', function () {
                fetch('{{ url('/notifications') }}/' + el.dataset.id + '/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
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
</script>
@endpush
@endonce
