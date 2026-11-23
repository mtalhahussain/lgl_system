@extends('layouts.admin')
@section('title', 'Books & Materials')
@section('content')
<x-list.header icon="fa-file-pdf" title="Books & Materials" eyebrow="Levels"
    :subtitle="auth()->user()->isStudent() ? 'Books and files for the levels you are enrolled in' : (auth()->user()->isTeacher() ? 'Books and files for the levels of your batches' : 'Books and files uploaded for every level')" />

@forelse($levels as $group)
    <section class="panel mb-4" aria-labelledby="level-{{ $group['course']->id }}">
        <div class="panel-heading">
            <h2 id="level-{{ $group['course']->id }}">{{ $group['course']->name }}@if($group['course']->level) ({{ $group['course']->level }})@endif</h2>
            <span class="page-subtitle">{{ $group['materials']->count() }} {{ Str::plural('file', $group['materials']->count()) }}</span>
        </div>
        @include('materials.table', ['materials' => $group['materials'], 'manage' => false])
    </section>
@empty
    <section class="panel"><p class="empty-state mb-0">No books or materials are available yet.</p></section>
@endforelse
@endsection
