<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BiometricController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClassSessionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EnrollmentPackageController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\WhatsAppReminderController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Routes
Route::middleware(['auth', EnsureActiveAccount::class])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index')->middleware('role:admin');
    Route::patch('/accounts/{user}', [AccountController::class, 'update'])->name('accounts.update')->middleware('role:admin');

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
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])
        ->name('admin.dashboard')->middleware('role:admin');

    Route::get('/teacher/dashboard', [TeacherController::class, 'dashboard'])
        ->name('teacher.dashboard')->middleware('role:teacher');

    // Student Management Routes
    Route::resource('students', StudentController::class)
        ->middleware('role:admin')->except(['index', 'show']);
    Route::get('/students', [StudentController::class, 'index'])
        ->name('students.index')->middleware('role:admin,teacher');
    Route::get('/students/{student}', [StudentController::class, 'show'])
        ->name('students.show')->middleware('role:admin,teacher,student');
    Route::post('/students/{student}/enroll', [StudentController::class, 'enroll'])
        ->name('students.enroll')->middleware('role:admin');

    // Teacher Management Routes
    Route::resource('teachers', TeacherController::class)
        ->middleware('role:admin');

    // Course Management Routes
    Route::resource('courses', CourseController::class)
        ->middleware('role:admin')->except(['index', 'show']);
    Route::get('/courses', [CourseController::class, 'index'])
        ->name('courses.index')->middleware('role:admin,teacher');
    Route::get('/courses/{course}', [CourseController::class, 'show'])
        ->name('courses.show')->middleware('role:admin,teacher');
    Route::post('/courses/{course}/toggle-status', [CourseController::class, 'toggleStatus'])
        ->name('courses.toggle-status')->middleware('role:admin');

    // Batch Management Routes
    Route::resource('batches', BatchController::class)
        ->middleware('role:admin')->except(['index', 'show']);
    Route::get('/batches', [BatchController::class, 'index'])
        ->name('batches.index')->middleware('role:admin,teacher');
    Route::get('/batches/{batch}', [BatchController::class, 'show'])
        ->name('batches.show')->middleware('role:admin,teacher');

    // Class Session Management Routes
    Route::resource('class-sessions', ClassSessionController::class)
        ->middleware('role:admin,teacher');
    Route::post('/batches/{batch}/sessions/generate', [ClassSessionController::class, 'generate'])
        ->name('class-sessions.generate')->middleware('role:admin,teacher');
    Route::post('/batches/{batch}/sessions/generate-from-schedule', [ClassSessionController::class, 'generateFromSchedule'])
        ->name('class-sessions.generate-from-schedule')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/start', [ClassSessionController::class, 'start'])
        ->name('class-sessions.start')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/end', [ClassSessionController::class, 'end'])
        ->name('class-sessions.end')->middleware('role:admin,teacher');
    Route::get('/class-sessions/{classSession}/attendance', [ClassSessionController::class, 'markAttendance'])
        ->name('class-sessions.attendance')->middleware('role:admin,teacher');
    Route::post('/class-sessions/{classSession}/attendance', [ClassSessionController::class, 'storeAttendance'])
        ->name('class-sessions.attendance.store')->middleware('role:admin,teacher');

    // Enrollment Management Routes
    Route::get('/enrollment-packages/create', [EnrollmentPackageController::class, 'create'])
        ->name('enrollment-packages.create')->middleware('role:admin');
    Route::post('/enrollment-packages', [EnrollmentPackageController::class, 'store'])
        ->name('enrollment-packages.store')->middleware('role:admin');
    Route::get('/enrollment-packages/{enrollmentPackage}', [EnrollmentPackageController::class, 'show'])
        ->name('enrollment-packages.show')->middleware('role:admin,student');
    Route::resource('enrollments', EnrollmentController::class)
        ->middleware('role:admin')->only(['index', 'create', 'store', 'show']);
    Route::post('/enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transfer'])
        ->name('enrollments.transfer')->middleware('role:admin');
    Route::patch('/enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'drop'])
        ->name('enrollments.withdraw')->middleware('role:admin');
    Route::patch('/enrollments/{enrollment}/discount', [EnrollmentController::class, 'updateDiscount'])
        ->name('enrollments.discount')->middleware('role:admin');

    // Fee Management Routes
    Route::post('/enrollments/{enrollment}/payments', [PaymentController::class, 'store'])
        ->name('payments.store')->middleware('role:admin');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])
        ->name('payments.show')->middleware('role:admin,student');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
        ->name('payments.reverse')->middleware('role:admin');
    Route::get('/refunds/{refund}', [PaymentController::class, 'refund'])
        ->name('refunds.show')->middleware('role:admin,student');
    Route::get('/fees', [FeeController::class, 'index'])
        ->name('fees.index')->middleware('role:admin');
    Route::post('/fees/{installment}/pay', [FeeController::class, 'pay'])
        ->name('fees.pay')->middleware('role:admin');
    Route::get('/fees/reports', [FeeController::class, 'reports'])
        ->name('fees.reports')->middleware('role:admin');
    Route::get('/fees/student/{student}', [FeeController::class, 'studentFees'])
        ->name('fees.student')->middleware('role:admin,student');
    Route::get('/fees/batch/{batch}', [FeeController::class, 'batchFees'])
        ->name('fees.batch')->middleware('role:admin');

    // Teacher payroll: Admin manages; a Teacher sees only own dues and slips (scoped in the controller)
    Route::get('/payroll', [PayrollController::class, 'index'])
        ->name('payroll.index')->middleware('role:admin,teacher');
    Route::post('/payroll/salaries', [PayrollController::class, 'storeSalaries'])
        ->name('payroll.salaries')->middleware('role:admin');
    Route::post('/payroll/batches', [PayrollController::class, 'storeBatch'])
        ->name('payroll.batches')->middleware('role:admin');
    Route::get('/payroll/dues/{due}', [PayrollController::class, 'show'])
        ->name('payroll.show')->middleware('role:admin,teacher');
    Route::post('/payroll/dues/{due}/void', [PayrollController::class, 'void'])
        ->name('payroll.void')->middleware('role:admin');
    Route::post('/payroll/dues/{due}/payouts', [PayrollController::class, 'pay'])
        ->name('payroll.pay')->middleware('role:admin');
    Route::get('/payroll/payouts/{payout}', [PayrollController::class, 'slip'])
        ->name('payroll.slip')->middleware('role:admin,teacher');
    Route::post('/payroll/payouts/{payout}/reverse', [PayrollController::class, 'reverse'])
        ->name('payroll.reverse')->middleware('role:admin');

    // Non-teaching staff (no sign-in): Admin only; listed on the Users page.
    Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create')->middleware('role:admin');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store')->middleware('role:admin');
    Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit')->middleware('role:admin');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update')->middleware('role:admin');

    // WhatsApp fee reminders (Meta Cloud API): Admin only.
    Route::get('/whatsapp', [WhatsAppReminderController::class, 'index'])->name('whatsapp.index')->middleware('role:admin');
    Route::put('/whatsapp/settings', [WhatsAppReminderController::class, 'updateSettings'])->name('whatsapp.settings')->middleware('role:admin');
    Route::post('/whatsapp/test', [WhatsAppReminderController::class, 'test'])->name('whatsapp.test')->middleware(['role:admin', 'throttle:10,1']);
    Route::post('/enrollments/{enrollment}/whatsapp-reminder', [WhatsAppReminderController::class, 'sendForEnrollment'])
        ->name('whatsapp.enrollment')->middleware(['role:admin', 'throttle:30,1']);

    // Installment Management Routes
    Route::get('/installments/create', [InstallmentController::class, 'create'])
        ->name('installments.create')->middleware('role:admin');
    Route::post('/installments', [InstallmentController::class, 'store'])
        ->name('installments.store')->middleware('role:admin');
    Route::get('/enrollments/{enrollment}/details', [InstallmentController::class, 'getEnrollmentDetails'])
        ->name('enrollments.details')->middleware('role:admin');

    // Attendance Routes
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index')->middleware('role:admin,teacher');
    Route::get('/attendance/create', [AttendanceController::class, 'create'])
        ->name('attendance.create')->middleware('role:admin,teacher');
    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->name('attendance.store')->middleware('role:admin,teacher');
    Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->whereNumber('session')
        ->name('attendance.show')->middleware('role:admin,teacher');
    Route::post('/attendance/mark', [AttendanceController::class, 'mark'])
        ->name('attendance.mark')->middleware('role:admin,teacher');
    Route::get('/attendance/reports', [AttendanceController::class, 'reports'])
        ->name('attendance.reports')->middleware('role:admin,teacher');
    Route::get('/attendance/student/{student}', [AttendanceController::class, 'studentAttendance'])
        ->name('attendance.student')->middleware('role:admin,teacher,student');

    // Attendance machine (Hikvision push): Admin only. The machine push endpoint is in routes/biometric.php.
    Route::get('/biometric', [BiometricController::class, 'index'])
        ->name('biometric.index')->middleware('role:admin');
    Route::post('/biometric/devices', [BiometricController::class, 'store'])
        ->name('biometric.store')->middleware('role:admin');
    Route::put('/biometric/devices/{device}', [BiometricController::class, 'update'])
        ->name('biometric.update')->middleware('role:admin');
    Route::post('/biometric/devices/{device}/token', [BiometricController::class, 'regenerate'])
        ->name('biometric.regenerate')->middleware('role:admin');
    Route::get('/biometric/teachers', [BiometricController::class, 'teachers'])
        ->name('biometric.teachers')->middleware('role:admin');
    Route::post('/biometric/devices/{device}/resync', [BiometricController::class, 'resync'])
        ->name('biometric.resync')->middleware('role:admin');
    Route::post('/biometric/devices/{device}/bridge-token', [BiometricController::class, 'regenerateBridge'])
        ->name('biometric.bridge-token')->middleware('role:admin');
    Route::get('/biometric/devices/{device}/bridge-config', [BiometricController::class, 'bridgeConfig'])
        ->name('biometric.bridge-config')->middleware('role:admin');
    Route::get('/biometric/bridge-script', [BiometricController::class, 'bridgeScript'])
        ->name('biometric.bridge-script')->middleware('role:admin');
    Route::post('/people/{user}/resync-machine', [BiometricController::class, 'resyncUser'])
        ->name('biometric.resync-user')->middleware('role:admin');

    // Certificate Routes
    Route::get('/certificates', [CertificateController::class, 'create'])
        ->name('certificates.index')->middleware('role:admin');
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->name('certificates.store')->middleware('role:admin');
    Route::get('/certificates/{certificate}/print', [CertificateController::class, 'print'])
        ->name('certificates.print')->middleware('role:admin');

    // Exam Routes
    Route::get('/exams', [ExamController::class, 'index'])
        ->name('exams.index')->middleware('role:admin,teacher');
    Route::get('/exams/create', [ExamController::class, 'create'])
        ->name('exams.create')->middleware('role:admin');
    Route::post('/exams', [ExamController::class, 'store'])
        ->name('exams.store')->middleware('role:admin');
    Route::get('/exams/{exam}/edit', [ExamController::class, 'edit'])
        ->name('exams.edit')->middleware('role:admin');
    Route::put('/exams/{exam}', [ExamController::class, 'update'])
        ->name('exams.update')->middleware('role:admin');
    Route::get('/exams/{exam}', [ExamController::class, 'show'])
        ->name('exams.show')->middleware('role:admin,teacher');
    Route::post('/exams/{exam}/results', [ExamController::class, 'saveResults'])
        ->name('exams.results.store')->middleware('role:admin,teacher');

    // Reports Routes
    Route::get('/reports', [ReportsController::class, 'index'])
        ->name('reports.index')->middleware('role:admin');
    Route::get('/reports/{type}', [ReportsController::class, 'generate'])
        ->name('reports.generate')->middleware('role:admin');

    // Settings Routes
    Route::get('/settings', [SettingController::class, 'index'])
        ->name('settings.index')->middleware('role:admin');
    Route::put('/settings', [SettingController::class, 'update'])
        ->name('settings.update')->middleware('role:admin');

    // Student Routes (accessible only to students)
    Route::middleware('role:student')->group(function () {
        Route::get('/student/dashboard', [StudentController::class, 'dashboard'])
            ->name('student.dashboard');
        Route::get('/student/attendance', [AttendanceController::class, 'myAttendance'])
            ->name('student.attendance');
        Route::get('/student/timetable', [StudentController::class, 'timetable'])
            ->name('student.timetable');
        Route::get('/student/results', [ExamController::class, 'studentResults'])
            ->name('student.results');
    });
});
