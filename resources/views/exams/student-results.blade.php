@extends('layouts.admin')
@section('title', 'My Result')
@section('content')
<x-list.header title="My Result" eyebrow="Academics" subtitle="Track your scheduled exams and view recorded marks. Scheduled exams have no result until marks are recorded." />
<x-list.kpis :items="$kpis" />
<div class="panel mb-3">
    <x-list.toolbar :filters="$filters" search="Search exam name…" :per-page="false" />
</div>

@if($scheduledExams->isNotEmpty())
    <section class="panel mt-3">
        <div class="panel-heading"><h2>Scheduled exams</h2></div>
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead><tr><th scope="col">Exam</th><th scope="col">Batch</th><th scope="col">Date</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    @foreach($scheduledExams as $scheduledExam)
                        <tr>
                            <td>{{ $scheduledExam->name }}</td>
                            <td>{{ $scheduledExam->batch->name }} <span class="cell-sub">{{ $scheduledExam->batch->course->name }} ({{ $scheduledExam->batch->course->level }})</span></td>
                            <td>{{ $scheduledExam->exam_date->format('d M Y') }}</td>
                            <td><span class="badge bg-secondary">Scheduled</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if($results->isNotEmpty())
    <div class="panel-heading mt-3"><h2>Results</h2></div>
@endif
@foreach($results as $result)
    <section class="panel mt-3" style="padding:14px 18px">
        <div class="d-flex justify-content-between align-items-center">
            <div><strong>{{ $result->batchExam->name }}</strong><span class="cell-sub">{{ $result->batchExam->batch->name }} &middot; Level {{ $result->batchExam->batch->course->level }} &middot; {{ $result->batchExam->exam_date->format('d M Y') }}</span></div>
            <span class="badge {{ $result->overall_passed ? 'bg-success' : 'bg-danger' }}">{{ $result->overall_passed ? 'Passed' : 'Failed' }}</span>
        </div>
        <div class="table-responsive mt-2"><table class="table mb-0">
            <thead><tr><th scope="col">Section</th><th scope="col">Marks</th><th scope="col">Result</th></tr></thead>
            <tbody>
            @foreach($result->sectionMarks->sortBy('section.sort') as $mark)
                <tr>
                    <td>{{ $mark->section->name }}</td>
                    <td>{{ rtrim(rtrim($mark->marks_obtained, '0'), '.') }} / {{ rtrim(rtrim($mark->section->max_marks, '0'), '.') }}</td>
                    <td>{!! $mark->passed ? '<span class="badge bg-success">Pass</span>' : '<span class="badge bg-danger">Fail</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @if($result->activeCertificate)
            <a class="btn btn-outline-success btn-sm mt-2" href="{{ route('certificates.print', $result->activeCertificate) }}" target="_blank" rel="noopener">View certificate</a>
        @endif
    </section>
@endforeach
@if($scheduledExams->isEmpty() && $results->isEmpty())
    <section class="panel mt-3">
        <div class="empty-state">
            <i class="fas fa-file-circle-check mb-2" aria-hidden="true"></i>
            @if(request()->filled('search') || request()->filled('batch_id') || request()->filled('status'))
                <h2 class="h6">No matching exams or results</h2>
                <p>Try another exam name, batch or status.</p>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('student.results') }}">Clear filters</a>
            @else
                <h2 class="h6">No exams scheduled yet</h2>
                <p>Your exams will appear here when scheduled. Results will appear after your marks are recorded.</p>
            @endif
        </div>
    </section>
@endif
@endsection
