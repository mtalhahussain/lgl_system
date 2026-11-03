<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Parent page for the layout's Back link on internal (non-sidebar) pages. A fixed parent,
 * not browser history, so Back after a save never returns to the form that was submitted.
 */
class BackLink
{
    public static function for(Request $request): ?array
    {
        $user = $request->user();
        $route = $request->route();
        if (! $user || ! $route || $user->isStudent()) {
            return null;
        }
        $name = (string) $route->getName();
        $param = fn (string $key) => $route->parameter($key);
        $admin = $user->isAdmin();

        // Pages that render their own back button are left out.
        [$target, $args, $label] = match (true) {
            in_array($name, ['students.show', 'students.create'], true) => ['students.index', [], 'Students'],
            $name === 'students.edit' => ['students.show', [$param('student')], 'Student'],
            in_array($name, ['teachers.show', 'teachers.create'], true) => ['teachers.index', [], 'Teachers'],
            $name === 'teachers.edit' => ['teachers.show', [$param('teacher')], 'Teacher'],
            in_array($name, ['courses.show', 'courses.create'], true) => ['courses.index', [], 'Courses'],
            $name === 'courses.edit' => ['courses.show', [$param('course')], 'Course'],
            in_array($name, ['batches.show', 'batches.create'], true) => ['batches.index', [], $user->isTeacher() ? 'My batches' : 'Batches'],
            $name === 'batches.edit' => ['batches.show', [$param('batch')], 'Batch'],
            in_array($name, ['class-sessions.show', 'class-sessions.create'], true) => ['class-sessions.index', [], 'Class sessions'],
            in_array($name, ['class-sessions.edit', 'class-sessions.attendance'], true) => ['class-sessions.show', [$param('class_session') ?? $param('classSession')], 'Session'],
            in_array($name, ['enrollments.show', 'enrollments.create', 'enrollment-packages.create'], true) => ['enrollments.index', [], 'Enrollments'],
            in_array($name, ['attendance.create', 'attendance.show'], true) => ['attendance.index', [], 'Attendance'],
            $name === 'attendance.reports' => $admin ? ['reports.index', [], 'Reports'] : ['attendance.index', [], 'Attendance'],
            $name === 'attendance.student' => ['students.show', [$param('student')], 'Student'],
            in_array($name, ['fees.student', 'fees.reports', 'installments.create'], true) => ['fees.index', [], 'Fee ledger'],
            $name === 'fees.batch' => ['batches.show', [$param('batch')], 'Batch'],
            $name === 'reports.generate' => ['reports.index', [], 'Reports'],
            in_array($name, ['whatsapp.index', 'biometric.index'], true) => ['settings.index', [], 'Settings'],
            in_array($name, ['staff.create', 'staff.edit'], true) => ['accounts.index', ['role' => 'staff'], 'Users'],
            default => [null, [], null],
        };

        return $target ? ['url' => route($target, $args), 'label' => $label] : null;
    }
}
