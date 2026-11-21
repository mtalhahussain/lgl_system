import { bindAction } from '../page-helpers';

export function init(config) {
    function showTodaySchedule() {
        const scheduleModal = new bootstrap.Modal(document.createElement('div'));
        // Implementation for showing today's schedule modal
        alert('Today\'s schedule feature coming soon!');
    }

    // Auto-refresh for live classes
    setInterval(function() {
        const liveElements = Array.from(document.querySelectorAll('.badge')).filter(badge => badge.textContent.includes('LIVE'));
        if (liveElements.length > 0) {
            // Refresh page if there are live classes to update status
            location.reload();
        }
    }, 60000); // Check every minute
    bindAction('today-schedule', () => showTodaySchedule());
}
