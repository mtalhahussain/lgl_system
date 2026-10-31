@extends('layouts.admin')
@section('title', 'Outstanding Dues')
@section('content')
<h1>Outstanding Dues</h1>
@include('admin.reports.filters')
<div class="table-responsive"><table class="table"><thead><tr><th>Student</th><th>Batch</th><th>Due date</th><th>Outstanding</th></tr></thead><tbody>
    @foreach($rows as $row)<tr><td>{{ $row->enrollment->student->name }}</td><td>{{ $row->enrollment->batch->name }}</td><td>{{ $row->due_date->format('d M Y') }}</td><td>{{ currency_format($row->outstanding_amount) }}</td></tr>@endforeach
</tbody></table></div>
{{ $rows->links() }}
@endsection
