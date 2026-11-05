@extends('layouts.admin')
@section('title', $person->name)
@section('content')
@php
    $viewer = auth()->user();
    $isAdmin = $viewer->isAdmin();
    $isTeacherProfile = $kind === 'teachers';
    $reference = $isTeacherProfile ? $person->employee_id : $person->student_id;
    $dues = $dues ?? collect();
    $initials = collect(preg_split('/\s+/', trim($person->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    if ($isTeacherProfile) {
        $openDues = $dues->whereNull('voided_at');
        $kpis = [['label' => 'Batches', 'value' => $batches->count(), 'icon' => 'fa-layer-group'],
            ['label' => 'Ongoing', 'value' => $batches->where('status', 'ongoing')->count(), 'icon' => 'fa-play'],
            ['label' => 'Active students', 'value' => $batches->whereIn('status', ['ongoing', 'upcoming'])->sum('active_enrollments_count'), 'icon' => 'fa-user-graduate', 'note' => 'Ongoing and upcoming batches']];
        if ($isAdmin) {
            $kpis[] = ['label' => 'Pay outstanding', 'value' => currency_format(\App\Support\Money::decimal($openDues->sum(fn ($due) => $due->outstandingMinor()))), 'icon' => 'fa-wallet'];
        }
    } else {
        $kpis = [['label' => 'Enrollments', 'value' => $enrollments->count(), 'icon' => 'fa-file-signature'],
            ['label' => 'Active', 'value' => $enrollments->where('status', 'active')->count(), 'icon' => 'fa-user-check'],
            ['label' => 'Completed', 'value' => $enrollments->where('status', 'completed')->count(), 'icon' => 'fa-award']];
    }
    $contact = array_filter([
        ['Email', 'fa-envelope', $person->email, 'mailto:'.$person->email],
        ['Phone', 'fa-phone', $person->phone, $person->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $person->phone) : null],
        ['Address', 'fa-location-dot', $person->address, null],
        ['Date of birth', 'fa-cake-candles', $person->date_of_birth?->format('d M Y'), null],
        ['Emergency contact', 'fa-life-ring', $person->emergency_contact, null],
    ], fn ($row) => filled($row[2]));
@endphp

<div class="panel profile-hero">
    <span class="profile-avatar" aria-hidden="true">{{ $initials }}</span>
    <div class="profile-identity">
        <div class="eyebrow">{{ $isTeacherProfile ? 'Teacher' : 'Student' }}@if($reference) · {{ $reference }}@endif</div>
        <h1>{{ $person->name }}</h1>
        <div class="profile-meta">
            <x-list.status :value="$person->is_active ? 'active' : 'inactive'" />
            @if($isTeacherProfile && $person->specialization)<span><i class="fas fa-book-open" aria-hidden="true"></i>{{ $person->specialization }}</span>@endif
            <span><i class="fas fa-calendar" aria-hidden="true"></i>{{ $isTeacherProfile && $person->hire_date ? 'Hired '.$person->hire_date->format('d M Y') : 'Joined '.$person->created_at?->format('d M Y') }}</span>
        </div>
    </div>
    <div class="profile-actions d-print-none">
        @if($isAdmin)<a class="btn btn-primary" href="{{ route($kind.'.edit', $person) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>@endif
        @if($isTeacherProfile && $isAdmin)
            <a class="btn btn-outline-primary" href="{{ route('payroll.index', ['teacher_id' => $person->id]) }}"><i class="fas fa-hand-holding-usd me-1" aria-hidden="true"></i>Payroll</a>
        @elseif(! $isTeacherProfile)
            @if($isAdmin || $viewer->id === $person->id)<a class="btn btn-outline-primary" href="{{ route('fees.student', $person) }}"><i class="fas fa-receipt me-1" aria-hidden="true"></i>Fees and receipts</a>@endif
            <a class="btn btn-outline-primary" href="{{ route('attendance.student', $person) }}"><i class="fas fa-clipboard-check me-1" aria-hidden="true"></i>Attendance</a>
            @if($isAdmin)<a class="btn btn-outline-primary" href="{{ route('certificates.index', ['student' => $person->id]) }}"><i class="fas fa-award me-1" aria-hidden="true"></i>Issue Certificate</a>@endif
        @endif
    </div>
</div>

<x-list.kpis :items="$kpis" />

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3">
    <div class="tw:flex tw:flex-col tw:gap-5">
        <section class="panel" aria-labelledby="contact-heading">
            <div class="panel-heading"><h2 id="contact-heading">Contact</h2></div>
            <ul class="profile-facts">
                @foreach($contact as [$label, $icon, $value, $href])
                    <li><i class="fas {{ $icon }}" aria-hidden="true"></i><div><small>{{ $label }}</small>
                        @if($href)<a href="{{ $href }}">{{ $value }}</a>@else<span>{{ $value }}</span>@endif</div></li>
                @endforeach
            </ul>
        </section>
        @if($isTeacherProfile)
            <section class="panel" aria-labelledby="teaching-heading">
                <div class="panel-heading"><h2 id="teaching-heading">Teaching and pay</h2></div>
                <div style="padding:6px 22px 14px">
                    <div class="finance-row"><span>Qualification</span><strong>{{ $person->qualification ?: '—' }}</strong></div>
                    <div class="finance-row"><span>Experience</span><strong>{{ $person->experience_years ? $person->experience_years.' '.\Illuminate\Support\Str::plural('year', $person->experience_years) : '—' }}</strong></div>
                    @if($isAdmin)
                        <div class="finance-row"><span>Pay type</span><strong>{{ $person->salary_type === 'per_student' ? 'Per student per month' : 'Fixed monthly salary' }}</strong></div>
                        @if($person->salary_type !== 'per_student')
                            <div class="finance-row"><span>Monthly salary</span><strong>{{ $person->monthly_salary ? currency_format($person->monthly_salary) : 'Not set' }}</strong></div>
                        @endif
                    @endif
                </div>
            </section>
        @endif
    </div>

    <div class="tw:xl:col-span-2 tw:flex tw:flex-col tw:gap-5">
        @if($isTeacherProfile)
            <section class="panel" aria-labelledby="batches-heading">
                <div class="panel-heading"><h2 id="batches-heading">Assigned batches</h2><span class="page-subtitle">{{ $batches->count() }} total</span></div>
                <div class="table-responsive"><table class="table list-table">
                    <thead><tr><th scope="col">Batch</th><th scope="col">Dates</th><th scope="col">Students</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @forelse($batches as $batch)
                        @php $fill = $batch->max_students ? min(100, round($batch->active_enrollments_count / $batch->max_students * 100)) : 0; @endphp
                        <tr>
                            <td><a class="cell-main" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a><span class="cell-sub">{{ $batch->course?->name }}</span></td>
                            <td class="text-nowrap">{{ $batch->start_date?->format('d M Y') }}<span class="cell-sub">{{ $batch->end_date ? 'to '.$batch->end_date->format('d M Y') : 'No end date' }}</span></td>
                            <td style="min-width:120px">{{ $batch->active_enrollments_count }} / {{ $batch->max_students }}
                                <div class="balance-bar" role="progressbar" aria-label="{{ $batch->name }} capacity used" aria-valuenow="{{ $fill }}" aria-valuemin="0" aria-valuemax="100" style="margin-top:6px"><span style="width:{{ $fill }}%"></span></div></td>
                            <td><x-list.status :value="$batch->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">No batches assigned yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
            @if($isAdmin)
                <section class="panel" aria-labelledby="pay-heading">
                    <div class="panel-heading"><h2 id="pay-heading">Recent pay</h2><a href="{{ route('payroll.index', ['teacher_id' => $person->id]) }}" style="font-size:11px;color:#7956df">View all <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
                    <div class="table-responsive"><table class="table list-table">
                        <thead><tr><th scope="col">For</th><th scope="col" class="num">Amount</th><th scope="col" class="num">Outstanding</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @forelse($dues->take(5) as $due)
                            <tr>
                                <td><a class="cell-main" href="{{ route('payroll.show', $due) }}">{{ $due->label() }}</a><span class="cell-sub">{{ $due->created_at?->format('d M Y') }}</span></td>
                                <td class="num">{{ currency_format($due->amount) }}</td>
                                <td class="num">{{ currency_format(\App\Support\Money::decimal($due->outstandingMinor())) }}</td>
                                <td><x-list.status :value="$due->state()" :label="$due->voided_at ? 'voided' : null" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No pay generated yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </section>
            @endif
        @else
            <section class="panel" aria-labelledby="enrollments-heading">
                <div class="panel-heading"><h2 id="enrollments-heading">Enrollments</h2><span class="page-subtitle">{{ $enrollments->count() }} total</span></div>
                <div class="table-responsive"><table class="table list-table">
                    <thead><tr><th scope="col">Batch</th><th scope="col">Enrolled</th><th scope="col" class="num">Discount</th><th scope="col" class="num">Net fee</th><th scope="col" class="num">Outstanding</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    @forelse($enrollments->sortByDesc('enrollment_date') as $row)
                        <tr>
                            <td>@if($isAdmin)<a class="cell-main" href="{{ route('enrollments.show', $row) }}">{{ $row->batch?->name }}</a>@else<span class="cell-main">{{ $row->batch?->name }}</span>@endif<span class="cell-sub">{{ $row->batch?->course?->name }}</span></td>
                            <td>{{ $row->enrollment_date?->format('d M Y') }}</td>
                            <td class="num"><strong>{{ $row->discount_percentage }}%</strong>@if(\App\Support\Money::minor($row->discount_amount) > 0)<span class="cell-sub">{{ currency_format($row->discount_amount) }}</span>@endif</td>
                            <td class="num">{{ currency_format(\App\Support\Money::decimal(max(0, \App\Support\Money::minor($row->total_fee) - \App\Support\Money::minor($row->discount_amount)))) }}<span class="cell-sub">of {{ currency_format($row->total_fee) }}</span></td>
                            <td class="num">{{ currency_format($row->remaining_fee) }}</td>
                            <td><x-list.status :value="$row->status" :label="$row->status === 'dropped' ? 'withdrawn' : null" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">No enrollments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        @endif
    </div>
</div>
@endsection
