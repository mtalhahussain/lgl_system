<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\FeeInstallment;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function dashboard()
    {
        $stats = [
            'total_students' => User::where('role', 'student')->count(),
            'active_students' => User::where('role', 'student')->where('is_active', true)->count(),
            'total_teachers' => User::where('role', 'teacher')->count(),
            'active_teachers' => User::where('role', 'teacher')->where('is_active', true)->count(),
            'total_courses' => Course::count(),
            'active_courses' => Course::where('is_active', true)->count(),
            'total_batches' => Batch::count(),
            'active_batches' => Batch::where('status', 'ongoing')->count(),
            'total_revenue' => FeeInstallment::where('status', 'paid')->sum('amount') ?? 0,
            'pending_fees' => FeeInstallment::where('status', 'pending')->sum('amount') ?? 0,
            'total_enrollments' => Enrollment::count(),
            'active_enrollments' => Enrollment::where('status', 'active')->count()
        ];

        $recentActivities = [
            [
                'icon' => 'fas fa-user-plus',
                'color' => 'success',
                'title' => 'New student enrollment',
                'description' => 'Maria Schmidt (A1 Course)',
                'time' => '2 hours ago'
            ],
            [
                'icon' => 'fas fa-euro-sign',
                'color' => 'info',
                'title' => 'Payment received',
                'description' => currency_format(300) . ' from Johann Weber',
                'time' => '3 hours ago'
            ],
            [
                'icon' => 'fas fa-calendar-alt',
                'color' => 'warning',
                'title' => 'New batch created',
                'description' => 'B2 Evening Class',
                'time' => '1 day ago'
            ],
            [
                'icon' => 'fas fa-chalkboard-teacher',
                'color' => 'primary',
                'title' => 'Teacher assigned',
                'description' => 'Anna Mueller assigned to A2 Morning',
                'time' => '2 days ago'
            ]
        ];

        return view('admin.dashboard', compact('stats', 'recentActivities'));
    }
}