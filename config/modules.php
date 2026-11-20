<?php

/*
 * Role modules that Admin can show or hide (Users > Role permissions), WordPress-style: per role, not per user.
 *
 * Teacher/Student: each module lists the route names (Str::is patterns) it covers; a hidden module disappears
 * from the menu AND its routes return 403 for that role. A module with no saved row is shown.
 *
 * Staff: admin modules split into view/create/edit/delete actions, each listing the admin routes it opens.
 * Staff is denied by default: only ticked actions open. Admin-only areas are deliberately absent and can
 * never be granted: Users & permissions (accounts.*, staff.*), Settings & integrations (settings.*,
 * whatsapp.*, biometric.*), Payroll & refunds (payroll.*), and the Admin dashboard (finance totals).
 *
 * Dashboard, notifications and own account settings stay available to every role. Admin always has everything.
 */
return [
    'staff' => [
        'leads' => ['label' => 'Leads', 'icon' => 'fa-user-tag', 'actions' => [
            'view' => ['leads.index', 'leads.show'],
            'create' => ['leads.create', 'leads.store', 'leads.convert', 'leads.convert.store'],
            'edit' => ['leads.edit', 'leads.update', 'leads.contact', 'leads.qualify', 'leads.reject'],
            'delete' => ['leads.destroy'],
        ]],
        'students' => ['label' => 'Students', 'icon' => 'fa-users', 'actions' => [
            'view' => ['students.index', 'students.show'],
            'create' => ['students.create', 'students.store', 'students.enroll'],
            'edit' => ['students.edit', 'students.update'],
            'delete' => ['students.destroy'],
        ]],
        'enrollments' => ['label' => 'Admissions', 'icon' => 'fa-user-plus', 'actions' => [
            'view' => ['enrollments.index', 'enrollments.show', 'enrollments.details', 'enrollment-packages.show'],
            'create' => ['enrollments.create', 'enrollments.store', 'enrollment-packages.create', 'enrollment-packages.store'],
            'edit' => ['enrollments.transfer', 'enrollments.correct-batch', 'enrollments.withdraw', 'enrollments.discount'],
        ]],
        'teachers' => ['label' => 'Teachers', 'icon' => 'fa-chalkboard-teacher', 'actions' => [
            'view' => ['teachers.index', 'teachers.show'],
            'create' => ['teachers.create', 'teachers.store'],
            'edit' => ['teachers.edit', 'teachers.update', 'teachers.reassign'],
            'delete' => ['teachers.destroy'],
        ]],
        'courses' => ['label' => 'Levels', 'icon' => 'fa-book', 'actions' => [
            'view' => ['courses.index', 'courses.show'],
            'create' => ['courses.create', 'courses.store'],
            'edit' => ['courses.edit', 'courses.update', 'courses.toggle-status'],
            'delete' => ['courses.destroy'],
        ]],
        'batches' => ['label' => 'Batches', 'icon' => 'fa-layer-group', 'actions' => [
            'view' => ['batches.index', 'batches.show'],
            'create' => ['batches.create', 'batches.store'],
            'edit' => ['batches.edit', 'batches.update'],
            'delete' => ['batches.destroy'],
        ]],
        'class_sessions' => ['label' => 'Class Sessions', 'icon' => 'fa-calendar-alt', 'actions' => [
            'view' => ['class-sessions.index', 'class-sessions.show'],
            'create' => ['class-sessions.create', 'class-sessions.store', 'class-sessions.generate', 'class-sessions.generate-from-schedule'],
            'edit' => ['class-sessions.edit', 'class-sessions.update', 'class-sessions.start', 'class-sessions.end', 'class-sessions.missed'],
            'delete' => ['class-sessions.destroy'],
        ]],
        'attendance' => ['label' => 'Attendance', 'icon' => 'fa-clipboard-check', 'actions' => [
            'view' => ['attendance.index', 'attendance.show', 'attendance.reports', 'attendance.student', 'class-sessions.attendance'],
            'edit' => ['attendance.create', 'attendance.store', 'attendance.mark', 'class-sessions.attendance.store'],
        ]],
        'exams' => ['label' => 'Exams', 'icon' => 'fa-clipboard-list', 'actions' => [
            'view' => ['exams.index', 'exams.show'],
            'create' => ['exams.create', 'exams.store'],
            'edit' => ['exams.edit', 'exams.update', 'exams.results.store'],
        ]],
        'certificates' => ['label' => 'Certificates', 'icon' => 'fa-award', 'actions' => [
            'view' => ['certificates.index', 'certificates.print', 'certificates.pdf'],
            'create' => ['certificates.store'],
            'edit' => ['certificates.revoke'],
        ]],
        'fees' => ['label' => 'Fee Management', 'icon' => 'fa-money-bill', 'actions' => [
            'view' => ['fees.index', 'fees.batch', 'fees.student', 'payments.show'],
            'create' => ['fees.pay', 'payments.store', 'installments.create', 'installments.store'],
            'delete' => ['payments.reverse'],
        ]],
        'materials' => ['label' => 'Books & Materials', 'icon' => 'fa-file-pdf', 'actions' => [
            'view' => ['materials.index', 'materials.view', 'materials.download'],
            'create' => ['materials.store'],
            'delete' => ['materials.destroy'],
        ]],
        'reports' => ['label' => 'Reports', 'icon' => 'fa-chart-bar', 'actions' => [
            'view' => ['reports.index', 'reports.generate', 'fees.reports'],
        ]],
        'announcements' => ['label' => 'Announcements', 'icon' => 'fa-bullhorn', 'actions' => [
            'create' => ['notifications.announce', 'notifications.announce.send'],
        ]],
    ],
    'teacher' => [
        'students' => ['label' => 'Students', 'icon' => 'fa-users', 'routes' => ['students.*']],
        'class_sessions' => ['label' => 'Class Sessions', 'icon' => 'fa-calendar-alt', 'routes' => ['class-sessions.*']],
        'courses' => ['label' => 'View Levels', 'icon' => 'fa-book', 'routes' => ['courses.*']],
        'batches' => ['label' => 'My Batches', 'icon' => 'fa-layer-group', 'routes' => ['batches.*']],
        'payroll' => ['label' => 'My Earnings', 'icon' => 'fa-hand-holding-usd', 'routes' => ['payroll.*']],
        'attendance' => ['label' => 'Attendance', 'icon' => 'fa-clipboard-check', 'routes' => ['attendance.*']],
        'exams' => ['label' => 'Exams', 'icon' => 'fa-clipboard-list', 'routes' => ['exams.*']],
        'materials' => ['label' => 'Books & Materials', 'icon' => 'fa-file-pdf', 'routes' => ['materials.*']],
    ],
    'student' => [
        'timetable' => ['label' => 'My Timetable', 'icon' => 'fa-calendar-week', 'routes' => ['student.timetable']],
        'attendance' => ['label' => 'My Attendance', 'icon' => 'fa-clipboard-check', 'routes' => ['student.attendance', 'student.session.join', 'attendance.student']],
        'results' => ['label' => 'My Result', 'icon' => 'fa-clipboard-check', 'routes' => ['student.results', 'certificates.print']],
        'profile' => ['label' => 'My Profile', 'icon' => 'fa-user', 'routes' => ['students.show']],
        'fees' => ['label' => 'My Fees', 'icon' => 'fa-money-bill-wave', 'routes' => ['fees.student', 'payments.show', 'refunds.show', 'enrollment-packages.show']],
        'materials' => ['label' => 'Books & Materials', 'icon' => 'fa-file-pdf', 'routes' => ['materials.*']],
    ],
];
