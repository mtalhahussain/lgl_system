@extends('layouts.admin')
@section('title', $student->name)
@section('content')
<x-show.hero icon="fa-user-graduate" eyebrow="Student · your batches" :title="$student->name">
    <a class="btn btn-primary" href="{{ route('attendance.student', $student) }}"><i class="fas fa-clipboard-check me-1" aria-hidden="true"></i>Attendance</a>
</x-show.hero>
<section class="panel" aria-labelledby="enrollments-heading">
    <div class="panel-heading"><h2 id="enrollments-heading">Enrollments in your batches</h2><span class="page-subtitle">{{ $enrollments->count() }} total</span></div>
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Batch</th><th scope="col">Enrolled</th><th scope="col">Status</th></tr></thead>
        <tbody>
        @forelse($enrollments as $enrollment)
            <tr>
                <td><a class="cell-main" href="{{ route('batches.show', $enrollment->batch) }}">{{ $enrollment->batch->name }}</a></td>
                <td>{{ $enrollment->enrollment_date?->format('d M Y') }}</td>
                <td><x-list.status :value="$enrollment->status" :label="$enrollment->status === 'dropped' ? 'withdrawn' : null" /></td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty-state">No enrollments in your batches.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
