@extends('layouts.admin')
@section('title', auth()->user()->isStudent() ? 'My Attendance' : 'Attendance Report')
@section('content')
<x-list.header :title="auth()->user()->isStudent() ? 'My Attendance' : ($student ? $student->name.' — Attendance' : 'Attendance Report')" eyebrow="Attendance"
    subtitle="Rate = (present + late) ÷ (present + late + absent). Excused records and classes that did not run are not counted.">
    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print</button>
</x-list.header>
<x-list.kpis :items="[
    ['label' => 'Attendance rate', 'value' => $stats['attendance_rate'] === null ? 'No marked classes' : $stats['attendance_rate'].'%', 'icon' => 'fa-percent'],
    ['label' => 'Present / late', 'value' => $stats['present'].' / '.$stats['late'], 'icon' => 'fa-check'],
    ['label' => 'Absent', 'value' => $stats['absent'], 'icon' => 'fa-xmark'],
    ['label' => 'Excused', 'value' => $stats['excused'], 'icon' => 'fa-file-medical'],
]" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" :search="$student ? null : 'Search student…'" :csv="true" :per-page-default="25" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th :sort="$student ? null : 'student'" default="-date">Student</x-list.th>
                <th scope="col">Batch</th>
                <x-list.th sort="date" default="-date">Date</x-list.th>
                <th scope="col">Status</th>
            </tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr><td class="cell-main">{{ $record->student->name }}</td><td>{{ $record->classSession->batch->name }}</td>
                    <td>{{ $record->classSession->session_date->format('d M Y') }}</td><td><x-list.status :value="$record->status" /></td></tr>
            @empty
                <x-list.empty colspan="4" message="No attendance records." />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$records" label="records" />
</div>
@endsection
