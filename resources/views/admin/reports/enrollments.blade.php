@extends('layouts.admin')
@section('title', 'Enrollment Report')
@section('content')
<h1>Enrollment Report</h1>
@include('admin.reports.filters')
<div class="table-responsive"><table class="table"><thead><tr><th>Student</th><th>Course</th><th>Batch</th><th>Date</th><th>Status</th></tr></thead><tbody>
    @foreach($rows as $row)<tr><td>{{ $row->student->name }}</td><td>{{ $row->batch->course->name }}</td><td>{{ $row->batch->name }}</td><td>{{ $row->enrollment_date->format('d M Y') }}</td><td>{{ $row->status }}</td></tr>@endforeach
</tbody></table></div>
{{ $rows->links() }}
@endsection
