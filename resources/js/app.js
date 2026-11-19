import './bootstrap';
import { initLists } from './list';
import { initDateTimePickers } from './date-time-pickers';
import { initLoginLogo } from './login-logo';
import { initCopyLinks } from './copy-link';
import { initBatchName } from './batch-name';

initLists();
initDateTimePickers();
initLoginLogo();
initCopyLinks();
initBatchName();

const studentPicker = document.getElementById('choose_student');
studentPicker?.addEventListener('change', () => studentPicker.form.requestSubmit());
