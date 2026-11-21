import { initPageModules } from './page-modules';
import { initCommonActions } from './common-actions';
import { initSearchSelects } from './search-selects';
import { initBatchSchedule } from './batch-schedule';
import './bootstrap';
import { initLists } from './list';
import { initDateTimePickers } from './date-time-pickers';
import { initLoginLogo } from './login-logo';
import { initCopyLinks } from './copy-link';
import { initBatchName } from './batch-name';

try {
    if (document.getElementById('sidebarCollapse') && localStorage.getItem('lgl:sidebar') === 'collapsed') {
        document.documentElement.classList.add('sidebar-collapsed');
    }
} catch { /* Storage may be unavailable. */ }

initPageModules();
initCommonActions();
initBatchSchedule();
initLists();
initDateTimePickers();
initLoginLogo();
initCopyLinks();
initBatchName();
initSearchSelects();

const studentPicker = document.getElementById('choose_student');
studentPicker?.addEventListener('change', () => studentPicker.form.requestSubmit());
