<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {

        // Teacher Payment Management Routes
        Route::get('/teacher-payments', [App\Http\Controllers\TeacherPaymentController::class, 'index'])->name('teacher_payments.index')->middleware('role:admin');
        Route::get('/teacher-payments/create', [App\Http\Controllers\TeacherPaymentController::class, 'create'])->name('teacher_payments.create')->middleware('role:admin');
        Route::post('/teacher-payments', [App\Http\Controllers\TeacherPaymentController::class, 'store'])->name('teacher_payments.store')->middleware('role:admin');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard Routes based on role
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        switch ($user->role) {
            case 'admin':
                return redirect()->route('admin.dashboard');
            case 'teacher':
                return redirect()->route('teacher.dashboard');
            case 'student':
                return redirect()->route('student.dashboard');
            case 'accountant':
                return redirect()->route('accountant.dashboard');
            default:
                return redirect()->route('login');
        }
    })->name('dashboard');
    
    // Role-specific dashboards
    Route::get('/admin/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard'])
        ->name('admin.dashboard')->middleware('role:admin');
    
    Route::get('/teacher/dashboard', [App\Http\Controllers\TeacherController::class, 'dashboard'])
        ->name('teacher.dashboard')->middleware('role:teacher');
    
    Route::get('/accountant/dashboard', function () {
        return view('accountant.dashboard');
    })->name('accountant.dashboard')->middleware('role:accountant');
    
    // Student Management Routes
    Route::resource('students', App\Http\Controllers\StudentController::class)
        ->middleware('role:admin,accountant')->except(['index', 'show']);
    Route::get('/students', [App\Http\Controllers\StudentController::class, 'index'])
        ->name('students.index')->middleware('role:admin,accountant,teacher');
    Route::get('/students/{student}', [App\Http\Controllers\StudentController::class, 'show'])
        ->name('students.show')->middleware('role:admin,accountant,teacher,student');
    Route::post('/students/{student}/enroll', [App\Http\Controllers\StudentController::class, 'enroll'])
        ->name('students.enroll')->middleware('role:admin,accountant');
    
    // Teacher Management Routes  
    Route::resource('teachers', App\Http\Controllers\TeacherController::class)
        ->middleware('role:admin');
    
    // Course Management Routes
    Route::resource('courses', App\Http\Controllers\CourseController::class)
        ->middleware('role:admin')->except(['index', 'show']);
    Route::get('/courses', [App\Http\Controllers\CourseController::class, 'index'])
        ->name('courses.index')->middleware('role:admin,teacher');
    Route::get('/courses/{course}', [App\Http\Controllers\CourseController::class, 'show'])
        ->name('courses.show')->middleware('role:admin,teacher');
    Route::post('/courses/{course}/toggle-status', [App\Http\Controllers\CourseController::class, 'toggleStatus'])
        ->name('courses.toggle-status')->middleware('role:admin');
    
    // Batch Management Routes
    Route::resource('batches', App\Http\Controllers\BatchController::class)
        ->middleware('role:admin')->except(['index', 'show']);
    Route::get('/batches', [App\Http\Controllers\BatchController::class, 'index'])
        ->name('batches.index')->middleware('role:admin,teacher');
    Route::get('/batches/{batch}', [App\Http\Controllers\BatchController::class, 'show'])
        ->name('batches.show')->middleware('role:admin,teacher');
    
    // Class Session Management Routes
    Route::resource('class-sessions', App\Http\Controllers\ClassSessionController::class)
        ->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/start', [App\Http\Controllers\ClassSessionController::class, 'start'])
        ->name('class-sessions.start')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/end', [App\Http\Controllers\ClassSessionController::class, 'end'])
        ->name('class-sessions.end')->middleware('role:admin,teacher');
    Route::get('/class-sessions/{classSession}/attendance', [App\Http\Controllers\ClassSessionController::class, 'markAttendance'])
        ->name('class-sessions.attendance')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/attendance', [App\Http\Controllers\ClassSessionController::class, 'storeAttendance'])
        ->name('class-sessions.attendance.store')->middleware('role:admin,teacher');
    
    // Enrollment Management Routes
    Route::resource('enrollments', App\Http\Controllers\EnrollmentController::class)
        ->middleware('role:admin,accountant');
    Route::post('/enrollments/{enrollment}/transfer', [App\Http\Controllers\EnrollmentController::class, 'transfer'])
        ->name('enrollments.transfer')->middleware('role:admin,accountant');
    Route::patch('/enrollments/{enrollment}/withdraw', [App\Http\Controllers\EnrollmentController::class, 'withdraw'])
        ->name('enrollments.withdraw')->middleware('role:admin,accountant');
    
    // Fee Management Routes
    Route::get('/fees', [App\Http\Controllers\FeeController::class, 'index'])
        ->name('fees.index')->middleware('role:admin,accountant');
    Route::post('/fees/{installment}/pay', [App\Http\Controllers\FeeController::class, 'pay'])
        ->name('fees.pay')->middleware('role:admin,accountant');
    Route::get('/fees/reports', [App\Http\Controllers\FeeController::class, 'reports'])
        ->name('fees.reports')->middleware('role:admin,accountant');
    Route::get('/fees/student/{student}', [App\Http\Controllers\FeeController::class, 'studentFees'])
        ->name('fees.student')->middleware('role:admin,accountant,student');
    Route::get('/fees/batch/{batch}', [App\Http\Controllers\FeeController::class, 'batchFees'])
        ->name('fees.batch')->middleware('role:admin,accountant');
    
    // Installment Management Routes
    Route::get('/installments/create', [App\Http\Controllers\InstallmentController::class, 'create'])
        ->name('installments.create')->middleware('role:admin,accountant');
    Route::post('/installments', [App\Http\Controllers\InstallmentController::class, 'store'])
        ->name('installments.store')->middleware('role:admin,accountant');
    Route::get('/enrollments/{enrollment}/details', [App\Http\Controllers\InstallmentController::class, 'getEnrollmentDetails'])
        ->name('enrollments.details')->middleware('role:admin,accountant');
    
    // Attendance Routes
    Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index'])
        ->name('attendance.index')->middleware('role:admin,teacher');
    Route::get('/attendance/create', [App\Http\Controllers\AttendanceController::class, 'create'])
        ->name('attendance.create')->middleware('role:admin,teacher');
    Route::post('/attendance', [App\Http\Controllers\AttendanceController::class, 'store'])
        ->name('attendance.store')->middleware('role:admin,teacher');
    Route::get('/attendance/{session}', [App\Http\Controllers\AttendanceController::class, 'show'])
        ->name('attendance.show')->middleware('role:admin,teacher');
    Route::post('/attendance/mark', [App\Http\Controllers\AttendanceController::class, 'mark'])
        ->name('attendance.mark')->middleware('role:admin,teacher');
    Route::get('/attendance/reports', [App\Http\Controllers\AttendanceController::class, 'reports'])
        ->name('attendance.reports')->middleware('role:admin,teacher');
    Route::get('/attendance/student/{student}', [App\Http\Controllers\AttendanceController::class, 'studentAttendance'])
        ->name('attendance.student')->middleware('role:admin,teacher,student');
    
    // Biometric Attendance Routes
    Route::post('/attendance/biometric/start', [App\Http\Controllers\AttendanceController::class, 'startBiometric'])
        ->name('attendance.biometric.start')->middleware('role:admin,teacher');
    Route::post('/attendance/biometric/end', [App\Http\Controllers\AttendanceController::class, 'endBiometric'])
        ->name('attendance.biometric.end')->middleware('role:admin,teacher');
    Route::post('/attendance/biometric/sync', [App\Http\Controllers\AttendanceController::class, 'syncBiometric'])
        ->name('attendance.biometric.sync')->middleware('role:admin,teacher');
    Route::get('/attendance/biometric/test', [App\Http\Controllers\AttendanceController::class, 'testDevice'])
        ->name('attendance.biometric.test')->middleware('role:admin,teacher');
    Route::post('/attendance/auto-absent', [App\Http\Controllers\AttendanceController::class, 'autoMarkAbsent'])
        ->name('attendance.auto.absent')->middleware('role:admin,teacher');
    
    // Student Fingerprint Management
    Route::post('/students/{student}/fingerprint/enroll', [App\Http\Controllers\StudentController::class, 'enrollFingerprint'])
        ->name('students.fingerprint.enroll')->middleware('role:admin');
    Route::delete('/students/{student}/fingerprint', [App\Http\Controllers\StudentController::class, 'removeFingerprint'])
        ->name('students.fingerprint.remove')->middleware('role:admin');
    
    // Reports Routes
    Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])
        ->name('reports.index');
    Route::get('/reports/{type}', [App\Http\Controllers\ReportsController::class, 'generate'])
        ->name('reports.generate');
        
    // Settings Routes
    Route::get('/settings', [App\Http\Controllers\SettingController::class, 'index'])
        ->name('settings.index')->middleware('role:admin');
    Route::put('/settings', [App\Http\Controllers\SettingController::class, 'update'])
        ->name('settings.update')->middleware('role:admin');
        
    // Student Routes (accessible only to students)
    Route::middleware('role:student')->group(function () {
        Route::get('/student/dashboard', [App\Http\Controllers\StudentController::class, 'dashboard'])
            ->name('student.dashboard');
        Route::get('/student/attendance', [App\Http\Controllers\AttendanceController::class, 'myAttendance'])
            ->name('student.attendance');
    });
});