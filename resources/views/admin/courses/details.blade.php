@extends('layouts.admin')
@section('title', 'Course Details')
@section('content')
<h1>{{ $course->name }} ({{ $course->level }})</h1>
<p>{{ $course->description }}</p>
<p>Default fee: {{ currency_format($course->total_fee) }}; duration: {{ $course->duration_weeks }} weeks.</p>
<p>Status: {{ $course->is_active ? 'Active' : 'Inactive' }}</p>
<a class="btn btn-primary" href="{{ route('courses.edit', $course) }}">Edit course</a>
<form class="d-inline" method="post" action="{{ route('courses.toggle-status', $course) }}">@csrf <button class="btn btn-outline-primary">{{ $course->is_active ? 'Deactivate' : 'Activate' }}</button></form>
<h2 class="mt-3">Batches</h2>
<ul class="list-group">@foreach($course->batches as $batch)<li class="list-group-item"><a href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a> — {{ $batch->teacher->name }}, {{ $batch->status }}</li>@endforeach</ul>
@endsection
