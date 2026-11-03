@extends('layouts.admin')
@section('title', 'Staff Attendance')
@section('content')
<x-list.header title="Staff Attendance" eyebrow="Attendance" subtitle="First and last machine punch per teacher or staff member per day. For records only; payroll is not affected." />
<form method="get" class="panel row g-2 align-items-end mb-4" style="padding:18px 22px">
    <div class="col-sm-4"><label class="form-label" for="month">Month</label><input class="form-control" type="month" id="month" name="month" value="{{ $month }}" max="{{ now()->format('Y-m') }}"></div>
    <div class="col-sm-5"><label class="form-label" for="teacher_id">Person</label>
        <select class="form-select" id="teacher_id" name="teacher_id">
            <option value="">Everyone</option>
            @foreach($teachers as $id => $name)<option value="{{ $id }}" @selected($teacherId === $id)>{{ $name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-3"><button class="btn btn-primary w-100">Show</button></div>
</form>
<div class="panel list-panel">
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Date</th><th scope="col">Person</th><th scope="col">First punch</th><th scope="col">Last punch</th><th scope="col" class="num">Punches</th></tr></thead>
            <tbody>
            @forelse($days as $day)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($day->day)->format('D, d M Y') }}</td>
                    <td><span class="cell-main">{{ $teachers[$day->user_id] ?? 'Person #'.$day->user_id }}</span><span class="cell-sub">{{ $roles[$day->user_id] ?? '' }}</span></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($day->first_in)->format('H:i') }}</td>
                    <td>{{ $day->punches > 1 ? \Illuminate\Support\Carbon::parse($day->last_out)->format('H:i') : '—' }}</td>
                    <td class="num">{{ $day->punches }}</td>
                </tr>
            @empty
                <x-list.empty :colspan="5" message="No teacher or staff punches in this month." />
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
