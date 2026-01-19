<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use Carbon\Carbon;

class AutoMarkAbsentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:auto-absent 
                            {--grace-period=15 : Grace period in minutes after class starts}
                            {--date= : Specific date to process (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark students absent who have not checked in after grace period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gracePeriod = $this->option('grace-period');
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        
        $this->info("Processing auto-absent for date: {$date->format('Y-m-d')}");
        $this->info("Grace period: {$gracePeriod} minutes after class start time");
        
        // Get sessions that should have auto-absent processing
        $sessions = ClassSession::whereDate('session_date', $date)
            ->where('status', 'ongoing')
            ->where(function($query) use ($gracePeriod) {
                // Sessions where grace period has passed
                $query->whereRaw("ADDTIME(CONCAT(session_date, ' ', start_time), '00:{$gracePeriod}:00') < NOW()");
            })
            ->with(['batch.enrollments.student', 'attendances'])
            ->get();
            
        if ($sessions->isEmpty()) {
            $this->info('No sessions found that need auto-absent processing.');
            return;
        }
        
        $totalProcessed = 0;
        $totalMarkedAbsent = 0;
        
        foreach ($sessions as $session) {
            $this->line("Processing: {$session->batch->name} - {$session->topic}");
            
            $result = $this->processSessionAbsent($session);
            $totalProcessed++;
            $totalMarkedAbsent += $result['marked_absent'];
            
            $this->info("  → Marked {$result['marked_absent']} students absent");
        }
        
        $this->info("\n✅ Summary:");
        $this->info("Sessions processed: {$totalProcessed}");
        $this->info("Total students marked absent: {$totalMarkedAbsent}");
        
        return 0;
    }
    
    /**
     * Process auto-absent for a specific session
     */
    private function processSessionAbsent($session)
    {
        $enrolledStudents = $session->batch->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->get();

        $markedStudentIds = $session->attendances->pluck('student_id')->toArray();
        $markedAbsent = 0;

        foreach ($enrolledStudents as $enrollment) {
            if (!in_array($enrollment->student_id, $markedStudentIds)) {
                Attendance::create([
                    'class_session_id' => $session->id,
                    'student_id' => $enrollment->student_id,
                    'status' => 'absent',
                    'notes' => 'Auto-marked absent by scheduler',
                    'auto_marked' => true,
                    'device_synced' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $markedAbsent++;
                $this->line("    - Marked absent: {$enrollment->student->name}");
            }
        }
        
        return [
            'marked_absent' => $markedAbsent,
            'total_enrolled' => $enrolledStudents->count()
        ];
    }
}
