@extends('layouts.admin')
@section('title', $batch ? 'Edit Batch' : 'Add Batch')
@section('content')
<x-list.header :title="$batch ? 'Edit Batch' : 'Add Batch'" eyebrow="Academics" :subtitle="$batch ? $batch->name : 'Course, teacher, dates and class delivery'" />
<form method="post" class="panel form-panel" action="{{ $batch ? route('batches.update', $batch) : route('batches.store') }}">
    @csrf
    @if($batch) @method('PUT') @endif
    <div class="form-section">
        <h2 class="form-section-title">Batch</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $batch?->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" aria-describedby="status-help">
                    <option value="auto" @selected(old('status', $batch?->status) !== 'cancelled')>Automatic from dates</option>
                    <option value="cancelled" @selected(old('status', $batch?->status) === 'cancelled')>Cancelled</option>
                </select>
                <div class="form-text" id="status-help">Upcoming, ongoing and completed follow the start and end dates.@if($batch && $batch->status !== 'cancelled') Current: {{ ucfirst($batch->status) }}.@endif</div>
            </div>
            @foreach(['course_id' => ['Course', $courses], 'teacher_id' => ['Teacher', $teachers]] as $field => [$label, $options])
                <div class="col-md-6">
                    <label class="form-label required" for="{{ $field }}">{{ $label }}</label>
                    <select class="form-select" id="{{ $field }}" name="{{ $field }}" required>
                        @foreach($options as $option)
                            <option value="{{ $option->id }}" @selected(old($field, $batch?->$field ?? ($field === 'course_id' ? request('course_id') : null)) == $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Dates and capacity</h2>
        <div class="row g-3">
            @foreach(['start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label {{ $field === 'start_date' ? 'required' : '' }}" for="{{ $field }}">{{ $label }}</label>
                    <input class="form-control" type="date" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $batch?->$field?->format('Y-m-d')) }}" @required($field === 'start_date')>
                </div>
            @endforeach
            <div class="col-md-6">
                <label class="form-label required" for="max_students">Capacity</label>
                <input class="form-control" id="max_students" name="max_students" type="number" min="1" max="500" value="{{ old('max_students', $batch?->max_students ?? 20) }}" required>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Class delivery</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="meeting_platform">Delivery</label>
                <select class="form-select" id="meeting_platform" name="meeting_platform">
                    @foreach(['in_person' => 'In person', 'zoom' => 'Zoom', 'google_meet' => 'Google Meet'] as $platform => $label)
                        <option value="{{ $platform }}" @selected(old('meeting_platform', $batch?->meeting_platform ?? 'in_person') === $platform)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="meeting_link">Meeting link (optional)</label>
                <input class="form-control" type="url" id="meeting_link" name="meeting_link" placeholder="https://" value="{{ old('meeting_link', $batch?->meeting_link) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $batch?->notes) }}</textarea>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h2 class="form-section-title">Class schedule <span class="text-muted fw-normal">(optional)</span></h2>
        <div class="form-text mb-2">Weekly class days and times. Used to show planned hours and to generate sessions. Changing it later does not alter sessions already generated. Leave empty if the batch has no fixed weekly timing.</div>
        @php
            $weekdays = \App\Models\BatchSchedule::WEEKDAYS;
            $scheduleRows = old('schedule', ($batch?->schedules ?? collect())->map(fn ($s) => [
                'weekday' => $s->weekday,
                'start' => \Illuminate\Support\Carbon::parse($s->getRawOriginal('start_time'))->format('H:i'),
                'end' => \Illuminate\Support\Carbon::parse($s->getRawOriginal('end_time'))->format('H:i'),
            ])->values()->all());
        @endphp
        @error('schedule')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        <div id="schedule-rows">
            @foreach($scheduleRows as $i => $row)
                <div class="row g-2 schedule-row align-items-end mb-2">
                    <div class="col-5 col-sm-4">
                        <label class="form-label">Day</label>
                        <select class="form-select" name="schedule[{{ $i }}][weekday]" data-native>
                            @foreach($weekdays as $value => $label)
                                <option value="{{ $value }}" @selected(($row['weekday'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3 col-sm-3">
                        <label class="form-label">Start</label>
                        <input class="form-control" type="time" name="schedule[{{ $i }}][start]" value="{{ $row['start'] ?? '' }}">
                    </div>
                    <div class="col-3 col-sm-3">
                        <label class="form-label">End</label>
                        <input class="form-control" type="time" name="schedule[{{ $i }}][end]" value="{{ $row['end'] ?? '' }}">
                    </div>
                    <div class="col-1 col-sm-2">
                        <button type="button" class="btn btn-outline-danger schedule-remove" aria-label="Remove class day">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <template id="schedule-template">
            <div class="row g-2 schedule-row align-items-end mb-2">
                <div class="col-5 col-sm-4">
                    <label class="form-label">Day</label>
                    <select class="form-select" name="schedule[__i__][weekday]" data-native>
                        @foreach($weekdays as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-3 col-sm-3">
                    <label class="form-label">Start</label>
                    <input class="form-control" type="time" name="schedule[__i__][start]">
                </div>
                <div class="col-3 col-sm-3">
                    <label class="form-label">End</label>
                    <input class="form-control" type="time" name="schedule[__i__][end]">
                </div>
                <div class="col-1 col-sm-2">
                    <button type="button" class="btn btn-outline-danger schedule-remove" aria-label="Remove class day">&times;</button>
                </div>
            </div>
        </template>
        <button type="button" class="btn btn-outline-primary btn-sm" id="schedule-add"><i class="fas fa-plus me-1"></i>Add class day</button>
    </div>
    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="{{ route('batches.index') }}">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>
<script>
(function () {
    var rows = document.getElementById('schedule-rows');
    var tpl = document.getElementById('schedule-template');
    var add = document.getElementById('schedule-add');
    if (!rows || !tpl || !add) return;
    var next = rows.querySelectorAll('.schedule-row').length;
    add.addEventListener('click', function () {
        var html = tpl.innerHTML.replace(/__i__/g, String(next++));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        rows.appendChild(wrap.firstChild);
    });
    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('.schedule-remove');
        if (btn) { btn.closest('.schedule-row').remove(); }
    });
})();
</script>
@endsection
