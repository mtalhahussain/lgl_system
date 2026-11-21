import { onReady } from '../page-helpers';

export function init(config) {
    onReady( function() {
        // Auto-set end date based on course duration
        const courseSelect = document.getElementById('course_id');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        function updateEndDate() {
            if (startDateInput.value && courseSelect.value) {
                const startDate = new Date(startDateInput.value);
                // Add 12 weeks as default duration
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + (12 * 7));

                const year = endDate.getFullYear();
                const month = String(endDate.getMonth() + 1).padStart(2, '0');
                const day = String(endDate.getDate()).padStart(2, '0');

                endDateInput.value = `${year}-${month}-${day}`;
            }
        }

        startDateInput.addEventListener('change', updateEndDate);
        courseSelect.addEventListener('change', updateEndDate);

        // Show/hide meeting fields based on platform
        const platformSelect = document.getElementById('meeting_platform');
        const meetingLink = document.getElementById('meeting_link');
        const meetingPassword = document.getElementById('meeting_password');

        platformSelect.addEventListener('change', function() {
            const isOnline = this.value !== 'in_person' && this.value !== '';
            meetingLink.closest('.mb-3').style.display = isOnline ? 'block' : 'none';
            meetingPassword.closest('.mb-3').style.display = isOnline ? 'block' : 'none';

            if (isOnline) {
                meetingLink.setAttribute('required', 'required');
            } else {
                meetingLink.removeAttribute('required');
                meetingLink.value = '';
                meetingPassword.value = '';
            }
        });

        // Trigger initial check
        platformSelect.dispatchEvent(new Event('change'));
    });
}
