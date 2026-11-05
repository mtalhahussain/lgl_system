<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchExam;
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
        $this->middleware('role:admin,teacher');
        $this->middleware('role:admin')->only(['create', 'store', 'edit', 'update']);
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
            'sections.*.max_marks' => ['required', 'numeric', 'min:1'],
            'sections.*.passing_marks' => ['required', 'numeric', 'min:0'],
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
