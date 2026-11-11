<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Lead;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function __construct(private EnrollmentService $enrollmentService) {}

    // Hours a qualify prefill link stays valid.
    private const PREFILL_TTL_HOURS = 48;

    // ---- Public intake (landing page, no auth) --------------------------------

    public function showApplyForm()
    {
        return view('public.apply.form');
    }

    public function storeApply(Request $request)
    {
        // Honeypot: real users leave this hidden field empty. Bots fill it.
        if (filled($request->input('website'))) {
            return redirect()->route('apply.thanks');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'cnic' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]{13,20}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
            'email' => 'nullable|string|email|max:255',
            'program_interest' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
        ]);

        $lead = Lead::create(array_merge($data, [
            'source' => Lead::SOURCE_PUBLIC,
            'status' => Lead::STATUS_NEW,
            'raw_payload' => $data,
        ]));

        return redirect()->route('apply.thanks');
    }

    public function thanks()
    {
        return view('public.apply.thanks');
    }

    // ---- Public prefilled completion (signed, status-gated) -------------------

    public function showComplete(Lead $lead)
    {
        $this->assertCompletable($lead);

        // Prefill from the latest known values; CNIC is locked in the view.
        $prefill = array_merge($lead->raw_payload ?? [], array_filter([
            'name' => $lead->name,
            'father_name' => $lead->father_name,
            'cnic' => $lead->cnic,
            'phone' => $lead->phone,
            'email' => $lead->email,
        ], fn ($v) => $v !== null));

        return view('public.apply.complete', [
            'lead' => $lead,
            'prefill' => $prefill,
            'action' => request()->fullUrl(),
        ]);
    }

    public function storeComplete(Request $request, Lead $lead)
    {
        $this->assertCompletable($lead);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
            'email' => 'nullable|string|email|max:255',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'emergency_contact' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
        ]);

        // CNIC stays the verified value from the lead (locked field).
        $lead->update([
            'name' => $data['name'],
            'father_name' => $data['father_name'] ?? $lead->father_name,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? $lead->email,
            'submitted_payload' => array_merge($data, ['cnic' => $lead->cnic]),
            'submitted_at' => now(),
            'status' => Lead::STATUS_SUBMITTED,
        ]);

        return redirect()->route('apply.thanks');
    }

    /** A lead can be completed only while qualified (one-time: submit flips it to submitted). */
    private function assertCompletable(Lead $lead): void
    {
        abort_unless($lead->status === Lead::STATUS_QUALIFIED, 410, 'This application link is no longer active.');
    }

    // ---- Admin pipeline (role:admin) -----------------------------------------

    public function index(Request $request)
    {
        $query = Lead::query()->with(['verifiedBy:id,name', 'student:id,name'])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('cnic', 'like', "%{$search}%");
            });
        }

        $leads = $query->paginate(25)->withQueryString();

        return view('admin.leads.index', compact('leads'));
    }

    public function show(Lead $lead)
    {
        $lead->load(['verifiedBy:id,name', 'assignedTo:id,name', 'student:id,name']);

        $duplicates = $this->duplicatesFor($lead);
        $prefillUrl = $lead->isQualified() ? $this->prefillUrl($lead) : null;

        return view('admin.leads.show', compact('lead', 'duplicates', 'prefillUrl'));
    }

    public function qualify(Lead $lead)
    {
        abort_unless(in_array($lead->status, [Lead::STATUS_NEW, Lead::STATUS_CONTACTED], true), 422, 'Lead cannot be qualified from its current status.');

        $lead->update([
            'status' => Lead::STATUS_QUALIFIED,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return redirect()->route('leads.show', $lead)
            ->with('success', 'Lead qualified. Share the application link below with the applicant.');
    }

    public function reject(Request $request, Lead $lead)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        abort_if(in_array($lead->status, [Lead::STATUS_CONVERTED, Lead::STATUS_REJECTED], true), 422, 'Lead is already closed.');

        $lead->update([
            'status' => Lead::STATUS_REJECTED,
            'rejected_reason' => $request->input('reason'),
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return redirect()->route('leads.show', $lead)->with('success', 'Lead rejected.');
    }

    public function convert(Lead $lead)
    {
        abort_unless($lead->status === Lead::STATUS_SUBMITTED, 422, 'The applicant has not completed their registration yet.');

        $prefill = array_merge($lead->raw_payload ?? [], $lead->submitted_payload ?? []);
        $duplicates = $this->duplicatesFor($lead);
        $batches = Batch::where('status', '!=', 'cancelled')->orderBy('name')->get(['id', 'name']);

        return view('admin.leads.convert', compact('lead', 'prefill', 'duplicates', 'batches'));
    }

    public function storeConvert(Request $request, Lead $lead)
    {
        abort_unless($lead->status === Lead::STATUS_SUBMITTED, 422, 'The applicant has not completed their registration yet.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'father_name' => 'nullable|string|max:255',
            'cnic' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]{13,20}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'emergency_contact' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
            'password' => 'nullable|required_with:password_confirmation|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
            'batch_id' => 'nullable|exists:batches,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            // Re-check the lead is still open under the write to avoid a double-convert race.
            $fresh = Lead::whereKey($lead->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== Lead::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'This lead has already been converted.']);
            }

            $student = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'father_name' => $data['father_name'] ?? null,
                'cnic' => $data['cnic'] ?? null,
                'password' => Hash::make($request->filled('password') ? $data['password'] : Str::random(64)),
                'role' => 'student',
                'phone' => $data['phone'],
                'address' => $data['address'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if (! empty($data['batch_id'])) {
                $batch = Batch::findOrFail($data['batch_id']);
                $this->enrollmentService->enrollStudent(
                    $student->id,
                    $batch->id,
                    $data['discount_percentage'] ?? 0
                );
            }

            $fresh->update([
                'status' => Lead::STATUS_CONVERTED,
                'student_id' => $student->id,
                'converted_at' => now(),
            ]);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Failed to create student: '.$e->getMessage()]);
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student created from lead and ready.');
    }

    // ---- Helpers --------------------------------------------------------------

    /** Signed, time-limited prefill link for a qualified lead. */
    private function prefillUrl(Lead $lead): string
    {
        return URL::temporarySignedRoute('apply.complete', now()->addHours(self::PREFILL_TTL_HOURS), ['lead' => $lead->id]);
    }

    /** Existing students and other open leads sharing this lead's CNIC or phone. */
    private function duplicatesFor(Lead $lead): array
    {
        $users = User::where('role', 'student')
            ->where(function ($q) use ($lead) {
                $q->where('phone', $lead->phone);
                if ($lead->cnic) {
                    $q->orWhere('cnic', $lead->cnic);
                }
            })
            ->get(['id', 'name', 'phone', 'cnic']);

        $leads = Lead::open()
            ->whereKeyNot($lead->id)
            ->where(function ($q) use ($lead) {
                $q->where('phone', $lead->phone);
                if ($lead->cnic) {
                    $q->orWhere('cnic', $lead->cnic);
                }
            })
            ->get(['id', 'name', 'phone', 'cnic', 'status']);

        return ['students' => $users, 'leads' => $leads];
    }
}
