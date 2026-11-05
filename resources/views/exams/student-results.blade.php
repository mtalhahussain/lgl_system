@extends('layouts.admin')
@section('title', 'My Results')
@section('content')
<div class="panel-heading"><h1>My Results</h1></div>

@forelse($results as $result)
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
@empty
    <section class="panel mt-3"><div class="empty-state">No exam results yet.</div></section>
@endforelse
@endsection
