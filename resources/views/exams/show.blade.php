@extends('layouts.admin')
@section('title', $exam->name)
@section('content')
<div class="panel-heading d-flex justify-content-between align-items-center">
    <h1>{{ $exam->name }}</h1>
    @if(auth()->user()->isAdmin())
        <a class="btn btn-outline-secondary" href="{{ route('exams.edit', $exam) }}">Edit exam</a>
    @endif
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<section class="panel" style="padding:14px 18px">
    <div>{{ $exam->batch->name }} &middot; Level {{ $exam->batch->course->level }} &middot; {{ $exam->exam_date->format('d M Y') }} &middot; {{ ucfirst($exam->type) }}</div>
</section>

<section class="panel mt-3">
    <div class="panel-heading"><h2 class="h5">Results</h2></div>
    <div class="table-responsive"><table class="table list-table align-middle">
        <thead><tr>
            <th scope="col">Student</th>
            @foreach($exam->sections as $section)
                <th scope="col">{{ $section->name }}<span class="cell-sub">pass {{ rtrim(rtrim($section->passing_marks, '0'), '.') }}/{{ rtrim(rtrim($section->max_marks, '0'), '.') }}</span></th>
            @endforeach
            <th scope="col">Overall</th>
            <th scope="col" class="d-print-none">&nbsp;</th>
        </tr></thead>
        <tbody>
        @forelse($students as $student)
            @php $result = $resultsByStudent->get($student->id); $marks = $result?->sectionMarks->keyBy('exam_section_id'); @endphp
            <tr>
                <td class="cell-main">{{ $student->name }}</td>
                @foreach($exam->sections as $section)
                    @php $m = $marks?->get($section->id); @endphp
                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="{{ $section->max_marks }}"
                               form="res-{{ $student->id }}" name="marks[{{ $section->id }}]" value="{{ $m?->marks_obtained }}"
                               aria-label="{{ $student->name }} – {{ $section->name }} marks" style="width:5.5rem"
                               @class(['is-invalid' => $m && ! $m->passed])></td>
                @endforeach
                <td>
                    @if($result)
                        <span class="badge {{ $result->overall_passed ? 'bg-success' : 'bg-danger' }}">{{ $result->overall_passed ? 'Passed' : 'Failed' }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="d-print-none text-nowrap">
                    <button class="btn btn-primary btn-sm" type="submit" form="res-{{ $student->id }}">Save</button>
                    @if($result && $result->overall_passed && auth()->user()->isAdmin())
                        @if($result->activeCertificate)
                            <a class="btn btn-outline-success btn-sm" href="{{ route('certificates.print', $result->activeCertificate) }}" target="_blank" rel="noopener">Cert {{ $result->activeCertificate->certificate_number }}</a>
                        @else
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('certificates.index', ['result' => $result->id]) }}">Issue certificate</a>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $exam->sections->count() + 3 }}" class="empty-state">No students enrolled in this batch.</td></tr>
        @endforelse
        </tbody>
    </table></div>

    {{-- Row forms live OUTSIDE the table; inputs/buttons reference them via form="res-{id}" (valid HTML5). --}}
    @foreach($students as $student)
        <form id="res-{{ $student->id }}" method="post" action="{{ route('exams.results.store', $exam) }}" class="d-none">
            @csrf
            <input type="hidden" name="student_id" value="{{ $student->id }}">
        </form>
    @endforeach
</section>
@endsection
