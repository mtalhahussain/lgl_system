@extends('layouts.admin')
@section('title', 'Assigned Levels')
@section('content')
<x-list.header icon="fa-book" title="Assigned Levels" eyebrow="My classes" subtitle="Levels that include one of your batches" />
<div class="panel list-panel">
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><th scope="col">Level</th><th scope="col">Code</th><th scope="col">Duration</th><th scope="col">Attachments</th></tr></thead>
            <tbody>
            @forelse($courses as $course)
                <tr>
                    <td><a class="cell-main" href="{{ route('courses.show', $course) }}">{{ $course->name }}</a></td>
                    <td><span class="status-pill">{{ $course->level }}</span></td>
                    <td>{{ $course->duration_weeks }} weeks</td>
                    <td>@include('materials.level-attachments', ['course' => $course])</td>
                </tr>
            @empty
                <x-list.empty colspan="4" message="No assigned courses." />
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
