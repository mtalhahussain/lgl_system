<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Batch;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Mark attendance for multiple students in a class session
     */
    public function markBulkAttendance($classSessionId, $attendanceData)
    {
        $classSession = ClassSession::with('batch.activeEnrollments.student')->findOrFail($classSessionId);
        $results = [];

        foreach ($attendanceData as $studentAttendance) {
            $studentId = $studentAttendance['student_id'];
            $status = $studentAttendance['status'];
            $checkInTime = $studentAttendance['check_in_time'] ?? null;
            $notes = $studentAttendance['notes'] ?? null;

            $enrollment = $classSession->batch->activeEnrollments()
                ->where('student_id', $studentId)
                ->first();

            if (!$enrollment) {
                $results[] = [
                    'student_id' => $studentId,
                    'success' => false,
                    'error' => 'Student not enrolled in this batch',
                ];
                continue;
            }

            $attendance = $classSession->markAttendance($studentId, $status, $checkInTime);
            
            if ($notes) {
                $attendance->update(['notes' => $notes]);
            }

            $results[] = [
                'student_id' => $studentId,
                'student_name' => $enrollment->student->name,
                'status' => $status,
                'check_in_time' => $attendance->check_in_time,
                'success' => true,
            ];
        }

        return $results;
    }

    /**
     * Generate attendance report for a batch
     */
    public function getBatchAttendanceReport($batchId, $startDate = null, $endDate = null)
    {
        $batch = Batch::with(['course', 'teacher', 'activeEnrollments.student'])->findOrFail($batchId);
        
        $sessionsQuery = $batch->classeSessions()->where('status', 'completed');
        
        if ($startDate) {
            $sessionsQuery->whereDate('session_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $sessionsQuery->whereDate('session_date', '<=', $endDate);
        }
        
        $sessions = $sessionsQuery->with('attendances.student')->orderBy('session_date')->get();
        $students = $batch->activeEnrollments->map(function ($enrollment) {
            return $enrollment->student;
        });

        $attendanceMatrix = [];
        $studentStats = [];

        // Initialize student stats
        foreach ($students as $student) {
            $studentStats[$student->id] = [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'total_sessions' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
                'attendance_rate' => 0,
            ];
        }

        // Process each session
        foreach ($sessions as $session) {
            $sessionData = [
                'session_id' => $session->id,
                'date' => $session->session_date,
                'topic' => $session->topic,
                'attendance' => [],
            ];

            foreach ($students as $student) {
                $attendance = $session->attendances->where('student_id', $student->id)->first();
                $status = $attendance ? $attendance->status : 'absent';

                $sessionData['attendance'][$student->id] = [
                    'status' => $status,
                    'check_in_time' => $attendance ? $attendance->check_in_time : null,
                    'notes' => $attendance ? $attendance->notes : null,
                ];

                // Update student stats
                $studentStats[$student->id]['total_sessions']++;
                $studentStats[$student->id][$status]++;
            }

            $attendanceMatrix[] = $sessionData;
        }

        // Calculate attendance rates
        foreach ($studentStats as $studentId => &$stats) {
            if ($stats['total_sessions'] > 0) {
                $presentCount = $stats['present'] + $stats['late'];
                $stats['attendance_rate'] = ($presentCount / $stats['total_sessions']) * 100;
            }
        }

        $overallStats = [
            'total_sessions' => $sessions->count(),
            'total_students' => $students->count(),
            'average_attendance_rate' => collect($studentStats)->avg('attendance_rate'),
            'session_wise_average' => [],
        ];

        // Calculate session-wise attendance rates
        foreach ($attendanceMatrix as $session) {
            $sessionAttendance = collect($session['attendance']);
            $presentCount = $sessionAttendance->whereIn('status', ['present', 'late'])->count();
            $totalStudents = $sessionAttendance->count();
            
            $overallStats['session_wise_average'][] = [
                'date' => $session['date'],
                'topic' => $session['topic'],
                'attendance_rate' => $totalStudents > 0 ? ($presentCount / $totalStudents) * 100 : 0,
                'present_count' => $presentCount,
                'total_students' => $totalStudents,
            ];
        }

        return [
            'batch_info' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'course' => $batch->course->name . ' - ' . $batch->course->level,
                'teacher' => $batch->teacher->name,
            ],
            'overall_stats' => $overallStats,
            'student_stats' => array_values($studentStats),
            'attendance_matrix' => $attendanceMatrix,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }

    /**
     * Get attendance summary for a specific date range
     */
    public function getAttendanceSummary($startDate, $endDate, $batchId = null)
    {
        $query = ClassSession::with(['batch.course', 'attendances'])
            ->whereBetween('session_date', [$startDate, $endDate])
            ->where('status', 'completed');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $sessions = $query->get();
        
        $summary = [
            'total_sessions' => $sessions->count(),
            'total_attendance_records' => 0,
            'present_count' => 0,
            'late_count' => 0,
            'absent_count' => 0,
            'excused_count' => 0,
            'overall_attendance_rate' => 0,
            'batch_wise_stats' => [],
            'daily_stats' => [],
        ];

        $batchStats = [];
        $dailyStats = [];

        foreach ($sessions as $session) {
            $batchId = $session->batch_id;
            $date = $session->session_date->format('Y-m-d');

            // Initialize batch stats if not exists
            if (!isset($batchStats[$batchId])) {
                $batchStats[$batchId] = [
                    'batch_id' => $batchId,
                    'batch_name' => $session->batch->name,
                    'course' => $session->batch->course->name . ' - ' . $session->batch->course->level,
                    'sessions_count' => 0,
                    'total_records' => 0,
                    'present' => 0,
                    'late' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'attendance_rate' => 0,
                ];
            }

            // Initialize daily stats if not exists
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = [
                    'date' => $date,
                    'sessions_count' => 0,
                    'total_records' => 0,
                    'present' => 0,
                    'late' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'attendance_rate' => 0,
                ];
            }

            $batchStats[$batchId]['sessions_count']++;
            $dailyStats[$date]['sessions_count']++;

            foreach ($session->attendances as $attendance) {
                $status = $attendance->status;
                
                // Update overall summary
                $summary['total_attendance_records']++;
                $summary[$status . '_count']++;
                
                // Update batch stats
                $batchStats[$batchId]['total_records']++;
                $batchStats[$batchId][$status]++;
                
                // Update daily stats
                $dailyStats[$date]['total_records']++;
                $dailyStats[$date][$status]++;
            }
        }

        // Calculate attendance rates
        foreach ($batchStats as &$batchStat) {
            if ($batchStat['total_records'] > 0) {
                $presentCount = $batchStat['present'] + $batchStat['late'];
                $batchStat['attendance_rate'] = ($presentCount / $batchStat['total_records']) * 100;
            }
        }

        foreach ($dailyStats as &$dailyStat) {
            if ($dailyStat['total_records'] > 0) {
                $presentCount = $dailyStat['present'] + $dailyStat['late'];
                $dailyStat['attendance_rate'] = ($presentCount / $dailyStat['total_records']) * 100;
            }
        }

        // Calculate overall attendance rate
        if ($summary['total_attendance_records'] > 0) {
            $overallPresentCount = $summary['present_count'] + $summary['late_count'];
            $summary['overall_attendance_rate'] = ($overallPresentCount / $summary['total_attendance_records']) * 100;
        }

        $summary['batch_wise_stats'] = array_values($batchStats);
        $summary['daily_stats'] = array_values($dailyStats);

        return $summary;
    }

    /**
     * Get students with poor attendance
     */
    public function getStudentsWithPoorAttendance($batchId = null, $attendanceThreshold = 75)
    {
        $query = Attendance::with(['student', 'classSession.batch.course'])
            ->select('student_id')
            ->selectRaw('
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status IN ("present", "late") THEN 1 ELSE 0 END) as attended_sessions,
                (SUM(CASE WHEN status IN ("present", "late") THEN 1 ELSE 0 END) / COUNT(*)) * 100 as attendance_rate
            ');

        if ($batchId) {
            $query->whereHas('classSession', function ($q) use ($batchId) {
                $q->where('batch_id', $batchId);
            });
        }

        $studentAttendance = $query->groupBy('student_id')
            ->havingRaw('attendance_rate < ?', [$attendanceThreshold])
            ->get();

        $results = [];
        
        foreach ($studentAttendance as $attendance) {
            $student = $attendance->student;
            $recentSession = $attendance->classSession;
            
            $results[] = [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'student_email' => $student->email,
                'student_phone' => $student->phone,
                'batch_name' => $recentSession->batch->name ?? 'N/A',
                'course' => $recentSession->batch->course->name . ' - ' . $recentSession->batch->course->level ?? 'N/A',
                'total_sessions' => $attendance->total_sessions,
                'attended_sessions' => $attendance->attended_sessions,
                'attendance_rate' => round($attendance->attendance_rate, 2),
                'missed_sessions' => $attendance->total_sessions - $attendance->attended_sessions,
            ];
        }

        return [
            'threshold' => $attendanceThreshold,
            'students_count' => count($results),
            'students' => $results,
        ];
    }

    /**
     * Generate QR code data for quick attendance marking
     */
    public function generateAttendanceQR($classSessionId)
    {
        $classSession = ClassSession::with('batch')->findOrFail($classSessionId);
        
        $qrData = [
            'type' => 'attendance',
            'session_id' => $classSessionId,
            'batch_id' => $classSession->batch_id,
            'date' => $classSession->session_date,
            'expires_at' => now()->addHours(3)->toISOString(), // QR expires after 3 hours
            'verification_hash' => hash('sha256', $classSessionId . config('app.key')),
        ];

        return base64_encode(json_encode($qrData));
    }

    /**
     * Process QR code attendance
     */
    public function processQRAttendance($qrData, $studentId)
    {
        try {
            $data = json_decode(base64_decode($qrData), true);
            
            // Validate QR data
            if (!$data || $data['type'] !== 'attendance') {
                throw new \Exception('Invalid QR code');
            }

            if (Carbon::parse($data['expires_at'])->isPast()) {
                throw new \Exception('QR code has expired');
            }

            $expectedHash = hash('sha256', $data['session_id'] . config('app.key'));
            if ($data['verification_hash'] !== $expectedHash) {
                throw new \Exception('QR code verification failed');
            }

            // Mark attendance
            $classSession = ClassSession::findOrFail($data['session_id']);
            $attendance = $classSession->markAttendance($studentId, 'present');

            return [
                'success' => true,
                'student_id' => $studentId,
                'session_id' => $data['session_id'],
                'status' => 'present',
                'check_in_time' => $attendance->check_in_time,
                'message' => 'Attendance marked successfully',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}