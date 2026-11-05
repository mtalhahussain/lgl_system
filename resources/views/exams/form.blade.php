@extends('layouts.admin')
@section('title', $exam ? 'Edit exam' : 'New exam')
@section('content')
@php
    $action = $exam ? route('exams.update', $exam) : route('exams.store');
    $sections = $exam ? $exam->sections : collect($sectionNames)->map(fn ($n) => (object) ['name' => $n, 'max_marks' => 25, 'passing_marks' => 15]);
@endphp
<div class="panel-heading"><h1>{{ $exam ? 'Edit exam' : 'New exam' }}</h1></div>

<section class="panel" style="padding:18px 22px;max-width:760px">
    <form method="post" action="{{ $action }}">
        @csrf
        @if($exam)@method('PUT')@endif

        <div class="mb-3">
            <label class="form-label" for="batch_id">Batch</label>
            <select class="form-select" id="batch_id" name="batch_id" required>
                <option value="">— Select a batch —</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected((int) old('batch_id', $exam->batch_id ?? 0) === $b->id)>{{ $b->name }} ({{ $b->course->level }})</option>
                @endforeach
            </select>
            @error('batch_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="name">Exam name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $exam->name ?? '') }}" required maxlength="255">
                @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="type">Type</label>
                <select class="form-select" id="type" name="type" required>
                    @foreach(['final', 'mid', 'retake', 'other'] as $t)
                        <option value="{{ $t }}" @selected(old('type', $exam->type ?? 'final') === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3 mb-3">
                <label class="form-label" for="exam_date">Date</label>
                <input class="form-control" id="exam_date" name="exam_date" type="date"
                       value="{{ old('exam_date', isset($exam) ? $exam->exam_date->toDateString() : now()->toDateString()) }}" required>
                @error('exam_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>

        <h2 class="h6 mt-2">Sections</h2>
        @error('sections')<div class="text-danger small">{{ $message }}</div>@enderror
        <div class="table-responsive"><table class="table">
            <thead><tr><th scope="col">Section</th><th scope="col">Max marks</th><th scope="col">Passing marks</th></tr></thead>
            <tbody>
            @foreach($sections as $i => $s)
                <tr>
                    <td>
                        {{ $s->name }}
                        <input type="hidden" name="sections[{{ $i }}][name]" value="{{ $s->name }}">
                    </td>
                    <td><input class="form-control" type="number" step="0.01" min="1"
                               name="sections[{{ $i }}][max_marks]" value="{{ old("sections.$i.max_marks", $s->max_marks) }}" required></td>
                    <td><input class="form-control" type="number" step="0.01" min="0"
                               name="sections[{{ $i }}][passing_marks]" value="{{ old("sections.$i.passing_marks", $s->passing_marks) }}" required></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a class="btn btn-outline-secondary" href="{{ route('exams.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">{{ $exam ? 'Save exam' : 'Create exam' }}</button>
        </div>
    </form>
</section>
@endsection
