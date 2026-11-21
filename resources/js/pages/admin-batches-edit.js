import { onReady } from '../page-helpers';

export function init(config) {
    onReady( function() {
        // Show/hide meeting fields based on platform
        const platformSelect = document.getElementById('meeting_platform');
        const meetingLinkField = document.getElementById('meeting_link_field');
        const meetingPasswordField = document.getElementById('meeting_password_field');
        const meetingLinkInput = document.getElementById('meeting_link');

        function toggleMeetingFields() {
            const isOnline = platformSelect.value !== 'in_person' && platformSelect.value !== '';
            meetingLinkField.style.display = isOnline ? 'block' : 'none';
            meetingPasswordField.style.display = isOnline ? 'block' : 'none';

            if (isOnline) {
                meetingLinkInput.setAttribute('required', 'required');
            } else {
                meetingLinkInput.removeAttribute('required');
            }
        }

        platformSelect.addEventListener('change', toggleMeetingFields);

        // Trigger initial check
        toggleMeetingFields();
    });
}
