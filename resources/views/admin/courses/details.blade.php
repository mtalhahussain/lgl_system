@extends('layouts.admin')
@section('title', $course->name)
@section('content')
<x-show.hero icon="fa-book" :eyebrow="'Course · Level '.$course->level" :title="$course->name" :status="$course->is_active ? 'active' : 'inactive'">
    <x-slot:meta>
        <span><i class="fas fa-hourglass-half" aria-hidden="true"></i>{{ $course->duration_weeks }} weeks</span>
        @if($course->sessions_per_week)<span><i class="fas fa-calendar-week" aria-hidden="true"></i>{{ $course->sessions_per_week }} sessions/week{{ $course->session_duration_minutes ? ' · '.$course->session_duration_minutes.' min' : '' }}</span>@endif
    </x-slot:meta>
    <a class="btn btn-primary" href="{{ route('batches.create', ['course_id' => $course->id]) }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>New batch</a>
    <a class="btn btn-outline-primary" href="{{ route('courses.edit', $course) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>
    <form class="d-inline" method="post" action="{{ route('courses.toggle-status', $course) }}" data-confirm="{{ $course->is_active ? 'Deactivate this course? Existing batches stay unchanged.' : 'Activate this course?' }}">
        @csrf <button class="btn btn-outline-secondary">{{ $course->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>
</x-show.hero>

<x-list.kpis :items="[
    ['label' => 'Batches', 'value' => $batches->count(), 'icon' => 'fa-layer-group'],
    ['label' => 'Ongoing', 'value' => $batches->where('status', 'ongoing')->count(), 'icon' => 'fa-play'],
    ['label' => 'Active students', 'value' => $batches->sum('active_enrollments_count'), 'icon' => 'fa-user-graduate'],
]" />

<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:xl:grid-cols-3">
    <div class="tw:flex tw:flex-col tw:gap-5">
        <section class="panel" aria-labelledby="fees-heading">
            <div class="panel-heading"><h2 id="fees-heading">Fees</h2></div>
            <div style="padding:6px 22px 14px">
                <div class="finance-row"><span>Default course fee</span><strong>{{ currency_format($course->total_fee) }}</strong></div>
                <div class="finance-row"><span>Teacher amount per student</span><strong>{{ currency_format($course->teacher_per_student_amount) }}</strong></div>
                <p class="form-text mb-0 mt-2">Fee changes apply to new admissions only.</p>
            </div>
        </section>
        @if($course->description)
            <section class="panel" aria-labelledby="about-heading">
                <div class="panel-heading"><h2 id="about-heading">About</h2></div>
                <p style="padding:16px 22px;margin:0;font-size:13px;white-space:pre-line">{{ $course->description }}</p>
            </section>
        @endif
    </div>
    <section class="panel tw:xl:col-span-2" aria-labelledby="batches-heading">
        <div class="panel-heading"><h2 id="batches-heading">Batches</h2><span class="page-subtitle">{{ $batches->count() }} total</span></div>
        <div class="table-responsive"><table class="table list-table">
            <thead><tr><th scope="col">Batch</th><th scope="col">Teacher</th><th scope="col">Dates</th><th scope="col">Students</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td><a class="cell-main" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a></td>
                    <td>{{ $batch->teacher?->name ?? '—' }}</td>
                    <td class="text-nowrap">{{ $batch->start_date?->format('d M Y') }}<span class="cell-sub">{{ $batch->end_date ? 'to '.$batch->end_date->format('d M Y') : 'No end date' }}</span></td>
                    <td>{{ $batch->active_enrollments_count }} / {{ $batch->max_students }}</td>
                    <td><x-list.status :value="$batch->status" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No batches yet. Create the first batch for this course.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
