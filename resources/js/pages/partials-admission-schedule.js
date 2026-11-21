export function init(config) {
    const customSchedule = document.getElementById('custom_schedule');
    const scheduleRows = document.getElementById('schedule_rows');
    function showSchedule() {
        scheduleRows.hidden = !customSchedule.checked;
        scheduleRows.querySelectorAll('input').forEach(input => input.disabled = !customSchedule.checked);
    }
    customSchedule.addEventListener('change', showSchedule);
    showSchedule();
}
