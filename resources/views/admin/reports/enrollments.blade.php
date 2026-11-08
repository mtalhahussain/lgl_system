@extends('layouts.admin')
@section('title', 'Enrollment Report')
@section('content')
<x-list.header title="Enrollment Report" eyebrow="Reports" subtitle="Student, course and batch roster by admission date">
    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print</button>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search student…" :csv="true" :per-page-default="25" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="student" default="-date">Student</x-list.th><th scope="col">Course</th><th scope="col">Batch</th>
                <x-list.th sort="date" default="-date">Date</x-list.th><th scope="col">Status</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr><td class="cell-main">{{ $row->student->name }}</td><td>{{ $row->batch->course->name }}@if($row->batch->course->level) ({{ $row->batch->course->level }})@endif</td><td>{{ $row->batch->name }}</td>
                    <td>{{ $row->enrollment_date->format('d M Y') }}</td><td><x-list.status :value="$row->status" /></td></tr>
            @empty
                <x-list.empty colspan="5" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$rows" label="enrollments" />
</div>
@endsection
