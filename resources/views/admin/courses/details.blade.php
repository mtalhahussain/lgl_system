@extends('layouts.admin')
@section('title', $course->name)
@section('content')
<x-show.hero icon="fa-book" :eyebrow="'Level · Code '.$course->level" :title="$course->name" :status="$course->is_active ? 'active' : 'inactive'">
    <x-slot:meta>
        <span><i class="fas fa-hourglass-half" aria-hidden="true"></i>{{ $course->duration_weeks }} weeks</span>
        @if($course->sessions_per_week)<span><i class="fas fa-calendar-week" aria-hidden="true"></i>{{ $course->sessions_per_week }} sessions/week{{ $course->session_duration_minutes ? ' · '.$course->session_duration_minutes.' min' : '' }}</span>@endif
    </x-slot:meta>
    @if(can_route('batches.create'))<a class="btn btn-primary" href="{{ route('batches.create', ['course_id' => $course->id]) }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>New batch</a>@endif
    @if(can_route('courses.edit'))<a class="btn btn-outline-primary" href="{{ route('courses.edit', $course) }}"><i class="fas fa-pen me-1" aria-hidden="true"></i>Edit</a>@endif
    @if(can_route('courses.toggle-status'))<form class="d-inline" method="post" action="{{ route('courses.toggle-status', $course) }}" data-confirm="{{ $course->is_active ? 'Deactivate this level? Existing batches stay unchanged.' : 'Activate this level?' }}">
        @csrf <button class="btn btn-outline-secondary">{{ $course->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>@endif
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
            <div class="pad-panel-body">
                <div class="finance-row"><span>Default level fee</span><strong>{{ currency_format($course->total_fee) }}</strong></div>
                <div class="finance-row"><span>Teacher amount per student</span><strong>{{ currency_format($course->teacher_per_student_amount) }}</strong></div>
                <p class="form-text mb-0 mt-2">Fee changes apply to new admissions only.</p>
            </div>
        </section>
        @if($course->description)
            <section class="panel" aria-labelledby="about-heading">
                <div class="panel-heading"><h2 id="about-heading">About</h2></div>
                <p class="panel-prose">{{ $course->description }}</p>
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
                <tr><td colspan="5" class="empty-state">No batches yet. Create the first batch for this level.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>

<section class="panel mt-4" aria-labelledby="materials-heading">
    <div class="panel-heading"><h2 id="materials-heading">Books &amp; Materials</h2><span class="page-subtitle">{{ $materials->count() }} {{ Str::plural('file', $materials->count()) }} · visible to assigned teachers and active/completed students</span></div>
    @if(can_route('materials.store'))<form class="pad-panel-body" method="post" action="{{ route('materials.store', $course) }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="material_title" class="form-label required">Title</label>
                <input class="form-control @error('title') is-invalid @enderror" id="material_title" name="title" value="{{ old('title') }}" maxlength="255" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="material_file" class="form-label required">File</label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="material_file" name="file" required
                    accept=".{{ implode(',.', \App\Models\LevelMaterial::EXTENSIONS) }}">
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="material_description" class="form-label">Description</label>
                <input class="form-control @error('description') is-invalid @enderror" id="material_description" name="description" value="{{ old('description') }}" maxlength="2000">
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 mt-3">
            <button class="btn btn-primary"><i class="fas fa-upload me-1" aria-hidden="true"></i>Upload</button>
            <span class="form-text">PDF, Word, PowerPoint, JPG or PNG · max 20 MB</span>
        </div>
    </form>@endif
    @include('materials.table', ['materials' => $materials, 'manage' => true])
</section>
@endsection
