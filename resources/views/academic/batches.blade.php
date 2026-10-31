@extends('layouts.admin')
@section('title', 'Assigned Batches')
@section('content')
<h1>{{ isset($course) ? $course->name : 'Assigned Batches' }}</h1>
<ul class="list-group mb-3">
    @forelse($batches as $batch)
        <li class="list-group-item">
            <a href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>
            — {{ $batch->status }}, {{ $batch->active_enrollments_count }} enrolled
        </li>
    @empty
        <li class="list-group-item">No assigned batches.</li>
    @endforelse
</ul>
{{ $batches->links() }}
@endsection
