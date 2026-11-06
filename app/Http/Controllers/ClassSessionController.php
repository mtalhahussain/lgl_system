<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Services\AttendanceService;
use App\Services\ClassTeachingService;
use App\Support\ListQuery;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher');
    }

    public function index(Request $request)
    {
        $defaultSort = 'date';
        $batches = Batch::query()->when(auth()->user()->isTeacher(), fn ($query) => $query->where('teacher_id', auth()->id()))
            ->orderBy('name')->pluck('name', 'id');
        $statuses = ['scheduled' => 'Scheduled', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        $list = ListQuery::for(ClassSession::with(['batch:id,name,course_id,teacher_id', 'batch.course:id,name', 'batch.teacher:id,name', 'makeupClasses', 'makeupFor'])
            ->whereIn('batch_id', $batches->keys()), $request)
            ->search(['topic', 'batch.name'])
            ->filter('batch_id', fn ($query, $id) => $query->where('batch_id', $id), $batches->keys()->all())
            ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
            ->dateRange('session_date')
            ->sorts(['date' => fn ($query, $direction) => $query->orderBy('session_date', $direction)->orderBy('start_time', $direction),
                'topic' => 'topic'], $defaultSort);
        if ($list->wantsCsv()) {
            return $list->csv('class-sessions', ['Batch' => fn ($session) => $session->batch?->name, 'Date' => fn ($session) => $session->session_date?->toDateString(),
                'Start' => fn ($session) => $session->start_time?->format('H:i'), 'Topic' => fn ($session) => $session->topic, 'Status' => fn ($session) => $session->status,
                'Teaching / coverage' => fn ($session) => $session->teachingLabel(),
                'Makeup for date' => fn ($session) => $session->makeupFor?->session_date?->toDateString()]);
        }
        $counts = $list->counts(['today' => ['DATE(class_sessions.session_date) = ?', [today()->toDateString()]],
            'week' => ['DATE(class_sessions.session_date) BETWEEN ? AND ?', [today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString()]],
            'cancelled' => "class_sessions.status = 'cancelled'"]);
        $kpis = [['label' => 'Sessions', 'value' => $counts['total'], 'icon' => 'fa-calendar-alt'],
            ['label' => 'Today', 'value' => $counts['today'], 'icon' => 'fa-calendar-day'],
            ['label' => 'This week', 'value' => $counts['week'], 'icon' => 'fa-calendar-week'],
            ['label' => 'Cancelled', 'value' => $counts['cancelled'], 'icon' => 'fa-ban']];
        $filters = [['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches], ['name' => 'status', 'label' => 'Status', 'options' => $statuses],
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date']];

        return view('admin.class-sessions.index', ['sessions' => $list->paginate(), 'kpis' => $kpis, 'filters' => $filters, 'defaultSort' => $defaultSort]);
    }

    public function create(Request $request)
    {
        $makeupFor = null;
        if ($request->filled('makeup_for_id')) {
            $makeupFor = ClassSession::findOrFail($request->integer('makeup_for_id'));
            $this->authorize('view', $makeupFor->batch);
            abort_unless($makeupFor->teaching_status === 'missed' && ! $makeupFor->makeup_for_id, 422);
        }
        // Get available batches based on role
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->with('course')
                ->whereIn('status', ['upcoming', 'ongoing', 'completed'])
                ->orderBy('name')
                ->get();
        } else {
            $batches = Batch::with('course')
                ->whereIn('status', ['upcoming', 'ongoing', 'completed'])
                ->orderBy('name')
                ->get();
        }

        return view('admin.class-sessions.form', ['batches' => $batches, 'classSession' => null, 'makeupFor' => $makeupFor]);
    }

    public const WEEKDAYS = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];

    private const MAX_GENERATED = 200;

    /** Creates one session per chosen weekday between two dates; dates that already have a session are skipped. */
    public function generate(Request $request, Batch $batch)
    {
        $this->authorize('view', $batch);
        abort_if(auth()->user()->isTeacher() && $batch->teacher_id !== auth()->id(), 403);
        $data = $request->validate([
            'days' => 'required|array|min:1', 'days.*' => 'in:'.implode(',', array_keys(self::WEEKDAYS)),
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
            'from' => 'required|date_format:Y-m-d|after_or_equal:today',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'topic' => 'required|string|max:255',
        ], ['days.required' => 'Choose at least one class day.']);
        if (! in_array($batch->status, ['upcoming', 'ongoing'], true)) {
            throw ValidationException::withMessages(['days' => 'Sessions can only be generated for upcoming or ongoing batches.']);
        }
        $from = max(Carbon::parse($data['from']), $batch->start_date->copy());
        $to = $batch->end_date ? min(Carbon::parse($data['to']), $batch->end_date->copy()) : Carbon::parse($data['to']);
        $dates = collect($from->lte($to) ? CarbonPeriod::create($from, $to) : [])
            ->filter(fn ($date) => in_array(strtolower($date->format('D')), $data['days'], true))->map(fn ($date) => $date->toDateString())->values();
        if ($dates->isEmpty()) {
            throw ValidationException::withMessages(['days' => 'No class day falls between these dates within the batch dates.']);
        }
        if ($dates->count() > self::MAX_GENERATED) {
            throw ValidationException::withMessages(['to' => 'At most '.self::MAX_GENERATED.' sessions at a time; choose a shorter date range.']);
        }
        $created = DB::transaction(function () use ($batch, $dates, $data) {
            Batch::lockForUpdate()->findOrFail($batch->id);
            // whereDate: a stored value may carry a time part, which whereBetween on the last date would miss.
            $existing = ClassSession::where('batch_id', $batch->id)->whereDate('session_date', '>=', $dates->first())
                ->whereDate('session_date', '<=', $dates->last())
                ->pluck('session_date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();
            $new = $dates->reject(fn ($date) => in_array($date, $existing, true))->values();
            foreach ($new as $date) {
                ClassSession::create(['batch_id' => $batch->id, 'session_date' => $date, 'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'], 'topic' => $data['topic'], 'status' => 'scheduled', 'meeting_link' => $batch->meeting_link]);
            }
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'class_sessions.generated', 'subject_type' => 'batch',
                'subject_id' => $batch->id, 'changes' => ['days' => $data['days'], 'from' => $dates->first(), 'to' => $dates->last(),
                    'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'created' => $new->count()], 'created_at' => now()]);

            return $new->count();
        });
        $skipped = $dates->count() - $created;

        return redirect()->route('batches.show', $batch)->with('success', "{$created} sessions created".($skipped ? ", {$skipped} dates already had a session" : '').'.');
    }

    /**
     * Creates sessions from the batch's weekly schedule: one per scheduled weekday in the
     * range, each at that day's own start/end time. Dates that already have a session at the
     * same start time are skipped, so reruns and multiple slots per day are safe.
     */
    public function generateFromSchedule(Request $request, Batch $batch)
    {
        $this->authorize('view', $batch);
        abort_if(auth()->user()->isTeacher() && $batch->teacher_id !== auth()->id(), 403);
        $data = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'topic' => 'nullable|string|max:255',
        ]);
        if (! in_array($batch->status, ['upcoming', 'ongoing'], true)) {
            throw ValidationException::withMessages(['schedule' => 'Sessions can only be generated for upcoming or ongoing batches.']);
        }
        $batch->load('schedules');
        if ($batch->schedules->isEmpty()) {
            throw ValidationException::withMessages(['schedule' => 'This batch has no weekly schedule yet. Add class days on the batch form first.']);
        }
        $from = max(Carbon::parse($data['from'] ?? today()->toDateString()), today(), $batch->start_date->copy());
        $to = $data['to'] ?? ($batch->end_date?->toDateString());
        if (! $to) {
            throw ValidationException::withMessages(['to' => 'Choose an end date, or set the batch end date first.']);
        }
        $to = min(Carbon::parse($to), $batch->end_date ? $batch->end_date->copy() : Carbon::parse($to));
        $byDay = $batch->schedules->groupBy('weekday');
        $slots = collect();
        foreach (collect($from->lte($to) ? CarbonPeriod::create($from->copy(), $to->copy()) : []) as $date) {
            foreach ($byDay->get(strtolower($date->format('D')), collect()) as $row) {
                $slots->push(['date' => $date->toDateString(), 'start' => $row->getRawOriginal('start_time'),
                    'end' => $row->getRawOriginal('end_time')]);
            }
        }
        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['schedule' => 'No scheduled class day falls between these dates within the batch dates.']);
        }
        if ($slots->count() > self::MAX_GENERATED) {
            throw ValidationException::withMessages(['to' => 'At most '.self::MAX_GENERATED.' sessions at a time; choose a shorter date range.']);
        }
        $created = DB::transaction(function () use ($batch, $slots, $data, $from, $to) {
            Batch::lockForUpdate()->findOrFail($batch->id);
            $existing = ClassSession::where('batch_id', $batch->id)->whereDate('session_date', '>=', $from->toDateString())
                ->whereDate('session_date', '<=', $to->toDateString())->get(['session_date', 'start_time'])
                ->map(fn ($session) => Carbon::parse($session->session_date)->toDateString().' '.Carbon::parse($session->getRawOriginal('start_time'))->format('H:i'))->all();
            $new = 0;
            foreach ($slots as $slot) {
                $key = $slot['date'].' '.Carbon::parse($slot['start'])->format('H:i');
                if (in_array($key, $existing, true)) {
                    continue;
                }
                ClassSession::create(['batch_id' => $batch->id, 'session_date' => $slot['date'], 'start_time' => $slot['start'],
                    'end_time' => $slot['end'], 'topic' => $data['topic'] ?? $batch->name.' class', 'status' => 'scheduled', 'meeting_link' => $batch->meeting_link]);
                $existing[] = $key;
                $new++;
            }
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'class_sessions.generated_from_schedule', 'subject_type' => 'batch',
                'subject_id' => $batch->id, 'changes' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'created' => $new], 'created_at' => now()]);

            return $new;
        });
        $skipped = $slots->count() - $created;

        return redirect()->route('batches.show', $batch)->with('success', "{$created} sessions created from schedule".($skipped ? ", {$skipped} already existed" : '').'.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'session_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'topic' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_link' => 'nullable|url:http,https|max:2000',
            'meeting_id' => 'nullable|string|max:100',
            'meeting_password' => 'nullable|string|max:100',
            'makeup_for_id' => 'nullable|integer|exists:class_sessions,id',
        ]);

        $batch = Batch::findOrFail($request->batch_id);

        // Check if teacher can create session for this batch
        if (auth()->user()->role === 'teacher' && $batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only create sessions for your assigned batches.');
        }

        $session = app(ClassTeachingService::class)->create([
            'batch_id' => $request->batch_id,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'topic' => $request->topic,
            'description' => $request->description,
            'is_online' => $request->is_online,
            'meeting_link' => $request->meeting_link,
            'meeting_id' => $request->meeting_id,
            'meeting_password' => $request->meeting_password,
            'status' => 'scheduled',
            'created_by' => auth()->id(),
            'makeup_for_id' => $request->input('makeup_for_id'),
        ]);

        return redirect()->route('class-sessions.index')
            ->with('success', 'Class session created successfully!');
    }

    public function show(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only view sessions for your assigned batches.');
        }

        $classSession->load(['batch.course:id,name,level', 'batch.teacher:id,name', 'attendances.student:id,name', 'makeupClasses', 'makeupFor', 'teachingRecorder:id,name']);

        $teacherPunches = \App\Models\BiometricPunch::where('user_id', $classSession->batch->teacher_id)
            ->where('result', 'teacher')->whereDate('punched_at', $classSession->session_date->toDateString())
            ->orderBy('punched_at')->get(['punched_at']);

        return view('admin.class-sessions.show', compact('classSession', 'teacherPunches'));
    }

    public function edit(ClassSession $classSession)
    {
        $this->authorize('view', $classSession->batch);
        if ($classSession->teaching_status || $classSession->makeup_for_id) {
            return redirect()->route('class-sessions.show', $classSession)->with('success', 'Recorded and linked makeup classes retain their original details. Cancel an unconfirmed makeup to schedule a replacement.');
        }
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only edit sessions for your assigned batches.');
        }

        // Get available batches based on role
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->with('course')
                ->where('status', 'ongoing')
                ->orderBy('name')
                ->get();
        } else {
            $batches = Batch::with('course')
                ->where('status', 'ongoing')
                ->orderBy('name')
                ->get();
        }

        return view('admin.class-sessions.form', compact('classSession', 'batches'));
    }

    public function update(Request $request, ClassSession $classSession)
    {
        return DB::transaction(function () use ($request, $classSession) {
            $classSession = ClassSession::lockForUpdate()->findOrFail($classSession->id);
            $this->authorize('view', $classSession->batch);
            if ($classSession->teaching_status || $classSession->makeup_for_id) {
                throw ValidationException::withMessages(['teaching' => 'Recorded and linked makeup classes retain their original details. Cancel an unconfirmed makeup and schedule a replacement from the original missed class.']);
            }
            // Check access permission
            if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
                abort(403, 'You can only update sessions for your assigned batches.');
            }

            $request->validate([
                'batch_id' => 'required|exists:batches,id',
                'session_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'topic' => 'required|string|max:255',
                'description' => 'nullable|string',
                'meeting_link' => 'nullable|url:http,https|max:2000',
                'meeting_id' => 'nullable|string|max:100',
                'meeting_password' => 'nullable|string|max:100',
            ]);

            $batch = Batch::findOrFail($request->batch_id);

            // Check if teacher can update session for this batch
            if (auth()->user()->role === 'teacher' && $batch->teacher_id !== auth()->id()) {
                abort(403, 'You can only update sessions for your assigned batches.');
            }

            if ($batch->id !== $classSession->batch_id && $classSession->attendances()->exists()) {
                throw ValidationException::withMessages(['batch_id' => 'A session with attendance cannot move to another batch.']);
            }

            $classSession->update([
                'batch_id' => $request->batch_id,
                'session_date' => $request->session_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'topic' => $request->topic,
                'description' => $request->description,
                'is_online' => $request->is_online,
                'meeting_link' => $request->meeting_link,
                'meeting_id' => $request->meeting_id,
                'meeting_password' => $request->meeting_password,
                'updated_by' => auth()->id(),
            ]);

            return redirect()->route('class-sessions.index')
                ->with('success', 'Class session updated successfully!');
        });
    }

    public function destroy(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only delete sessions for your assigned batches.');
        }

        DB::transaction(function () use ($classSession) {
            $locked = ClassSession::lockForUpdate()->findOrFail($classSession->id);
            $this->authorize('view', $locked->batch);
            if ($locked->teaching_status) {
                throw ValidationException::withMessages(['teaching' => 'Taken and missed teaching records cannot be cancelled.']);
            }
            $locked->update(['status' => 'cancelled']);
            if ($locked->makeup_for_id) {
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'class.makeup_cancelled',
                    'subject_type' => 'class_session', 'subject_id' => $locked->id,
                    'changes' => ['makeup_for_id' => $locked->makeup_for_id]]);
            }
        });

        return redirect()->route('class-sessions.index')
            ->with('success', 'Class session cancelled. Attendance history preserved.');
    }

    public function start(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only start sessions for your assigned batches.');
        }

        app(ClassTeachingService::class)->record($classSession, 'start');

        if ($classSession->isOnline() && $classSession->meeting_link) {
            return redirect()->away($classSession->meeting_link);
        }

        return redirect()->route('class-sessions.show', $classSession)
            ->with('success', 'Class session started!');
    }

    public function end(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only end sessions for your assigned batches.');
        }

        app(ClassTeachingService::class)->record($classSession, 'taken');

        app(\App\Services\NotificationService::class)->toAdmins([
            'type' => 'class', 'icon' => 'fa-chalkboard-user', 'level' => 'success',
            'title' => 'Class completed',
            'body' => ($classSession->batch->name ?? 'A batch').' class on '.($classSession->session_date?->format('d.m.Y') ?? '').' was taken.',
            'url' => route('class-sessions.show', $classSession),
        ], auth()->id());

        return redirect()->route('class-sessions.show', $classSession)
            ->with('success', 'Class session ended!');
    }

    public function markMissed(Request $request, ClassSession $classSession)
    {
        $this->authorize('view', $classSession->batch);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        app(ClassTeachingService::class)->record($classSession, 'missed', $data['reason']);

        $notifier = app(\App\Services\NotificationService::class);
        $when = $classSession->session_date?->format('d.m.Y') ?? '';
        $batchName = $classSession->batch->name ?? 'your batch';
        $notifier->toBatchStudents($classSession->batch_id, [
            'type' => 'class', 'icon' => 'fa-calendar-xmark', 'level' => 'warning',
            'title' => 'Class missed',
            'body' => "The {$batchName} class on {$when} was missed. A makeup will be scheduled.",
            'url' => route('class-sessions.show', $classSession),
        ]);
        $notifier->toAdmins([
            'type' => 'class', 'icon' => 'fa-calendar-xmark', 'level' => 'warning',
            'title' => 'Class marked missed',
            'body' => "{$batchName} class on {$when} was marked missed.",
            'url' => route('class-sessions.show', $classSession),
        ], auth()->id());

        return redirect()->route('class-sessions.show', $classSession)->with('success', 'Class recorded as missed. Schedule a linked makeup to cover it.');
    }

    public function markAttendance(ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only mark attendance for your assigned batches.');
        }

        $eligibleEnrollments = $classSession->eligibleEnrollments()->with('student:id,name')->get();

        return view('admin.class-sessions.attendance', compact('classSession', 'eligibleEnrollments'));
    }

    public function storeAttendance(Request $request, ClassSession $classSession)
    {
        // Check access permission
        if (auth()->user()->role === 'teacher' && $classSession->batch->teacher_id !== auth()->id()) {
            abort(403, 'You can only mark attendance for your assigned batches.');
        }

        if (is_array($request->input('attendance'))) {
            $request->merge(['attendance' => array_filter($request->input('attendance'), fn ($status) => $status !== null && $status !== '')]);
        }
        $request->validate([
            'attendance' => 'required|array',
            'attendance.*' => 'required|in:present,absent,late,excused',
            'notes' => 'nullable|array',
            'reason' => 'nullable|string|min:5|max:1000',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $rows = [];
        foreach ($request->attendance as $studentId => $status) {
            $rows[] = ['student_id' => $studentId, 'status' => $status, 'notes' => $request->input('notes.'.$studentId)];
        }
        app(AttendanceService::class)->markBulkAttendance($classSession->id, $rows, $request->input('reason'));

        return redirect()->route('class-sessions.show', $classSession)
            ->with('success', 'Attendance marked successfully!');
    }
}
