<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    // ---- Public intake (landing page, no auth) --------------------------------

    public function showApplyForm()
    {
        return view('public.apply.form', ['inquiryAction' => request()->routeIs('inquiry.form') ? request()->fullUrl() : route('apply.store')]);
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
            'program_interest' => ['required', \Illuminate\Validation\Rule::in(\App\Services\EnrollmentService::LEVELS)],
            'message' => 'nullable|string|max:1000',
        ], [], ['program_interest' => 'level of interest']);

        try {
            \Illuminate\Support\Facades\Cache::lock('inquiry:phone:'.hash('sha256', $data['phone']), 10)->block(3, function () use ($data) {
                // Repeat submissions get the same public response without exposing contact records.
                if (Lead::where('source', Lead::SOURCE_PUBLIC)->where('phone', $data['phone'])
                    ->where('created_at', '>=', now()->subDay())->exists()) {
                    return;
                }
                Lead::create(array_merge($data, [
                    'source' => Lead::SOURCE_PUBLIC, 'status' => Lead::STATUS_NEW, 'raw_payload' => $data,
                ]));
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            abort(429, 'Please wait a moment before submitting again.');
        }

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

        $linkExpiry = in_array($request->query('link_expiry'), ['24h', '7d'], true) ? $request->query('link_expiry') : 'permanent';
        $inquiryUrl = $linkExpiry === 'permanent' ? route('apply.form')
            : \Illuminate\Support\Facades\URL::temporarySignedRoute('inquiry.form', $linkExpiry === '24h' ? now()->addDay() : now()->addDays(7));

        return view('admin.leads.index', compact('leads', 'linkExpiry', 'inquiryUrl'));
    }

    // ---- Manual leads (walk-in, phone call, referral) -------------------------

    /** Statuses an Admin may set by hand; qualify/reject/convert keep their own actions. */
    private const MANUAL_STATUSES = [Lead::STATUS_NEW, Lead::STATUS_CONTACTED];

    public function create()
    {
        return view('admin.leads.form', ['lead' => null, 'statuses' => self::MANUAL_STATUSES]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedManual($request);
        $lead = Lead::create($data + ['source' => Lead::SOURCE_MANUAL, 'raw_payload' => $data, 'assigned_to' => auth()->id()]);

        return redirect()->route('leads.show', $lead)->with('success', 'Lead added.');
    }

    public function edit(Lead $lead)
    {
        $this->assertEditable($lead);

        return view('admin.leads.form', ['lead' => $lead, 'statuses' => self::MANUAL_STATUSES]);
    }

    public function update(Request $request, Lead $lead)
    {
        $this->assertEditable($lead);
        $data = $this->validatedManual($request, $lead);
        $lead->update($data);

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function destroy(Lead $lead)
    {
        // A converted lead is the admission record of a student; keep it.
        abort_if($lead->isConverted(), 422, 'Converted leads cannot be deleted.');
        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead deleted.');
    }

    private function assertEditable(Lead $lead): void
    {
        abort_if(in_array($lead->status, [Lead::STATUS_SUBMITTED, Lead::STATUS_CONVERTED], true), 422,
            'Submitted or converted leads cannot be edited.');
    }

    private function validatedManual(Request $request, ?Lead $lead = null): array
    {
        // Status is chosen only while the lead is still new/contacted; other states keep their workflow status.
        $statusEditable = ! $lead || in_array($lead->status, self::MANUAL_STATUSES, true);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'cnic' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]{13,20}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/D'],
            'email' => 'nullable|string|email|max:255',
            'program_interest' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
            'status' => [$statusEditable ? 'required' : 'prohibited', 'in:'.implode(',', self::MANUAL_STATUSES)],
        ]);

        return $data;
    }

    public function show(Lead $lead)
    {
        $lead->load(['verifiedBy:id,name', 'assignedTo:id,name', 'student:id,name']);

        $duplicates = $this->duplicatesFor($lead);
        return view('admin.leads.show', compact('lead', 'duplicates'));
    }

    public function contact(Lead $lead)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($lead) {
            $fresh = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($fresh->status, [Lead::STATUS_NEW, Lead::STATUS_CONTACTED], true), 422, 'This lead is no longer awaiting contact.');
            $fresh->update(['status' => Lead::STATUS_CONTACTED, 'assigned_to' => auth()->id()]);
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Lead marked as contacted.');
    }

    public function qualify(Lead $lead)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($lead) {
            $fresh = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($fresh->status, [Lead::STATUS_NEW, Lead::STATUS_CONTACTED], true), 422, 'Lead cannot be qualified from its current status.');
            $fresh->update(['status' => Lead::STATUS_QUALIFIED, 'verified_by' => auth()->id(), 'verified_at' => now()]);
        });

        return redirect()->route('leads.show', $lead)
            ->with('success', 'Lead qualified. You can now register the student.');
    }

    public function reject(Request $request, Lead $lead)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($lead, $request) {
            $fresh = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($fresh->status, [Lead::STATUS_CONVERTED, Lead::STATUS_REJECTED], true), 422, 'Lead is already closed.');
            $fresh->update(['status' => Lead::STATUS_REJECTED, 'rejected_reason' => $request->input('reason'),
                'verified_by' => auth()->id(), 'verified_at' => now()]);
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Lead rejected.');
    }

    public function convert(Lead $lead)
    {
        abort_unless(\App\Services\StudentRegistrationService::canConvert($lead), 422, 'Qualify the lead before conversion. Converted or rejected leads cannot be registered again.');
        $prefill = array_merge($lead->raw_payload ?? [], $lead->submitted_payload ?? [],
            $lead->only(['name', 'father_name', 'cnic', 'phone', 'email']));

        return view('admin.people.form', ['kind' => 'students', 'person' => null, 'lead' => $lead,
            'prefill' => $prefill, 'duplicates' => $this->duplicatesFor($lead),
            'formAction' => route('leads.convert.store', $lead),
            'batches' => \App\Services\StudentRegistrationService::availableBatches()]);
    }

    public function storeConvert(Request $request, Lead $lead)
    {
        abort_unless(\App\Services\StudentRegistrationService::canConvert($lead), 422, 'Qualify the lead before conversion. Converted or rejected leads cannot be registered again.');
        if (is_array($request->input('schedule'))) {
            $request->merge(['schedule' => array_values(array_filter($request->input('schedule'), fn ($row) => !is_array($row) || filled($row['amount'] ?? null) || filled($row['due_date'] ?? null)))]);
        }
        $data = $request->validate(\App\Services\StudentRegistrationService::rules($request->filled('student_id'), $request->input('student_id')));
        $student = app(\App\Services\StudentRegistrationService::class)->register($data, $lead);

        return redirect()->route('students.show', $student)->with('success', $request->filled('batch_id')
            ? 'Lead converted: student registered and enrolled. Fee plan created.' : 'Lead converted: student registered. Admission can be added later.');
    }

    // ---- Helpers --------------------------------------------------------------

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
