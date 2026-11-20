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
        $this->middleware('role:admin')->except('staffDashboard');
    }

    public function dashboard()
    {
        $stats = ['Active students' => User::students()->where('is_active', true)->whereHas('enrollments', fn ($query) => $query->where('status', 'active'))->count(),
            'Teachers' => User::teachers()->where('is_active', true)->count(), 'Ongoing batches' => Batch::where('status', 'ongoing')->count()];
        $finance = \Illuminate\Support\Facades\Schema::hasTable('payments') ? app(\App\Services\OverviewService::class)->financialTotals() : null;
        $recent = Enrollment::with(['student:id,name', 'batch:id,name'])->orderByDesc('id')->limit(10)->get();
        $reminders = app(\App\Services\OverviewService::class)->batchReminders();
        $teacherNextClasses = app(\App\Services\TeacherScheduleService::class)->upcomingForTeachers();
        $endingSoon = app(\App\Services\TeacherScheduleService::class)->batchesEndingSoon();

        return view('dashboards.admin', compact('stats', 'finance', 'recent', 'reminders', 'teacherNextClasses', 'endingSoon'));
    }

    /** Staff home: shortcuts to the modules Admin granted to Staff (no finance totals). */
    public function staffDashboard(Request $request)
    {
        $user = $request->user();
        $modules = collect(\App\Models\RolePermission::modulesFor('staff'))
            ->map(fn ($meta, $module) => $meta + ['granted' => collect(array_keys(\App\Models\RolePermission::actionsFor('staff', $module)))
                ->filter(fn ($action) => \App\Models\RolePermission::allows($user, $module, $action))->values()->all()])
            ->filter(fn ($meta) => $meta['granted'] !== []);

        return view('dashboards.staff', compact('modules'));
    }
}
