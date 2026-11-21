export function initBatchSchedule() {
    if (!document.getElementById('schedule-rows')) return;
    var startDate = document.getElementById('start_date');
    var endDate = document.getElementById('end_date');
    function updateDateBounds() {
        endDate.min = startDate.value;
        endDate.setCustomValidity(endDate.value && startDate.value && endDate.value < startDate.value
            ? 'End date must be on or after the start date.' : '');
    }
    startDate.addEventListener('input', updateDateBounds);
    startDate.addEventListener('change', updateDateBounds);
    endDate.addEventListener('input', updateDateBounds);
    endDate.addEventListener('change', updateDateBounds);
    updateDateBounds();

    var rows = document.getElementById('schedule-rows');
    var tpl = document.getElementById('schedule-template');
    var add = document.getElementById('schedule-add');
    if (!rows || !tpl || !add) return;
    var next = Math.max(-1, ...Array.from(rows.querySelectorAll('[name]'), function (input) {
        return Number(input.name.match(/^schedule\[(\d+)\]/)?.[1] ?? -1);
    })) + 1;
    var dayCount = tpl.content.querySelector('select').options.length;
    var selects = function () { return Array.prototype.slice.call(rows.querySelectorAll('.schedule-row select')); };
    // One row per weekday: disable days already chosen in other rows; hide Add once all days are used.
    var syncDays = function () {
        var all = selects();
        all.forEach(function (select) {
            Array.prototype.forEach.call(select.options, function (option) {
                option.disabled = all.some(function (other) { return other !== select && other.value === option.value; });
            });
        });
        add.hidden = all.length >= dayCount;
    };
    add.addEventListener('click', function () {
        var used = selects().map(function (s) { return s.value; });
        var html = tpl.innerHTML.replace(/__i__/g, String(next++));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var row = wrap.firstChild;
        var select = row.querySelector('select');
        var free = Array.prototype.find.call(select.options, function (o) { return used.indexOf(o.value) === -1; });
        if (!free) return;
        select.value = free.value;
        rows.appendChild(row);
        syncDays();
    });
    rows.addEventListener('change', syncDays);
    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('.schedule-remove');
        if (btn) { btn.closest('.schedule-row').remove(); syncDays(); }
    });
    syncDays();
}
