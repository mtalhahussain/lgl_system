@extends('layouts.admin')
@section('title', 'Attendance')
@section('content')
<x-list.header title="Attendance" :eyebrow="$tab === 'team' ? 'Teachers & Staff' : ucfirst($tab)" subtitle="Daily machine punches for the selected dates. No punch does not automatically mean absent; payroll is not affected." />
@include('partials.attendance-tabs')
<x-list.kpis :items="$kpis" />
<form method="get" class="panel row g-2 align-items-end mb-4" style="padding:18px 22px">
    @if($tab !== 'team')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <div class="col-sm-3"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="{{ $from }}" required></div>
    <div class="col-sm-3"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="{{ $to }}" min="{{ $from }}" required></div>
    <div class="col-sm-4"><label class="form-label" for="teacher_id">{{ $tab === 'teachers' ? 'Teacher' : ($tab === 'staff' ? 'Staff member' : 'Teacher / Staff member') }}</label>
        <select class="form-select" id="teacher_id" name="teacher_id">
            <option value="">All</option>
            @foreach($teachers as $id => $name)<option value="{{ $id }}" data-person-name="{{ $name }}" data-person-role="{{ $roleLabels[$id] }}" @selected($teacherId === $id)>{{ $name }} ({{ $roleLabels[$id] }})</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2"><button class="btn btn-primary w-100">Show</button></div>
</form>
<div class="panel list-panel">
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Date</th><th scope="col">Person</th><th scope="col" title="First recorded machine punch">Check-in</th><th scope="col" title="Last recorded machine punch">Check-out</th><th scope="col" class="num">Punches</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Details</span></th></tr></thead>
            <tbody>
            @forelse($days as $day)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($day->day)->format('D, d M Y') }}</td>
                    <td><span class="cell-main">{{ $teachers[$day->user_id] ?? 'Person #'.$day->user_id }}</span><span class="cell-sub">{{ $roles[$day->user_id] ?? '' }}</span></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($day->first_in)->format('h:i:s A') }}</td>
                    <td>{{ $day->punches > 1 ? \Illuminate\Support\Carbon::parse($day->last_out)->format('h:i:s A') : '—' }}</td>
                    <td class="num">{{ $day->punches }}</td>
                    <td><x-list.status value="active" :label="$day->punches > 1 ? 'Punches recorded' : 'Single punch'" /></td>
                    <td class="text-end"><button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#punches_{{ $day->user_id }}_{{ $loop->index }}" aria-expanded="false" aria-controls="punches_{{ $day->user_id }}_{{ $loop->index }}">Details</button></td>
                </tr>
                <tr class="detail-row"><td colspan="7" class="p-0"><div class="collapse" id="punches_{{ $day->user_id }}_{{ $loop->index }}"><div class="detail-body">
                    <h2 class="h6">Recorded punches · {{ $teachers[$day->user_id] }}</h2>
                    <ul class="list-unstyled mb-0">
                        @foreach($punchDetails->get($day->user_id.'|'.$day->day, collect()) as $punch)
                            <li>{{ $punch->punched_at->format('h:i:s A') }} <span class="text-muted">{{ $punch->device?->name ?? 'Attendance machine' }}</span></li>
                        @endforeach
                    </ul>
                </div></div></td></tr>
            @empty
                <x-list.empty :colspan="7" message="No machine punches in the selected dates. This is not an absence status." />
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
