@extends('layouts.admin')
@section('title', 'Admin Dashboard')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">OVERVIEW</div><h1>Admin Dashboard</h1><p class="page-subtitle">Welcome back, {{ explode(' ', auth()->user()->name)[0] }}. Here’s your institute at a glance.</p></div>
    <a class="btn btn-primary" href="{{ route('enrollments.create') }}"><i class="fas fa-plus me-2" aria-hidden="true"></i>New admission</a>
</div>
<div class="tw:grid tw:grid-cols-1 tw:gap-4 tw:sm:grid-cols-2 tw:xl:grid-cols-4 tw:mb-6">
    @foreach($stats as $label => $value)
        <div class="panel metric"><div><div class="metric-label">{{ $label }}</div><div class="metric-value">{{ number_format($value) }}</div><div class="metric-note">Currently active</div></div><span class="metric-icon" style="color:{{ ['var(--accent)','var(--navy)','var(--gold-ink)'][$loop->index % 3] }}"><i class="fas {{ ['fa-user-graduate','fa-chalkboard-teacher','fa-layer-group'][$loop->index % 3] }}" aria-hidden="true"></i></span></div>
    @endforeach
    <div class="panel metric"><div><div class="metric-label">This month collected</div><div class="metric-value" style="font-size:23px">{{ $finance ? currency_format($finance['This month net collected']) : 'Unavailable' }}</div><div class="metric-note">Net of period reversals</div></div><span class="metric-icon" style="color:#24b7a0"><i class="fas fa-wallet" aria-hidden="true"></i></span></div>
</div>
@if($reminders['starting']->isNotEmpty() || $reminders['ending']->isNotEmpty())
<section class="panel tw:mb-6" aria-labelledby="batch-reminders-heading" style="border-left:4px solid var(--gold-ink)">
    <div class="panel-heading"><h2 id="batch-reminders-heading"><i class="fas fa-bell me-2" style="color:var(--gold-ink)" aria-hidden="true"></i>Batch reminders</h2><span class="page-subtitle">Next 2 days · status updates automatically</span></div>
    <div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:md:grid-cols-2 tw:p-5">
        @foreach(['starting' => ['Starting soon', 'start_date', 'Starts'], 'ending' => ['Completing soon', 'end_date', 'Ends']] as $key => [$title, $column, $verb])
            <div>
                <h3 style="font-size:12px;font-weight:700;margin-bottom:10px">{{ $title }} ({{ $reminders[$key]->count() }})</h3>
                @forelse($reminders[$key] as $batch)
                    @php $days = (int) today()->diffInDays($batch->$column, false); @endphp
                    <a href="{{ route('batches.show', $batch) }}" class="finance-row" style="color:inherit">
                        <span><strong>{{ $batch->name }}</strong><small style="display:block;color:#8290a5;font-size:11px">{{ $batch->course?->name }} · {{ $batch->teacher?->name }}</small></span>
                        <span class="status-pill">{{ $verb }} {{ $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days") }} · {{ $batch->$column->toDateString() }}</span>
                    </a>
                @empty<p class="page-subtitle">None.</p>@endforelse
            </div>
        @endforeach
    </div>
</section>
@endif
<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3 tw:mb-6">
    <section class="panel tw:xl:col-span-2">
        <div class="panel-heading"><h2>Recent admissions</h2><a href="{{ route('enrollments.index') }}" style="font-size:11px;color:var(--accent)">View all <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>STUDENT</th><th>BATCH</th><th>STATUS</th><th></th></tr></thead><tbody>
            @forelse($recent as $row)
                <tr><td><div class="person-cell"><span class="user-avatar">{{ mb_substr($row->student->name, 0, 1) }}</span><div><strong>{{ $row->student->name }}</strong><small>Enrollment #{{ $row->id }}</small></div></div></td><td>{{ $row->batch->name }}</td><td><span class="status-pill">{{ $row->status }}</span></td><td><a href="{{ route('enrollments.show', $row) }}" aria-label="View enrollment for {{ $row->student->name }}" style="color:#8b98ac"><i class="fas fa-arrow-right" aria-hidden="true"></i></a></td></tr>
            @empty<tr><td colspan="4" class="empty-state">No admissions yet. Create your first admission to get started.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Fee overview</h2><span class="status-pill">Ledger</span></div>
        <div class="fee-overview-body">
            <p class="page-subtitle fee-overview-subtitle">All-time collection and scheduled debt</p>
            @if($finance)
                @php
                    $collected = \App\Support\Money::minor($finance['Net collected']);
                    $outstanding = \App\Support\Money::minor($finance['Outstanding']);
                    $ratio = ($collected + $outstanding) > 0 ? round($collected / ($collected + $outstanding) * 100) : 0;
                @endphp
                <div class="fee-overview-ratio">{{ $ratio }}<span>%</span></div>
                <progress class="fee-overview-progress" aria-label="Scheduled fees collected" value="{{ $ratio }}" max="100">{{ $ratio }}%</progress>
                @foreach($finance as $label => $amount)<div class="finance-row"><span>{{ $label }}</span><strong>{{ currency_format($amount) }}</strong></div>@endforeach
            @else<p class="empty-state">Fee collection is temporarily unavailable.</p>@endif
            <a class="btn btn-outline-primary w-100 mt-3" href="{{ route('fees.index') }}">Manage collections <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></a>
        </div>
    </section>
</div>
<section class="panel"><div class="panel-heading"><h2>Your workspace</h2><span class="page-subtitle">Everyday essentials</span></div><div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-3 tw:gap-4 tw:p-5">
    @foreach([['students.index','fa-users','People','Manage student records'],['attendance.index','fa-clipboard-check','Attendance','Review class attendance'],['reports.index','fa-chart-bar','Reports','Explore institute activity']] as [$destination, $icon, $label, $description])
        <a href="{{ route($destination) }}" class="tw:flex tw:items-center tw:gap-4" style="color:inherit"><span class="metric-icon" style="border:0;border-radius:10px"><i class="fas {{ $icon }}" aria-hidden="true"></i></span><span><strong style="font-size:12px">{{ $label }}</strong><small style="display:block;color:#8290a5;font-size:11px;margin-top:3px">{{ $description }}</small></span></a>
    @endforeach
</div></section>
@endsection
