@extends('layouts.admin')
@section('title', isset($course) ? $course->name : 'Assigned Batches')
@section('content')
<x-list.header :title="isset($course) ? $course->name : 'Assigned Batches'" eyebrow="My classes" :subtitle="isset($course) ? 'Your batches in this course' : 'Batches you teach'" />
<div class="panel list-panel">
    @isset($filters)<x-list.toolbar :filters="$filters" search="Search batch name…" />@endisset
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th :sort="isset($filters) ? 'name' : null" default="-start">Batch</x-list.th>
                <x-list.th :sort="isset($filters) ? 'start' : null" default="-start">Duration</x-list.th>
                <th scope="col">Weekly timing</th>
                <th scope="col">Students</th>
                <th scope="col">Status</th>
            </tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td><a class="cell-main" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>@if($batch->relationLoaded('course') && $batch->course)<span class="cell-sub">{{ $batch->course->name }}</span>@endif</td>
                    <td>{{ $batch->start_date->format('d M Y') }}<span class="cell-sub">to {{ $batch->end_date?->format('d M Y') ?? 'No end date' }}</span></td>
                    <td>@forelse($batch->orderedSchedules() as $slot)<span class="d-block">{{ $slot->weekdayLabel() }} {{ \Illuminate\Support\Carbon::parse($slot->getRawOriginal('start_time'))->format('h:i A') }}–{{ \Illuminate\Support\Carbon::parse($slot->getRawOriginal('end_time'))->format('h:i A') }}</span>@empty<span class="cell-sub">No weekly schedule set.</span>@endforelse</td>
                    <td>{{ $batch->active_enrollments_count }} / {{ $batch->max_students }}</td>
                    <td><x-list.status :value="$batch->status" /></td>
                </tr>
            @empty
                <x-list.empty colspan="5" message="No assigned batches." />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$batches" label="batches" />
</div>
@endsection
