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
Route::middleware(['auth', \App\Http\Middleware\EnsureActiveAccount::class])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/accounts', [App\Http\Controllers\AccountController::class, 'index'])->name('accounts.index')->middleware('role:admin');
    Route::patch('/accounts/{user}', [App\Http\Controllers\AccountController::class, 'update'])->name('accounts.update')->middleware('role:admin');
    
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
            default:
                return redirect()->route('login');
        }
    })->name('dashboard');
    
    // Role-specific dashboards
    Route::get('/admin/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard'])
        ->name('admin.dashboard')->middleware('role:admin');
    
    Route::get('/teacher/dashboard', [App\Http\Controllers\TeacherController::class, 'dashboard'])
        ->name('teacher.dashboard')->middleware('role:teacher');
    
    
    // Student Management Routes
    Route::resource('students', App\Http\Controllers\StudentController::class)
        ->middleware('role:admin')->except(['index', 'show']);
    Route::get('/students', [App\Http\Controllers\StudentController::class, 'index'])
        ->name('students.index')->middleware('role:admin,teacher');
    Route::get('/students/{student}', [App\Http\Controllers\StudentController::class, 'show'])
        ->name('students.show')->middleware('role:admin,teacher,student');
    Route::post('/students/{student}/enroll', [App\Http\Controllers\StudentController::class, 'enroll'])
        ->name('students.enroll')->middleware('role:admin');
    
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
    Route::post('/batches/{batch}/sessions/generate', [App\Http\Controllers\ClassSessionController::class, 'generate'])
        ->name('class-sessions.generate')->middleware('role:admin,teacher');
    Route::post('/batches/{batch}/sessions/generate-from-schedule', [App\Http\Controllers\ClassSessionController::class, 'generateFromSchedule'])
        ->name('class-sessions.generate-from-schedule')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/start', [App\Http\Controllers\ClassSessionController::class, 'start'])
        ->name('class-sessions.start')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/end', [App\Http\Controllers\ClassSessionController::class, 'end'])
        ->name('class-sessions.end')->middleware('role:admin,teacher');
    Route::get('/class-sessions/{classSession}/attendance', [App\Http\Controllers\ClassSessionController::class, 'markAttendance'])
        ->name('class-sessions.attendance')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/attendance', [App\Http\Controllers\ClassSessionController::class, 'storeAttendance'])
        ->name('class-sessions.attendance.store')->middleware('role:admin,teacher');
    
    // Enrollment Management Routes
    Route::get('/enrollment-packages/create', [App\Http\Controllers\EnrollmentPackageController::class, 'create'])
        ->name('enrollment-packages.create')->middleware('role:admin');
    Route::post('/enrollment-packages', [App\Http\Controllers\EnrollmentPackageController::class, 'store'])
        ->name('enrollment-packages.store')->middleware('role:admin');
    Route::get('/enrollment-packages/{enrollmentPackage}', [App\Http\Controllers\EnrollmentPackageController::class, 'show'])
        ->name('enrollment-packages.show')->middleware('role:admin,student');
    Route::resource('enrollments', App\Http\Controllers\EnrollmentController::class)
        ->middleware('role:admin')->only(['index', 'create', 'store', 'show']);
    Route::post('/enrollments/{enrollment}/transfer', [App\Http\Controllers\EnrollmentController::class, 'transfer'])
        ->name('enrollments.transfer')->middleware('role:admin');
    Route::patch('/enrollments/{enrollment}/withdraw', [App\Http\Controllers\EnrollmentController::class, 'drop'])
        ->name('enrollments.withdraw')->middleware('role:admin');
    Route::patch('/enrollments/{enrollment}/discount', [App\Http\Controllers\EnrollmentController::class, 'updateDiscount'])
        ->name('enrollments.discount')->middleware('role:admin');
    
    // Fee Management Routes
    Route::post('/enrollments/{enrollment}/payments', [App\Http\Controllers\PaymentController::class, 'store'])
        ->name('payments.store')->middleware('role:admin');
    Route::get('/payments/{payment}', [App\Http\Controllers\PaymentController::class, 'show'])
        ->name('payments.show')->middleware('role:admin,student');
    Route::post('/payments/{payment}/reverse', [App\Http\Controllers\PaymentController::class, 'reverse'])
        ->name('payments.reverse')->middleware('role:admin');
    Route::get('/refunds/{refund}', [App\Http\Controllers\PaymentController::class, 'refund'])
        ->name('refunds.show')->middleware('role:admin,student');
    Route::get('/fees', [App\Http\Controllers\FeeController::class, 'index'])
        ->name('fees.index')->middleware('role:admin');
    Route::post('/fees/{installment}/pay', [App\Http\Controllers\FeeController::class, 'pay'])
        ->name('fees.pay')->middleware('role:admin');
    Route::get('/fees/reports', [App\Http\Controllers\FeeController::class, 'reports'])
        ->name('fees.reports')->middleware('role:admin');
    Route::get('/fees/student/{student}', [App\Http\Controllers\FeeController::class, 'studentFees'])
        ->name('fees.student')->middleware('role:admin,student');
    Route::get('/fees/batch/{batch}', [App\Http\Controllers\FeeController::class, 'batchFees'])
        ->name('fees.batch')->middleware('role:admin');
    
    // Teacher payroll: Admin manages; a Teacher sees only own dues and slips (scoped in the controller)
    Route::get('/payroll', [App\Http\Controllers\PayrollController::class, 'index'])
        ->name('payroll.index')->middleware('role:admin,teacher');
    Route::post('/payroll/salaries', [App\Http\Controllers\PayrollController::class, 'storeSalaries'])
        ->name('payroll.salaries')->middleware('role:admin');
    Route::post('/payroll/batches', [App\Http\Controllers\PayrollController::class, 'storeBatch'])
        ->name('payroll.batches')->middleware('role:admin');
    Route::get('/payroll/dues/{due}', [App\Http\Controllers\PayrollController::class, 'show'])
        ->name('payroll.show')->middleware('role:admin,teacher');
    Route::post('/payroll/dues/{due}/void', [App\Http\Controllers\PayrollController::class, 'void'])
        ->name('payroll.void')->middleware('role:admin');
    Route::post('/payroll/dues/{due}/payouts', [App\Http\Controllers\PayrollController::class, 'pay'])
        ->name('payroll.pay')->middleware('role:admin');
    Route::get('/payroll/payouts/{payout}', [App\Http\Controllers\PayrollController::class, 'slip'])
        ->name('payroll.slip')->middleware('role:admin,teacher');
    Route::post('/payroll/payouts/{payout}/reverse', [App\Http\Controllers\PayrollController::class, 'reverse'])
        ->name('payroll.reverse')->middleware('role:admin');

    // Non-teaching staff (no sign-in): Admin only; listed on the Users page.
    Route::get('/staff/create', [App\Http\Controllers\StaffController::class, 'create'])->name('staff.create')->middleware('role:admin');
    Route::post('/staff', [App\Http\Controllers\StaffController::class, 'store'])->name('staff.store')->middleware('role:admin');
    Route::get('/staff/{staff}/edit', [App\Http\Controllers\StaffController::class, 'edit'])->name('staff.edit')->middleware('role:admin');
    Route::put('/staff/{staff}', [App\Http\Controllers\StaffController::class, 'update'])->name('staff.update')->middleware('role:admin');

    // WhatsApp fee reminders (Meta Cloud API): Admin only.
    Route::get('/whatsapp', [App\Http\Controllers\WhatsAppReminderController::class, 'index'])->name('whatsapp.index')->middleware('role:admin');
    Route::put('/whatsapp/settings', [App\Http\Controllers\WhatsAppReminderController::class, 'updateSettings'])->name('whatsapp.settings')->middleware('role:admin');
    Route::post('/whatsapp/test', [App\Http\Controllers\WhatsAppReminderController::class, 'test'])->name('whatsapp.test')->middleware(['role:admin', 'throttle:10,1']);
    Route::post('/enrollments/{enrollment}/whatsapp-reminder', [App\Http\Controllers\WhatsAppReminderController::class, 'sendForEnrollment'])
        ->name('whatsapp.enrollment')->middleware(['role:admin', 'throttle:30,1']);

    // Installment Management Routes
    Route::get('/installments/create', [App\Http\Controllers\InstallmentController::class, 'create'])
        ->name('installments.create')->middleware('role:admin');
    Route::post('/installments', [App\Http\Controllers\InstallmentController::class, 'store'])
        ->name('installments.store')->middleware('role:admin');
    Route::get('/enrollments/{enrollment}/details', [App\Http\Controllers\InstallmentController::class, 'getEnrollmentDetails'])
        ->name('enrollments.details')->middleware('role:admin');
    
    // Attendance Routes
    Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index'])
        ->name('attendance.index')->middleware('role:admin,teacher');
    Route::get('/attendance/create', [App\Http\Controllers\AttendanceController::class, 'create'])
        ->name('attendance.create')->middleware('role:admin,teacher');
    Route::post('/attendance', [App\Http\Controllers\AttendanceController::class, 'store'])
        ->name('attendance.store')->middleware('role:admin,teacher');
    Route::get('/attendance/{session}', [App\Http\Controllers\AttendanceController::class, 'show'])->whereNumber('session')
        ->name('attendance.show')->middleware('role:admin,teacher');
    Route::post('/attendance/mark', [App\Http\Controllers\AttendanceController::class, 'mark'])
        ->name('attendance.mark')->middleware('role:admin,teacher');
    Route::get('/attendance/reports', [App\Http\Controllers\AttendanceController::class, 'reports'])
        ->name('attendance.reports')->middleware('role:admin,teacher');
    Route::get('/attendance/student/{student}', [App\Http\Controllers\AttendanceController::class, 'studentAttendance'])
        ->name('attendance.student')->middleware('role:admin,teacher,student');
    
    // Attendance machine (Hikvision push): Admin only. The machine push endpoint is in routes/biometric.php.
    Route::get('/biometric', [App\Http\Controllers\BiometricController::class, 'index'])
        ->name('biometric.index')->middleware('role:admin');
    Route::post('/biometric/devices', [App\Http\Controllers\BiometricController::class, 'store'])
        ->name('biometric.store')->middleware('role:admin');
    Route::put('/biometric/devices/{device}', [App\Http\Controllers\BiometricController::class, 'update'])
        ->name('biometric.update')->middleware('role:admin');
    Route::post('/biometric/devices/{device}/token', [App\Http\Controllers\BiometricController::class, 'regenerate'])
        ->name('biometric.regenerate')->middleware('role:admin');
    Route::get('/biometric/teachers', [App\Http\Controllers\BiometricController::class, 'teachers'])
        ->name('biometric.teachers')->middleware('role:admin');
    Route::post('/biometric/devices/{device}/resync', [App\Http\Controllers\BiometricController::class, 'resync'])
        ->name('biometric.resync')->middleware('role:admin');
    Route::post('/biometric/devices/{device}/bridge-token', [App\Http\Controllers\BiometricController::class, 'regenerateBridge'])
        ->name('biometric.bridge-token')->middleware('role:admin');
    Route::get('/biometric/devices/{device}/bridge-config', [App\Http\Controllers\BiometricController::class, 'bridgeConfig'])
        ->name('biometric.bridge-config')->middleware('role:admin');
    Route::get('/biometric/bridge-script', [App\Http\Controllers\BiometricController::class, 'bridgeScript'])
        ->name('biometric.bridge-script')->middleware('role:admin');
    Route::post('/people/{user}/resync-machine', [App\Http\Controllers\BiometricController::class, 'resyncUser'])
        ->name('biometric.resync-user')->middleware('role:admin');
    
    // Reports Routes
    Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])
        ->name('reports.index')->middleware('role:admin');
    Route::get('/reports/{type}', [App\Http\Controllers\ReportsController::class, 'generate'])
        ->name('reports.generate')->middleware('role:admin');
        
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
        Route::get('/student/timetable', [App\Http\Controllers\StudentController::class, 'timetable'])
            ->name('student.timetable');
    });
});
