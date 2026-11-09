<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\BatchTeacherEarning;
use App\Models\Enrollment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is allowed only in local or testing environments.');
        }
        // Seed Settings first
        $this->call(SettingsSeeder::class);
        // Create Admin User
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '+92 123 456 7890',
            'is_active' => true,
        ]);


        // Create Teachers (a pool so each student can be assigned 2-4 of them)
        $teacherNames = [
            'Muhammad Imran',
            'Abdul Rehman',
            'Yasir Shabbir',
            'Tahir Mehmood',
            'Faisal Zafar',
            'Nadia Parveen',
        ];

        foreach ($teacherNames as $t => $teacherName) {
            User::create([
                'name' => $teacherName,
                'email' => $t === 0 ? 'teacher@lgl.com' : "teacher{$t}@lgl.com",
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'phone' => '+92 123 456 ' . (7892 + $t),
                'hourly_rate' => 50.00 + ($t * 5),
                'is_active' => true,
            ]);
        }

        // Create Students (Muslim names)
        $studentNames = [
            'Muhammad Ali', 'Ahmed Raza', 'Bilal Khan', 'Usman Ghani', 'Hamza Tariq',
            'Abdullah Yousuf', 'Zain Malik', 'Hassan Iqbal', 'Omar Farooq', 'Saad Hussain',
            'Ayesha Siddiqui', 'Fatima Noor', 'Maryam Shahid', 'Khadija Aslam', 'Zainab Riaz',
            'Amna Javed', 'Hira Saleem', 'Sana Rashid', 'Aisha Kamran', 'Rabia Nawaz',
        ];

        foreach ($studentNames as $s => $studentName) {
            $i = $s + 1;
            User::create([
                'name' => $studentName,
                'email' => "student{$i}@lgl.com",
                'password' => Hash::make('password'),
                'role' => 'student',
                'phone' => '+92 123 456 ' . (8000 + $i),
                'date_of_birth' => now()->subYears(rand(18, 45))->format('Y-m-d'),
                'address' => 'Student Address ' . $i . ', Lahore, Pakistan',
                'emergency_contact' => '+92 123 999 ' . (1000 + $i),
                'is_active' => true,
            ]);
        }

        // Create Courses
        $courses = [
            [
                'name' => 'German for Beginners',
                'level' => 'A1',
                'description' => 'Introduction to German language for complete beginners',
                'total_fee' => 35000,
                'teacher_per_student_amount' => 5000,
                'duration_weeks' => 12,
                'sessions_per_week' => 2,
                'session_duration_minutes' => 90,
            ],
            [
                'name' => 'German Elementary',
                'level' => 'A2',
                'description' => 'Elementary German language course',
                'total_fee' => 37000,
                'teacher_per_student_amount' => 5200,
                'duration_weeks' => 12,
                'sessions_per_week' => 2,
                'session_duration_minutes' => 90,
            ],
            [
                'name' => 'German Intermediate',
                'level' => 'B1',
                'description' => 'Intermediate German language course',
                'total_fee' => 40000,
                'teacher_per_student_amount' => 5500,
                'duration_weeks' => 14,
                'sessions_per_week' => 2,
                'session_duration_minutes' => 90,
            ],
            [
                'name' => 'German Upper Intermediate',
                'level' => 'B2',
                'description' => 'Upper intermediate German language course',
                'total_fee' => 42000,
                'teacher_per_student_amount' => 5700,
                'duration_weeks' => 14,
                'sessions_per_week' => 2,
                'session_duration_minutes' => 90,
            ],
            [
                'name' => 'German Advanced',
                'level' => 'C1',
                'description' => 'Advanced German language course',
                'total_fee' => 45000,
                'teacher_per_student_amount' => 6000,
                'duration_weeks' => 16,
                'sessions_per_week' => 2,
                'session_duration_minutes' => 90,
            ],
            [
                'name' => 'German Proficiency',
                'level' => 'C2',
                'description' => 'German proficiency course for native-level speakers',
                'total_fee' => 48000,
                'teacher_per_student_amount' => 6500,
                'duration_weeks' => 16,
                'sessions_per_week' => 2,
                'session_duration_minutes' => 90,
            ],
        ];

        foreach ($courses as $courseData) {
            Course::create($courseData);
        }

        // Create Batches: 5 batches, each with its own distinct set of students
        // (no student is shared between batches). Each batch has exactly ONE
        // teacher, paid a per-student amount (5k / 10k per student).
        $courses = Course::all();
        $teachers = User::teachers()->get();
        $students = User::students()->orderBy('id')->get();
        $salaryOptions = [5000, 10000];

        // Split the 20 students into 5 non-overlapping groups of 4
        $batchCount = 5;
        $studentGroups = $students->split($batchCount);

        foreach ($studentGroups as $index => $groupStudents) {
            $course = $courses[$index % $courses->count()];

            // One teacher per batch (rotate so each batch gets a different teacher)
            $teacher = $teachers[$index % $teachers->count()];
            $salary = $salaryOptions[array_rand($salaryOptions)];

            $batch = Batch::create([
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'name' => $course->level . '-' . now()->year . '-' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                'start_date' => now()->subDays(rand(7, 30)),
                'end_date' => now()->addDays(rand(60, 120)),
                'max_students' => max(15, $groupStudents->count()),
                'status' => 'ongoing',
                'meeting_platform' => ['in_person', 'zoom', 'google_meet'][rand(0, 2)],
                'meeting_link' => rand(0, 1) ? 'https://zoom.us/j/123456789' : null,
                'notes' => 'Active batch for ' . $course->name,
            ]);

            // Record this batch's single teacher on a per-student amount of 5k or 10k
            BatchTeacherEarning::create([
                'batch_id' => $batch->id,
                'teacher_id' => $teacher->id,
                'salary_type' => 'per_student',
                'salary_amount' => $salary,
            ]);

            // Enroll this batch's distinct students and record a demo part-payment
            foreach ($groupStudents as $student) {
                $enrollment = app(\App\Services\EnrollmentService::class)->enrollStudent(
                    $student->id, $batch->id, rand(0, 1) ? 10 : 0, 3
                );
                $net = \App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount);
                app(\App\Services\PaymentService::class)->pay(
                    $enrollment->id, \App\Support\Money::decimal(intdiv($net, 2)), 'cash',
                    (string) \Illuminate\Support\Str::uuid(), $admin, 'Demo seed payment'
                );
            }
        }

        $this->command?->info('Demo data seeded: Admin, Teacher and Student accounts');
    }
}
