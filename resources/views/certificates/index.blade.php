@extends('layouts.admin')
@section('title', 'Certificates')
@section('content')
<div class="panel-heading"><h1>Issue Certificate</h1></div>

<section class="panel" style="padding:18px 22px;max-width:720px">
    <form method="post" action="{{ route('certificates.store') }}">
        @csrf
        @if(! empty($preselectResultId))<input type="hidden" name="student_exam_result_id" value="{{ $preselectResultId }}">@endif
        <div class="mb-3">
            <label class="form-label" for="student_id">Student</label>
            <select class="form-select" id="student_id" name="student_id">
                <option value="">— Select a student —</option>
                @foreach($students as $s)
                    <option value="{{ $s->id }}" @selected((int) old('student_id', $preselectId) === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Selecting a student fills the name and level below; you can edit them.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="student_name">Name on certificate</label>
            <input class="form-control" id="student_name" name="student_name"
                   value="{{ old('student_name', $preselect['name'] ?? '') }}" required maxlength="255">
            @error('student_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="father_name">Father&rsquo;s name (S/O)</label>
            <input class="form-control" id="father_name" name="father_name"
                   value="{{ old('father_name') }}" maxlength="255">
        </div>
        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="level">Level</label>
                <select class="form-select" id="level" name="level" required>
                    @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $lvl)
                        <option value="{{ $lvl }}" @selected(old('level', $preselect['level'] ?? 'A1') === $lvl)>{{ $lvl }}</option>
                    @endforeach
                </select>
                @error('level')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="completion_date">Completion date</label>
                <input class="form-control" id="completion_date" name="completion_date" type="date"
                       value="{{ old('completion_date', now()->toDateString()) }}" required>
                @error('completion_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-award me-1" aria-hidden="true"></i>Issue &amp; print</button>
    </form>
</section>

<section class="panel mt-4" aria-labelledby="issued-heading">
    <div class="panel-heading"><h2 id="issued-heading">Issued certificates</h2><span class="page-subtitle">{{ $certificates->count() }} shown</span></div>
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Number</th><th scope="col">Student</th><th scope="col">Level</th><th scope="col">Completed</th><th scope="col">Issued</th><th scope="col" class="d-print-none">&nbsp;</th></tr></thead>
        <tbody>
        @forelse($certificates as $certificate)
            <tr>
                <td class="cell-main">{{ $certificate->certificate_number }}</td>
                <td>{{ $certificate->student_name }}@if($certificate->father_name)<span class="cell-sub">S/O {{ $certificate->father_name }}</span>@endif</td>
                <td>{{ $certificate->level }}</td>
                <td>{{ $certificate->completion_date->format('d M Y') }}</td>
                <td>{{ $certificate->issued_at?->format('d M Y') }}</td>
                <td class="d-print-none"><a class="btn btn-outline-secondary btn-sm" href="{{ route('certificates.print', $certificate) }}" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Reprint</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No certificates issued yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<script>
    (function () {
        var meta = @json($studentMeta);
        var sel = document.getElementById('student_id');
        var name = document.getElementById('student_name');
        var level = document.getElementById('level');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var m = meta[sel.value];
            if (!m) return;
            if (name) name.value = m.name || name.value;
            if (level && m.level) level.value = m.level;
        });
    })();
</script>
@endsection
