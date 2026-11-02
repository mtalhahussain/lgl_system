@extends('layouts.admin')
@section('title', 'Assigned Students')
@section('content')
<x-list.header title="Assigned Students" eyebrow="My classes" subtitle="Students enrolled in your batches" />
<div class="panel list-panel">
    <x-list.toolbar :filters="[['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches]]" search="Search student name…" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr><x-list.th sort="name" default="name">Name</x-list.th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($students as $student)
                <tr>
                    <td><div class="person-cell"><span class="user-avatar" aria-hidden="true">{{ mb_substr($student->name, 0, 1) }}</span><a class="cell-main" href="{{ route('students.show', $student) }}">{{ $student->name }}</a></div></td>
                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('students.show', $student) }}">View</a></td>
                </tr>
            @empty
                <x-list.empty colspan="2" message="No assigned students." />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$students" label="students" />
</div>
@endsection
