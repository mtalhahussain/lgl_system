<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\TeacherEarningController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return ['message' => 'German Language Institute ERP API', 'version' => '1.0'];
});

// Authentication required for all API routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Dashboard Routes
    Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard']);
    Route::get('/dashboard/teacher', [DashboardController::class, 'teacherDashboard']);
    Route::get('/dashboard/student', [DashboardController::class, 'studentDashboard']);
    Route::get('/dashboard/financial-reports', [DashboardController::class, 'financialReports']);
    Route::get('/dashboard/batch-performance', [DashboardController::class, 'batchPerformanceReports']);
    Route::get('/dashboard/student-progress', [DashboardController::class, 'studentProgressReports']);
    Route::get('/dashboard/kpi', [DashboardController::class, 'kpiMetrics']);

    // Course Management
    Route::apiResource('courses', CourseController::class);

    // Batch Management
    Route::apiResource('batches', BatchController::class);
    Route::get('/batches/{batch}/enrollments', [BatchController::class, 'enrollments']);
    Route::get('/batches/{batch}/sessions', [BatchController::class, 'sessions']);
    Route::get('/batches/{batch}/earnings', [BatchController::class, 'earnings']);

    // Enrollment Management
    Route::apiResource('enrollments', EnrollmentController::class)->except(['update']);
    Route::post('/enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transfer']);
    Route::post('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop']);
    Route::post('/enrollments/{enrollment}/complete', [EnrollmentController::class, 'complete']);
    Route::get('/enrollments/{enrollment}/payment-summary', [EnrollmentController::class, 'paymentSummary']);
    Route::post('/enrollments/{enrollment}/process-payment', [EnrollmentController::class, 'processPayment']);
    Route::post('/enrollments/{enrollment}/apply-discount', [EnrollmentController::class, 'applyDiscount']);

    // Teacher Earnings Management
    Route::apiResource('teacher-earnings', TeacherEarningController::class)->except(['update', 'destroy']);
    Route::post('/teacher-earnings/calculate', [TeacherEarningController::class, 'calculateEarnings']);
    Route::post('/teacher-earnings/generate', [TeacherEarningController::class, 'generateEarning']);
    Route::post('/teacher-earnings/process-payroll', [TeacherEarningController::class, 'processMonthlyPayroll']);
    Route::post('/teacher-earnings/{earning}/pay', [TeacherEarningController::class, 'payEarning']);
    Route::get('/teacher-earnings/monthly-report', [TeacherEarningController::class, 'monthlyReport']);
    Route::get('/teacher-earnings/summary', [TeacherEarningController::class, 'earningsSummary']);
    Route::get('/teacher-earnings/pending-payments', [TeacherEarningController::class, 'pendingPayments']);

});