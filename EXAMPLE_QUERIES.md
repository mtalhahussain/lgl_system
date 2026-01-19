# German Language Institute ERP System - Example Queries

## Overview
This document contains example queries and use cases for the German Language Institute ERP system.

## Database Setup
```bash
# Run migrations
php artisan migrate

# Seed the database
php artisan db:seed

# Start Laravel server
php artisan serve
```

## Authentication
All API endpoints require authentication. Use Laravel Sanctum for token-based authentication.

## Example Queries

### 1. Student Enrollment Workflow

#### Enroll a Student in a Batch
```php
// Using the EnrollmentService
$enrollment = $enrollmentService->enrollStudent(
    $studentId = 5,     // Student ID
    $batchId = 1,       // Batch ID
    $discountPercentage = 10, // 10% discount
    $installments = 3   // Pay in 3 installments
);

// API call
POST /api/enrollments
{
    "student_id": 5,
    "batch_id": 1,
    "discount_percentage": 10,
    "installments": 3
}
```

#### Transfer Student to Another Batch
```php
// Transfer student
$newEnrollment = $enrollmentService->transferStudent(
    $enrollmentId = 1,
    $newBatchId = 2,
    $transferDate = '2025-02-01'
);

// API call
POST /api/enrollments/1/transfer
{
    "new_batch_id": 2,
    "transfer_date": "2025-02-01"
}
```

### 2. Fee Management

#### Process Payment
```php
// Process a payment
$result = $feeCalculator->processPayment(
    $enrollmentId = 1,
    $amount = 15000,
    $paymentMethod = 'bank',
    $transactionRef = 'TXN123456'
);

// API call
POST /api/enrollments/1/process-payment
{
    "amount": 15000,
    "payment_method": "bank",
    "transaction_reference": "TXN123456"
}
```

#### Get Overdue Payments Report
```php
// Get overdue payments (last 30 days)
$overdueReport = $feeCalculator->getOverduePaymentsReport(30);

// API call
GET /api/dashboard/financial-reports?start_date=2025-01-01&end_date=2025-01-31
```

### 3. Teacher Salary Calculation

#### Calculate Monthly Earnings for a Teacher
```php
// Calculate earnings for January 2025
$earnings = $salaryCalculator->calculateMonthlyEarnings(
    $teacherId = 3,
    $year = 2025,
    $month = 1
);

// API call
GET /api/teacher-earnings/monthly-report?teacher_id=3&year=2025&month=1
```

#### Process Monthly Payroll
```php
// Process payroll for all teachers
$payrollResults = $salaryCalculator->processMonthlyPayroll(2025, 1);

// API call
POST /api/teacher-earnings/process-payroll
{
    "year": 2025,
    "month": 1
}
```

### 4. Attendance Management

#### Mark Bulk Attendance
```php
// Mark attendance for multiple students
$attendanceData = [
    ['student_id' => 5, 'status' => 'present', 'check_in_time' => '09:15:00'],
    ['student_id' => 6, 'status' => 'late', 'check_in_time' => '09:25:00'],
    ['student_id' => 7, 'status' => 'absent'],
];

$results = $attendanceService->markBulkAttendance($classSessionId = 1, $attendanceData);
```

#### Get Batch Attendance Report
```php
// Get attendance report for a batch
$attendanceReport = $attendanceService->getBatchAttendanceReport(
    $batchId = 1,
    $startDate = '2025-01-01',
    $endDate = '2025-01-31'
);
```

### 5. Complex Reporting Queries

#### Revenue Analysis by Course Level
```sql
-- SQL Query: Revenue by Course Level (Current Year)
SELECT 
    c.level,
    c.name,
    COUNT(DISTINCT b.id) as total_batches,
    COUNT(DISTINCT e.id) as total_enrollments,
    SUM(e.total_fee - e.discount_amount) as expected_revenue,
    SUM(e.paid_amount) as collected_revenue,
    ROUND(AVG(fi.amount), 2) as avg_payment_amount
FROM courses c
LEFT JOIN batches b ON c.id = b.course_id
LEFT JOIN enrollments e ON b.id = e.batch_id
LEFT JOIN fee_installments fi ON e.id = fi.enrollment_id AND fi.status = 'paid'
WHERE YEAR(e.enrollment_date) = 2025
GROUP BY c.id, c.level, c.name
ORDER BY c.level;
```

#### Teacher Performance Report
```sql
-- SQL Query: Teacher Performance Metrics
SELECT 
    u.name as teacher_name,
    COUNT(DISTINCT b.id) as total_batches,
    COUNT(DISTINCT e.id) as total_students,
    AVG(attendance_rate.rate) as avg_attendance_rate,
    SUM(te.total_earning) as total_earnings,
    SUM(te.paid_amount) as paid_earnings
FROM users u
JOIN batches b ON u.id = b.teacher_id
JOIN enrollments e ON b.id = e.batch_id AND e.status = 'active'
LEFT JOIN teacher_earnings te ON u.id = te.teacher_id
LEFT JOIN (
    SELECT 
        cs.batch_id,
        (COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) * 100.0 / COUNT(a.id)) as rate
    FROM class_sessions cs
    JOIN attendances a ON cs.id = a.class_session_id
    WHERE cs.status = 'completed'
    GROUP BY cs.batch_id
) attendance_rate ON b.id = attendance_rate.batch_id
WHERE u.role = 'teacher' AND u.is_active = 1
GROUP BY u.id, u.name;
```

#### Student Progress Tracking
```php
// Eloquent Query: Students with Poor Attendance
$studentsAtRisk = User::students()
    ->with(['enrollments.batch.course'])
    ->whereHas('attendances', function ($query) {
        $query->select('student_id')
              ->selectRaw('(COUNT(CASE WHEN status IN ("present", "late") THEN 1 END) * 100.0 / COUNT(*)) as attendance_rate')
              ->groupBy('student_id')
              ->havingRaw('attendance_rate < 75');
    })
    ->get();

// Get payment defaulters
$paymentDefaulters = User::students()
    ->whereHas('enrollments.feeInstallments', function ($query) {
        $query->where('status', 'pending')
              ->where('due_date', '<', now()->subDays(7));
    })
    ->with(['enrollments.feeInstallments' => function ($query) {
        $query->where('status', 'pending')
              ->where('due_date', '<', now()->subDays(7));
    }])
    ->get();
```

### 6. Business Intelligence Queries

#### Monthly Revenue Trends
```php
// Get revenue trends for the last 12 months
$revenueTrends = DB::table('fee_installments')
    ->select(
        DB::raw('YEAR(paid_date) as year'),
        DB::raw('MONTH(paid_date) as month'),
        DB::raw('SUM(amount) as total_revenue'),
        DB::raw('COUNT(*) as payment_count')
    )
    ->where('status', 'paid')
    ->where('paid_date', '>=', now()->subMonths(12))
    ->groupBy(DB::raw('YEAR(paid_date), MONTH(paid_date)'))
    ->orderBy('year', 'desc')
    ->orderBy('month', 'desc')
    ->get();
```

#### Course Completion Rate Analysis
```php
// Calculate completion rates by course level
$completionRates = Course::select('id', 'name', 'level')
    ->withCount([
        'batches as total_enrollments' => function ($query) {
            $query->join('enrollments', 'batches.id', '=', 'enrollments.batch_id');
        },
        'batches as completed_enrollments' => function ($query) {
            $query->join('enrollments', 'batches.id', '=', 'enrollments.batch_id')
                  ->where('enrollments.status', 'completed');
        },
        'batches as dropped_enrollments' => function ($query) {
            $query->join('enrollments', 'batches.id', '=', 'enrollments.batch_id')
                  ->where('enrollments.status', 'dropped');
        }
    ])
    ->get()
    ->map(function ($course) {
        $totalEnrollments = $course->total_enrollments;
        $completionRate = $totalEnrollments > 0 ? 
            ($course->completed_enrollments / $totalEnrollments) * 100 : 0;
        $dropoutRate = $totalEnrollments > 0 ? 
            ($course->dropped_enrollments / $totalEnrollments) * 100 : 0;
            
        return [
            'course' => $course->name . ' - ' . $course->level,
            'total_enrollments' => $totalEnrollments,
            'completion_rate' => round($completionRate, 2),
            'dropout_rate' => round($dropoutRate, 2),
        ];
    });
```

### 7. Automated Tasks

#### Send Fee Reminders
```bash
# Command to send fee reminders (3 days before due date)
php artisan fees:send-reminders --days=3

# Schedule in app/Console/Kernel.php
$schedule->command('fees:send-reminders --days=3')->daily();
```

#### Generate Monthly Teacher Earnings
```php
// Command to generate monthly earnings for all teachers
class GenerateTeacherEarnings extends Command
{
    public function handle()
    {
        $year = now()->year;
        $month = now()->month;
        
        $teachers = User::teachers()->active()->get();
        
        foreach ($teachers as $teacher) {
            $batches = $teacher->teachingBatches()
                ->where('status', 'ongoing')
                ->get();
                
            foreach ($batches as $batch) {
                $this->salaryCalculator->generateEarningsRecord(
                    $teacher->id,
                    $batch->id,
                    $year,
                    $month
                );
            }
        }
    }
}
```

### 8. API Endpoints Summary

```
GET    /api/dashboard/admin              # Admin dashboard overview
GET    /api/dashboard/teacher            # Teacher dashboard
GET    /api/dashboard/student            # Student dashboard
GET    /api/dashboard/financial-reports  # Financial reports
GET    /api/dashboard/kpi               # Key performance indicators

GET    /api/courses                     # List courses
POST   /api/courses                     # Create course
GET    /api/courses/{id}                # Get course details
PUT    /api/courses/{id}                # Update course
DELETE /api/courses/{id}                # Delete course

GET    /api/batches                     # List batches
POST   /api/batches                     # Create batch
GET    /api/batches/{id}                # Get batch details
GET    /api/batches/{id}/enrollments    # Get batch enrollments
GET    /api/batches/{id}/sessions       # Get batch sessions

GET    /api/enrollments                 # List enrollments
POST   /api/enrollments                 # Create enrollment
POST   /api/enrollments/{id}/transfer   # Transfer student
POST   /api/enrollments/{id}/drop       # Drop student
POST   /api/enrollments/{id}/process-payment # Process payment

GET    /api/teacher-earnings            # List teacher earnings
POST   /api/teacher-earnings/calculate  # Calculate earnings
POST   /api/teacher-earnings/{id}/pay   # Process payment
```

This comprehensive ERP system provides complete management of a German language institute with proper role-based access, financial tracking, attendance management, and detailed reporting capabilities.