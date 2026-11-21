import { init as initAccountantDashboard } from './pages/accountant-dashboard';
import { init as initAccountsCreate } from './pages/accounts-create';
import { init as initAuthLogin } from './pages/auth-login';
import { init as initCertificatesIndex } from './pages/certificates-index';
import { init as initEnrollmentPackagesCreate } from './pages/enrollment-packages-create';
import { init as initLayoutsStudentNav } from './pages/layouts-student-nav';
import { init as initLayoutsStudent } from './pages/layouts-student';
import { init as initPartialsAdmissionSchedule } from './pages/partials-admission-schedule';
import { init as initPartialsNotificationBell } from './pages/partials-notification-bell';
import { init as initStudentDashboard } from './pages/student-dashboard';
import { init as initTeacherDashboard } from './pages/teacher-dashboard';
import { init as initAdminBatchesCreate } from './pages/admin-batches-create';
import { init as initAdminBatchesEdit } from './pages/admin-batches-edit';
import { init as initAdminFeesIndex } from './pages/admin-fees-index';
import { init as initAdminFeesStudent } from './pages/admin-fees-student';
import { init as initAdminInstallmentsCreate } from './pages/admin-installments-create';
import { init as initAdminPeopleForm } from './pages/admin-people-form';
import { init as initAdminStudentsEdit } from './pages/admin-students-edit';
import { init as initAdminStudentsIndex } from './pages/admin-students-index';
import { init as initAdminTeachersCreate } from './pages/admin-teachers-create';

const modules = {
    'accountant-dashboard': initAccountantDashboard,
    'accounts-create': initAccountsCreate,
    'auth-login': initAuthLogin,
    'certificates-index': initCertificatesIndex,
    'enrollment-packages-create': initEnrollmentPackagesCreate,
    'layouts-student-nav': initLayoutsStudentNav,
    'layouts-student': initLayoutsStudent,
    'partials-admission-schedule': initPartialsAdmissionSchedule,
    'partials-notification-bell': initPartialsNotificationBell,
    'student-dashboard': initStudentDashboard,
    'teacher-dashboard': initTeacherDashboard,
    'admin-batches-create': initAdminBatchesCreate,
    'admin-batches-edit': initAdminBatchesEdit,
    'admin-fees-index': initAdminFeesIndex,
    'admin-fees-student': initAdminFeesStudent,
    'admin-installments-create': initAdminInstallmentsCreate,
    'admin-people-form': initAdminPeopleForm,
    'admin-students-edit': initAdminStudentsEdit,
    'admin-students-index': initAdminStudentsIndex,
    'admin-teachers-create': initAdminTeachersCreate,
};

export function initPageModules() {
    document.querySelectorAll('[data-page-module]').forEach(config => {
        modules[config.dataset.pageModule]?.(config);
    });
}
