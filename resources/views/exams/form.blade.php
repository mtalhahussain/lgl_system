@extends('layouts.admin')
@section('title', $exam ? 'Edit exam' : 'New exam')
@push('styles')
<style>
    .exam-form-grid { display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1.35fr); }
    .exam-form-grid > .form-section { border-bottom:0; }
    .exam-form-grid > .form-section + .form-section { border-left:1px solid var(--line); }
    .exam-section-table { width:100%; min-width:360px; margin:18px 0 0; }
    .exam-section-table th { font-size:12px; color:var(--muted); font-weight:600; }
    .exam-section-table td { vertical-align:middle; padding-top:16px; padding-bottom:16px; }
    .exam-section-table th:first-child { width:36%; }
    .exam-section-name { font-weight:600; color:var(--navy); }
    .exam-section-skill { display:block; font-size:12px; color:var(--muted); margin-top:3px; }
    .exam-pass-note { background:var(--accent-soft); border-radius:10px; padding:14px 16px; margin-top:16px; font-size:13px; color:var(--navy); }
    @media(max-width:991.98px) {
        .exam-form-grid { grid-template-columns:minmax(0, 1fr); }
        .exam-form-grid > .form-section + .form-section { border-left:0; border-top:1px solid var(--line); }
    }
</style>
@endpush
@section('content')
@php
    $action = $exam ? route('exams.update', $exam) : route('exams.store');
    $sections = $exam ? $exam->sections : collect($sectionNames)->map(fn ($n) => (object) ['name' => $n, 'max_marks' => 25, 'passing_marks' => 15]);
    $skills = ['Hören' => 'Listening', 'Lesen' => 'Reading', 'Schreiben' => 'Writing', 'Sprechen' => 'Speaking'];
@endphp
<a class="back-link" href="{{ route('exams.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to Exams</a>
<x-list.header :title="$exam ? 'Edit exam' : 'New exam'" eyebrow="Academics" subtitle="Choose a batch, set the exam date and define marks for each language skill." />

<form class="panel form-panel" method="post" action="{{ $action }}">
    @csrf
    @if($exam) @method('PUT') @endif
    <div class="exam-form-grid">
        <section class="form-section" aria-labelledby="exam-details-heading">
            <h2 class="form-section-title" id="exam-details-heading">Exam details</h2>
            <p class="form-section-note">All fields are required. Use a clear name to identify this exam or retake.</p>
            <div class="mb-3">
                <label class="form-label required" for="batch_id">Batch</label>
                <select class="form-select @error('batch_id') is-invalid @enderror" id="batch_id" name="batch_id" required>
                    <option value="">Select a batch</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}" @selected((int) old('batch_id', $exam->batch_id ?? 0) === $b->id)>{{ $b->name }} ({{ $b->course->level }})</option>
                    @endforeach
                </select>
                @error('batch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($batches->isEmpty())<p class="form-text mb-0">No batches available. <a href="{{ route('batches.create') }}">Create a batch</a> before scheduling an exam.</p>@endif
            </div>
            <div class="mb-3">
                <label class="form-label required" for="name">Exam name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $exam->name ?? '') }}" placeholder="e.g. A1 Final — November 2026" required maxlength="255">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label required" for="type">Type</label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                        @foreach(['final' => 'Final', 'mid' => 'Midterm', 'retake' => 'Retake', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $exam->type ?? 'final') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label required" for="exam_date">Date</label>
                    <input class="form-control @error('exam_date') is-invalid @enderror" id="exam_date" name="exam_date" type="date" value="{{ old('exam_date', $exam ? $exam->exam_date->toDateString() : now()->toDateString()) }}" required>
                    @error('exam_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>
        <section class="form-section" aria-labelledby="exam-sections-heading">
            <h2 class="form-section-title" id="exam-sections-heading">Section marks</h2>
            <p class="form-section-note" id="section-marks-help">Set maximum and passing marks for each skill. Passing marks cannot exceed maximum marks.</p>
            @error('sections')<div class="text-danger small" role="alert">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table exam-section-table" aria-describedby="section-marks-help">
                    <thead><tr><th scope="col">Language skill</th><th scope="col">Max marks</th><th scope="col">Passing marks</th></tr></thead>
                    <tbody>
                    @foreach($sections as $i => $s)
                        <tr>
                            <th scope="row">
                                <span class="exam-section-name">{{ $s->name }}</span>
                                <span class="exam-section-skill">{{ $skills[$s->name] ?? '' }}</span>
                                <input type="hidden" name="sections[{{ $i }}][name]" value="{{ $s->name }}">
                                @error("sections.$i.name")<div class="text-danger small">{{ $message }}</div>@enderror
                            </th>
                            <td>
                                <label class="visually-hidden" for="section-{{ $i }}-max">{{ $s->name }} maximum marks</label>
                                <input class="form-control @error("sections.$i.max_marks") is-invalid @enderror" id="section-{{ $i }}-max" type="number" step="0.01" min="1" max="9999.99" name="sections[{{ $i }}][max_marks]" value="{{ old("sections.$i.max_marks", $s->max_marks) }}" required>
                                @error("sections.$i.max_marks")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <label class="visually-hidden" for="section-{{ $i }}-pass">{{ $s->name }} passing marks</label>
                                <input class="form-control @error("sections.$i.passing_marks") is-invalid @enderror" id="section-{{ $i }}-pass" type="number" step="0.01" min="0" max="9999.99" name="sections[{{ $i }}][passing_marks]" value="{{ old("sections.$i.passing_marks", $s->passing_marks) }}" required>
                                @error("sections.$i.passing_marks")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="exam-pass-note"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>Students must pass all four sections to pass the exam.</div>
        </section>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('exams.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $exam ? 'Save exam' : 'Create exam' }}</button>
    </div>
</form>
@endsection
