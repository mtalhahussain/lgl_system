@extends('layouts.admin')
@section('title', 'Assigned Courses')
@section('content')
<x-list.header title="Assigned Courses" eyebrow="My classes" subtitle="Courses that include one of your batches" />
<div class="panel list-panel">
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Course</th><th scope="col">Level</th><th scope="col">Duration</th></tr></thead>
            <tbody>
            @forelse($courses as $course)
                <tr>
                    <td><a class="cell-main" href="{{ route('courses.show', $course) }}">{{ $course->name }}</a></td>
                    <td><span class="status-pill">{{ $course->level }}</span></td>
                    <td>{{ $course->duration_weeks }} weeks</td>
                </tr>
            @empty
                <x-list.empty colspan="3" message="No assigned courses." />
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
