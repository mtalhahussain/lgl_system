<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchExam;
use App\Models\StudentExamResult;
use App\Models\User;
use App\Services\ExamGradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->except(['studentResults']);
        $this->middleware('role:admin')->only(['create', 'store', 'edit', 'update']);
    }

    public function studentResults(Request $request)
    {
        $batches = Batch::whereHas('enrollments', fn ($query) => $query->where('student_id', $request->user()->id))
            ->orderBy('name')->pluck('name', 'id');
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'batch_id' => ['nullable', 'integer', Rule::in($batches->keys()->all())],
            'status' => ['nullable', Rule::in(['scheduled', 'recorded', 'passed', 'failed'])],
        ]);
        $scheduledQuery = BatchExam::where('status', 'scheduled')
            ->whereDoesntHave('results', fn ($query) => $query->where('student_id', $request->user()->id))
            ->whereHas('batch.enrollments', fn ($query) => $query->where('student_id', $request->user()->id)
                ->whereIn('status', ['active', 'completed']))
            ->with('batch.course:id,name,level');
        $resultsQuery = StudentExamResult::where('student_id', $request->user()->id)
            ->with(['batchExam.batch.course:id,level', 'sectionMarks.section', 'activeCertificate'])
            ->orderByDesc('graded_at');
        $kpis = [
            ['label' => 'Scheduled', 'value' => (clone $scheduledQuery)->count(), 'icon' => 'fa-calendar-day'],
            ['label' => 'Results available', 'value' => (clone $resultsQuery)->count(), 'icon' => 'fa-file-circle-check'],
            ['label' => 'Passed', 'value' => (clone $resultsQuery)->where('overall_passed', true)->count(), 'icon' => 'fa-circle-check'],
            ['label' => 'Failed', 'value' => (clone $resultsQuery)->where('overall_passed', false)->count(), 'icon' => 'fa-circle-xmark'],
        ];
        if (! empty($data['search'])) {
            $search = '%'.trim($data['search']).'%';
            $scheduledQuery->where('name', 'like', $search);
            $resultsQuery->whereHas('batchExam', fn ($query) => $query->where('name', 'like', $search));
        }
        if (! empty($data['batch_id'])) {
            $scheduledQuery->where('batch_id', $data['batch_id']);
            $resultsQuery->whereHas('batchExam', fn ($query) => $query->where('batch_id', $data['batch_id']));
        }
        $status = $data['status'] ?? '';
        if ($status === 'scheduled') {
            $resultsQuery->whereRaw('1 = 0');
        } elseif (in_array($status, ['recorded', 'passed', 'failed'], true)) {
            $scheduledQuery->whereRaw('1 = 0');
            if ($status !== 'recorded') {
                $resultsQuery->where('overall_passed', $status === 'passed');
            }
        }
        $scheduledExams = $scheduledQuery->orderBy('exam_date')->get();
        $results = $resultsQuery->get();
        $filters = [
            ['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches->all()],
            ['name' => 'status', 'label' => 'Status', 'options' => ['scheduled' => 'Scheduled', 'recorded' => 'Results available', 'passed' => 'Passed', 'failed' => 'Failed']],
        ];

        return view('exams.student-results', compact('results', 'scheduledExams', 'kpis', 'filters'));
    }

    public function index(Request $request)
    {
        $exams = BatchExam::query()
            ->with('batch.course:id,name,level')
            ->when(auth()->user()->isTeacher(),
                fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('teacher_id', auth()->id())))
            ->when($request->filled('batch'), fn ($q) => $q->where('batch_id', (int) $request->query('batch')))
            ->orderByDesc('exam_date')->get();

        return view('exams.index', compact('exams'));
    }

    public function create()
    {
        $batches = Batch::with('course:id,name,level')->orderBy('name')->get();

        return view('exams.form', ['exam' => null, 'batches' => $batches, 'sectionNames' => BatchExam::SECTIONS]);
    }

    public function store(Request $request)
    {
        $data = $this->validateExam($request);

        $exam = DB::transaction(function () use ($data) {
            $exam = BatchExam::create([
                'batch_id' => $data['batch_id'], 'name' => $data['name'],
                'type' => $data['type'], 'exam_date' => $data['exam_date'], 'status' => 'scheduled',
            ]);
            foreach ($data['sections'] as $i => $s) {
                $exam->sections()->create(['name' => $s['name'], 'max_marks' => $s['max_marks'],
                    'passing_marks' => $s['passing_marks'], 'sort' => $i + 1]);
            }

            return $exam;
        });

        $notifier = app(\App\Services\NotificationService::class);
        $examDate = $exam->exam_date instanceof \Carbon\Carbon ? $exam->exam_date->format('d.m.Y') : (string) $exam->exam_date;
        $notifier->toBatchStudents($exam->batch_id, [
            'type' => 'exam', 'icon' => 'fa-file-pen', 'level' => 'info',
            'title' => 'Exam scheduled',
            'body' => "{$exam->name} is scheduled for {$examDate}.",
            'url' => route('student.results'),
        ]);
        $notifier->toAdmins([
            'type' => 'exam', 'icon' => 'fa-file-pen', 'level' => 'info',
            'title' => 'Exam created',
            'body' => "{$exam->name} scheduled for {$examDate}.",
            'url' => route('exams.show', $exam),
        ], auth()->id());

        return redirect()->route('exams.show', $exam)->with('success', 'Exam created.');
    }

    public function edit(BatchExam $exam)
    {
        $batches = Batch::with('course:id,name,level')->orderBy('name')->get();
        $exam->load('sections');

        return view('exams.form', ['exam' => $exam, 'batches' => $batches, 'sectionNames' => BatchExam::SECTIONS]);
    }

    public function update(Request $request, BatchExam $exam)
    {
        $data = $this->validateExam($request);

        if ($exam->results()->exists()) {
            if ((int) $data['batch_id'] !== $exam->batch_id) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Cannot change the batch after results have been recorded.',
                ]);
            }
            $exam->load('sections');
            foreach ($data['sections'] as $s) {
                $section = $exam->sections->firstWhere('name', $s['name']);
                if ($section && ((float) $section->max_marks !== (float) $s['max_marks']
                    || (float) $section->passing_marks !== (float) $s['passing_marks'])) {
                    throw ValidationException::withMessages([
                        'sections' => 'Cannot change section marks after results have been recorded.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($exam, $data) {
            $exam->update(['batch_id' => $data['batch_id'], 'name' => $data['name'],
                'type' => $data['type'], 'exam_date' => $data['exam_date']]);
            foreach ($data['sections'] as $i => $s) {
                $exam->sections()->updateOrCreate(['name' => $s['name']],
                    ['max_marks' => $s['max_marks'], 'passing_marks' => $s['passing_marks'], 'sort' => $i + 1]);
            }
        });

        return redirect()->route('exams.show', $exam)->with('success', 'Exam updated.');
    }

    public function show(BatchExam $exam)
    {
        $this->authorizeTeacher($exam);
        $exam->load(['batch.course', 'sections', 'results.sectionMarks', 'results.activeCertificate']);

        $students = User::students()
            ->whereHas('enrollments', fn ($q) => $q->where('batch_id', $exam->batch_id)
                ->whereIn('status', ['active', 'completed']))
            ->orderBy('name')->get(['id', 'name']);

        $resultsByStudent = $exam->results->keyBy('student_id');

        return view('exams.show', compact('exam', 'students', 'resultsByStudent'));
    }

    public function saveResults(Request $request, BatchExam $exam, ExamGradingService $service)
    {
        $exam->load('sections');
        $this->authorizeTeacher($exam);

        $validated = $request->validate([
            'student_id' => ['required', Rule::exists('enrollments', 'student_id')
                ->where('batch_id', $exam->batch_id)->whereIn('status', ['active', 'completed'])],
            'marks' => ['required', 'array'],
        ]);

        $marks = collect($validated['marks'])->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all();
        $service->saveStudentResult($exam, (int) $validated['student_id'], $marks, $request->user());

        $notifier = app(\App\Services\NotificationService::class);
        $notifier->toUser((int) $validated['student_id'], [
            'type' => 'exam_result', 'icon' => 'fa-file-circle-check', 'level' => 'success',
            'title' => 'Exam result published',
            'body' => "Your result for {$exam->name} is available.",
            'url' => route('student.results'),
        ]);
        $notifier->toAdmins([
            'type' => 'exam_result', 'icon' => 'fa-file-circle-check', 'level' => 'info',
            'title' => 'Exam result recorded',
            'body' => "A result for {$exam->name} was recorded.",
            'url' => route('exams.show', $exam),
        ], $request->user()->id);

        return back()->with('success', 'Result saved.');
    }

    private function validateExam(Request $request): array
    {
        $data = $request->validate([
            'batch_id' => ['required', Rule::exists('batches', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['final', 'mid', 'retake', 'other'])],
            'exam_date' => ['required', 'date'],
            'sections' => ['required', 'array', 'size:4'],
            'sections.*.name' => ['required', 'distinct', Rule::in(BatchExam::SECTIONS)],
            'sections.*.max_marks' => ['required', 'numeric', 'min:1', 'max:9999.99'],
            'sections.*.passing_marks' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        foreach ($request->input('sections', []) as $i => $s) {
            if (isset($s['max_marks'], $s['passing_marks']) && (float) $s['passing_marks'] > (float) $s['max_marks']) {
                throw ValidationException::withMessages([
                    "sections.$i.passing_marks" => 'Passing marks cannot exceed max marks.',
                ]);
            }
        }

        return $data;
    }

    private function authorizeTeacher(BatchExam $exam): void
    {
        abort_if(auth()->user()->isTeacher() && $exam->batch->teacher_id !== auth()->id(), 403);
    }
}
