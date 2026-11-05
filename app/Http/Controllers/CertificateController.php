<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentCertificate;
use App\Models\StudentExamResult;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin')->except(['print']);
    }

    public function create(Request $request)
    {
        $students = User::students()
            ->with(['enrollments' => fn ($q) => $q->orderByDesc('enrollment_date')->with('batch.course:id,level')])
            ->orderBy('name')->get(['id', 'name']);

        // id => ['name' => ..., 'level' => ...] for the form's auto-fill.
        $studentMeta = $students->mapWithKeys(fn ($s) => [$s->id => [
            'name' => $s->name,
            'level' => optional($s->enrollments->first()?->batch?->course)->level ?? '',
        ]]);

        $preselect = $request->filled('student') ? $studentMeta->get((int) $request->query('student')) : null;
        $preselectId = $preselect ? (int) $request->query('student') : null;

        if ($request->filled('result')) {
            $result = StudentExamResult::with('batchExam.batch.course:id,level', 'student:id,name')
                ->where('overall_passed', true)->find((int) $request->query('result'));
            if ($result) {
                $preselect = [
                    'name' => $result->student?->name ?? '',
                    'level' => $result->batchExam?->batch?->course?->level ?? '',
                ];
                $preselectId = $result->student_id;
                $preselectResultId = $result->id;
            }
        }
        $preselectResultId ??= null;

        $certificates = StudentCertificate::with('student:id,name')
            ->orderByDesc('issued_at')->limit(50)->get();

        return view('certificates.index', compact('students', 'studentMeta', 'preselect', 'preselectId', 'preselectResultId', 'certificates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required_with:student_exam_result_id', 'nullable', Rule::exists('users', 'id')->where('role', 'student')],
            'student_exam_result_id' => ['nullable', Rule::exists('student_exam_results', 'id')
                ->where('overall_passed', true)],
            'student_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'completion_date' => 'required|date',
        ]);

        // When issuing from an exam result, the level is authoritative from that result's course.
        if (! empty($data['student_exam_result_id'])) {
            $resultLevel = StudentExamResult::whereKey($data['student_exam_result_id'])
                ->with('batchExam.batch.course:id,level')->first()?->batchExam?->batch?->course?->level;
            if ($resultLevel) {
                $data['level'] = $resultLevel;
            }
        }

        // One active (non-revoked) certificate per student + level.
        if (! empty($data['student_id'])) {
            $exists = StudentCertificate::active()
                ->where('student_id', $data['student_id'])->where('level', $data['level'])->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student already has an active '.$data['level'].' certificate. Revoke it first to reissue.',
                ]);
            }
        }

        if (! empty($data['student_exam_result_id']) && ! empty($data['student_id'])) {
            $linkedStudentId = StudentExamResult::whereKey($data['student_exam_result_id'])->value('student_id');
            if ($linkedStudentId !== null && (int) $linkedStudentId !== (int) $data['student_id']) {
                throw ValidationException::withMessages([
                    'student_exam_result_id' => 'The selected exam result does not belong to this student.',
                ]);
            }
        }

        // Retry on the rare race where two issues compute the same number: the
        // unique index rejects the loser, which then regenerates and retries.
        $cert = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $cert = DB::transaction(function () use ($data, $request) {
                    $cert = StudentCertificate::create([
                        'student_id' => $data['student_id'] ?? null,
                        'student_exam_result_id' => $data['student_exam_result_id'] ?? null,
                        'student_name' => $data['student_name'],
                        'father_name' => $data['father_name'] ?? null,
                        'level' => $data['level'],
                        'completion_date' => $data['completion_date'],
                        'certificate_number' => StudentCertificate::nextNumber($data['level']),
                        'issued_by' => $request->user()->id,
                        'issued_at' => now(),
                    ]);

                    AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'certificate.issued',
                        'subject_type' => 'student_certificate', 'subject_id' => $cert->id, 'changes' => []]);

                    return $cert;
                });
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        return redirect()->route('certificates.print', $cert)
            ->with('success', 'Certificate '.$cert->certificate_number.' issued.');
    }

    public function print(StudentCertificate $certificate)
    {
        $user = auth()->user();
        abort_if($user->isStudent() && (int) $certificate->student_id !== (int) $user->id, 403);

        return view('certificates.print', ['cert' => $certificate]);
    }

    public function revoke(Request $request, StudentCertificate $certificate)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        DB::transaction(function () use ($certificate, $data, $request) {
            $locked = StudentCertificate::whereKey($certificate->id)->lockForUpdate()->first();
            if ($locked->isRevoked()) {
                throw ValidationException::withMessages([
                    'reason' => 'This certificate is already revoked.',
                ]);
            }
            $locked->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id,
                'revoke_reason' => $data['reason']]);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'certificate.revoked',
                'subject_type' => 'student_certificate', 'subject_id' => $locked->id,
                'changes' => ['revoke_reason' => $data['reason']]]);
        });

        return back()->with('success', 'Certificate '.$certificate->certificate_number.' revoked.');
    }
}
