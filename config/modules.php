<?php

/*
 * Role modules that Admin can show or hide (Users > Role permissions).
 * Each module lists the route names (Str::is patterns) it covers; a hidden module disappears from the
 * menu AND its routes return 403 for that role. Dashboard, notifications and own account settings stay
 * available. Admin always has every module; staff never signs in.
 */
return [
    'teacher' => [
        'students' => ['label' => 'Students', 'icon' => 'fa-users', 'routes' => ['students.*']],
        'class_sessions' => ['label' => 'Class Sessions', 'icon' => 'fa-calendar-alt', 'routes' => ['class-sessions.*']],
        'courses' => ['label' => 'View Courses', 'icon' => 'fa-book', 'routes' => ['courses.*']],
        'batches' => ['label' => 'My Batches', 'icon' => 'fa-layer-group', 'routes' => ['batches.*']],
        'payroll' => ['label' => 'My Earnings', 'icon' => 'fa-hand-holding-usd', 'routes' => ['payroll.*']],
        'attendance' => ['label' => 'Attendance', 'icon' => 'fa-clipboard-check', 'routes' => ['attendance.*']],
        'exams' => ['label' => 'Exams', 'icon' => 'fa-clipboard-list', 'routes' => ['exams.*']],
    ],
    'student' => [
        'timetable' => ['label' => 'My Timetable', 'icon' => 'fa-calendar-week', 'routes' => ['student.timetable']],
        'attendance' => ['label' => 'My Attendance', 'icon' => 'fa-clipboard-check', 'routes' => ['student.attendance', 'student.session.join', 'attendance.student']],
        'results' => ['label' => 'My Result', 'icon' => 'fa-clipboard-check', 'routes' => ['student.results', 'certificates.print']],
        'profile' => ['label' => 'My Profile', 'icon' => 'fa-user', 'routes' => ['students.show']],
        'fees' => ['label' => 'My Fees', 'icon' => 'fa-money-bill-wave', 'routes' => ['fees.student', 'payments.show', 'refunds.show', 'enrollment-packages.show']],
    ],
];
