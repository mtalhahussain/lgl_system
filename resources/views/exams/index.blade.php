@extends('layouts.admin')
@section('title', 'Exams')
@section('content')
<div class="panel-heading d-flex justify-content-between align-items-center">
    <h1>Exams</h1>
    @if(auth()->user()->isAdmin())
        <a class="btn btn-primary" href="{{ route('exams.create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>New exam</a>
    @endif
</div>

<section class="panel">
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Exam</th><th scope="col">Batch</th><th scope="col">Level</th><th scope="col">Date</th><th scope="col">Type</th><th scope="col" class="d-print-none">&nbsp;</th></tr></thead>
        <tbody>
        @forelse($exams as $exam)
            <tr>
                <td class="cell-main">{{ $exam->name }}</td>
                <td>{{ $exam->batch->name }}</td>
                <td>{{ $exam->batch->course->level }}</td>
                <td>{{ $exam->exam_date->format('d M Y') }}</td>
                <td>{{ ucfirst($exam->type) }}</td>
                <td class="d-print-none"><a class="btn btn-outline-secondary btn-sm" href="{{ route('exams.show', $exam) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No exams yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
