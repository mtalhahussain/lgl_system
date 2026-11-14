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
        $stats = ['Active students' => User::students()->where('is_active', true)->whereHas('enrollments', fn ($query) => $query->where('status', 'active'))->count(),
            'Teachers' => User::teachers()->where('is_active', true)->count(), 'Ongoing batches' => Batch::where('status', 'ongoing')->count()];
        $finance = \Illuminate\Support\Facades\Schema::hasTable('payments') ? app(\App\Services\OverviewService::class)->financialTotals() : null;
        $recent = Enrollment::with(['student:id,name', 'batch:id,name'])->orderByDesc('id')->limit(10)->get();
        $reminders = app(\App\Services\OverviewService::class)->batchReminders();

        return view('dashboards.admin', compact('stats', 'finance', 'recent', 'reminders'));
    }
}
