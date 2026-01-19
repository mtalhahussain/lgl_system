<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\BiometricDevice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class HikvisionAttendanceService
{
    protected $deviceConfig;
    
    public function __construct()
    {
        $this->deviceConfig = [
            'host' => env('HIKVISION_HOST', '192.168.1.100'),
            'port' => env('HIKVISION_PORT', '80'),
            'username' => env('HIKVISION_USERNAME', 'admin'),
            'password' => env('HIKVISION_PASSWORD', 'admin123'),
            'timeout' => 30
        ];
    }

    /**
     * Register student fingerprint with Hikvision device
     */
    public function registerStudentFingerprint($studentId, $fingerprintData)
    {
        try {
            $student = User::find($studentId);
            if (!$student) {
                return ['success' => false, 'message' => 'Student not found'];
            }

            $deviceUrl = "http://{$this->deviceConfig['host']}:{$this->deviceConfig['port']}/ISAPI/AccessControl/UserInfo/SetUp";
            
            $userData = [
                'UserInfo' => [
                    'employeeNo' => $student->id,
                    'name' => $student->name,
                    'userType' => 'normal',
                    'Valid' => [
                        'enable' => true,
                        'beginTime' => Carbon::now()->format('Y-m-d\TH:i:s'),
                        'endTime' => Carbon::now()->addYear()->format('Y-m-d\TH:i:s')
                    ],
                    'FingerPrintCfg' => [
                        'fingerPrintID' => 1,
                        'fingerPrintData' => $fingerprintData
                    ]
                ]
            ];

            $response = Http::withBasicAuth(
                $this->deviceConfig['username'],
                $this->deviceConfig['password']
            )->timeout($this->deviceConfig['timeout'])
              ->put($deviceUrl, $userData);

            if ($response->successful()) {
                // Update student record with fingerprint info
                $student->update([
                    'fingerprint_enrolled' => true,
                    'device_employee_no' => $student->id,
                    'fingerprint_enrolled_at' => Carbon::now()
                ]);

                Log::info("Fingerprint registered successfully for student: {$student->name}");
                return ['success' => true, 'message' => 'Fingerprint registered successfully'];
            }

            return ['success' => false, 'message' => 'Failed to register fingerprint on device'];

        } catch (\Exception $e) {
            Log::error("Hikvision fingerprint registration failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Device connection error'];
        }
    }

    /**
     * Fetch attendance records from Hikvision device
     */
    public function syncAttendanceFromDevice($sessionId, $startTime, $endTime)
    {
        try {
            $deviceUrl = "http://{$this->deviceConfig['host']}:{$this->deviceConfig['port']}/ISAPI/AccessControl/AcsEvent";
            
            $searchParams = [
                'searchID' => uniqid(),
                'searchResultPosition' => 0,
                'maxResults' => 1000,
                'AcsEventCond' => [
                    'searchTimeType' => 'startTime',
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'major' => 5,
                    'minor' => 75 // Card reader event
                ]
            ];

            $response = Http::withBasicAuth(
                $this->deviceConfig['username'],
                $this->deviceConfig['password']
            )->timeout($this->deviceConfig['timeout'])
              ->post($deviceUrl, $searchParams);

            if (!$response->successful()) {
                Log::error("Failed to fetch attendance from Hikvision device");
                return ['success' => false, 'records' => []];
            }

            $attendanceData = $response->json();
            $processedRecords = $this->processAttendanceData($sessionId, $attendanceData);

            return ['success' => true, 'records' => $processedRecords];

        } catch (\Exception $e) {
            Log::error("Hikvision attendance sync failed: " . $e->getMessage());
            return ['success' => false, 'records' => []];
        }
    }

    /**
     * Process raw attendance data from device
     */
    protected function processAttendanceData($sessionId, $rawData)
    {
        $processedRecords = [];
        $session = ClassSession::find($sessionId);
        
        if (!$session || !isset($rawData['AcsEvent'])) {
            return $processedRecords;
        }

        $events = is_array($rawData['AcsEvent']) ? $rawData['AcsEvent'] : [$rawData['AcsEvent']];

        foreach ($events as $event) {
            if (!isset($event['employeeNoString'])) continue;

            $studentId = $event['employeeNoString'];
            $checkInTime = Carbon::parse($event['time']);
            
            // Find student by device employee number
            $student = User::where('id', $studentId)
                          ->where('role', 'student')
                          ->first();

            if (!$student) continue;

            // Check if student is enrolled in this batch
            $isEnrolled = $session->batch->enrollments()
                                        ->where('student_id', $student->id)
                                        ->where('status', 'active')
                                        ->exists();

            if (!$isEnrolled) continue;

            // Determine attendance status based on time
            $sessionStartTime = Carbon::parse($session->session_date->format('Y-m-d') . ' ' . $session->start_time);
            $lateThreshold = $sessionStartTime->addMinutes(15); // 15 minutes late threshold

            $status = 'present';
            if ($checkInTime->gt($lateThreshold)) {
                $status = 'late';
            }

            $processedRecords[] = [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'check_in_time' => $checkInTime->format('H:i:s'),
                'status' => $status,
                'device_synced' => true,
                'synced_at' => Carbon::now()
            ];
        }

        return $processedRecords;
    }

    /**
     * Auto mark absent for students who didn't check in
     */
    public function markAbsentStudents($sessionId)
    {
        try {
            $session = ClassSession::with(['batch.enrollments.student', 'attendances'])->find($sessionId);
            
            if (!$session) {
                return ['success' => false, 'message' => 'Session not found'];
            }

            // Get all enrolled students
            $enrolledStudents = $session->batch->enrollments()
                                             ->where('status', 'active')
                                             ->with('student')
                                             ->get();

            // Get students who already have attendance marked
            $markedStudentIds = $session->attendances->pluck('student_id')->toArray();

            $absentCount = 0;

            foreach ($enrolledStudents as $enrollment) {
                $studentId = $enrollment->student_id;
                
                // If student doesn't have attendance marked, mark as absent
                if (!in_array($studentId, $markedStudentIds)) {
                    Attendance::create([
                        'class_session_id' => $session->id,
                        'student_id' => $studentId,
                        'status' => 'absent',
                        'check_in_time' => null,
                        'notes' => 'Auto-marked absent (not checked in)',
                        'auto_marked' => true,
                        'device_synced' => false
                    ]);
                    
                    $absentCount++;
                }
            }

            Log::info("Auto-marked {$absentCount} students as absent for session {$sessionId}");
            
            return [
                'success' => true, 
                'absent_count' => $absentCount,
                'message' => "Auto-marked {$absentCount} students as absent"
            ];

        } catch (\Exception $e) {
            Log::error("Auto absent marking failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to mark absent students'];
        }
    }

    /**
     * Start biometric attendance session for a class
     */
    public function startBiometricSession($sessionId)
    {
        try {
            $session = ClassSession::with('batch.course')->find($sessionId);
            
            if (!session) {
                return ['success' => false, 'message' => 'Session not found'];
            }

            // Update session to active biometric mode
            $session->update([
                'biometric_active' => true,
                'biometric_start_time' => Carbon::now(),
                'biometric_end_time' => Carbon::now()->addMinutes(30) // 30 min window
            ]);

            // Send activation command to device
            $deviceUrl = "http://{$this->deviceConfig['host']}:{$this->deviceConfig['port']}/ISAPI/AccessControl/RemoteControl/door/1";
            
            $response = Http::withBasicAuth(
                $this->deviceConfig['username'],
                $this->deviceConfig['password']
            )->put($deviceUrl, ['cmd' => 'open']);

            Log::info("Biometric attendance session started for session {$sessionId}");
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'biometric_window' => 30,
                'message' => 'Biometric attendance session started'
            ];

        } catch (\Exception $e) {
            Log::error("Failed to start biometric session: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to start biometric session'];
        }
    }

    /**
     * End biometric session and process final attendance
     */
    public function endBiometricSession($sessionId)
    {
        try {
            $session = ClassSession::find($sessionId);
            
            if (!$session) {
                return ['success' => false, 'message' => 'Session not found'];
            }

            // Sync any remaining attendance from device
            $startTime = $session->biometric_start_time;
            $endTime = Carbon::now();
            
            $syncResult = $this->syncAttendanceFromDevice($sessionId, $startTime, $endTime);
            
            if ($syncResult['success']) {
                // Process and save synced records
                foreach ($syncResult['records'] as $record) {
                    Attendance::updateOrCreate(
                        [
                            'class_session_id' => $sessionId,
                            'student_id' => $record['student_id']
                        ],
                        [
                            'status' => $record['status'],
                            'check_in_time' => $record['check_in_time'],
                            'device_synced' => true,
                            'notes' => 'Biometric check-in',
                            'synced_at' => Carbon::now()
                        ]
                    );
                }
            }

            // Auto mark absent for students who didn't check in
            $absentResult = $this->markAbsentStudents($sessionId);

            // Deactivate biometric session
            $session->update([
                'biometric_active' => false,
                'biometric_end_time' => Carbon::now()
            ]);

            Log::info("Biometric attendance session ended for session {$sessionId}");

            return [
                'success' => true,
                'synced_records' => count($syncResult['records'] ?? []),
                'absent_marked' => $absentResult['absent_count'] ?? 0,
                'message' => 'Biometric session completed successfully'
            ];

        } catch (\Exception $e) {
            Log::error("Failed to end biometric session: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to end biometric session'];
        }
    }

    /**
     * Remove student fingerprint from device
     */
    public function removeStudentFingerprint($deviceEmployeeNo)
    {
        try {
            $response = $this->httpClient->delete("/isapi/accessControl/UserInfo/{$deviceEmployeeNo}");
            
            if ($response->successful()) {
                \Log::info("Fingerprint removed from device", [
                    'device_employee_no' => $deviceEmployeeNo
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Fingerprint removed from device successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to remove fingerprint from device'
            ];
            
        } catch (\Exception $e) {
            \Log::error('Remove fingerprint error', [
                'device_employee_no' => $deviceEmployeeNo,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Test device connection
     */
    public function testConnection()
    {
        try {
            $deviceUrl = "http://{$this->deviceConfig['host']}:{$this->deviceConfig['port']}/ISAPI/System/deviceInfo";
            
            $response = Http::withBasicAuth(
                $this->deviceConfig['username'],
                $this->deviceConfig['password']
            )->timeout(10)->get($deviceUrl);

            if ($response->successful()) {
                $deviceInfo = $response->json();
                return [
                    'success' => true, 
                    'device_info' => $deviceInfo,
                    'message' => 'Device connection successful'
                ];
            }

            return ['success' => false, 'message' => 'Device not responding'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}