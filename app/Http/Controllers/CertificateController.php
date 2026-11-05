<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentCertificate;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CertificateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
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

        $certificates = StudentCertificate::with('student:id,name')
            ->orderByDesc('issued_at')->limit(50)->get();

        return view('certificates.index', compact('students', 'studentMeta', 'preselect', 'preselectId', 'certificates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'student')],
            'student_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'level' => 'required|in:A1,A2,B1,B2,C1,C2',
            'completion_date' => 'required|date',
        ]);

        // Retry on the rare race where two issues compute the same number: the
        // unique index rejects the loser, which then regenerates and retries.
        $cert = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $cert = DB::transaction(function () use ($data, $request) {
                    $cert = StudentCertificate::create([
                        'student_id' => $data['student_id'] ?? null,
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
        return view('certificates.print', ['cert' => $certificate]);
    }
}
