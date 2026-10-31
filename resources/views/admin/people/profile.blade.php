@extends('layouts.admin')
@section('title', 'Profile')
@section('content')
<h1>{{ $person->name }}</h1>
<dl><dt>Email</dt><dd>{{ $person->email }}</dd><dt>Phone</dt><dd>{{ $person->phone }}</dd><dt>Address</dt><dd>{{ $person->address }}</dd><dt>Status</dt><dd>{{ $person->is_active ? 'Active' : 'Inactive' }}</dd></dl>
@if(auth()->user()->isAdmin())<a class="btn btn-outline-primary" href="{{ route($kind.'.edit', $person) }}">Edit profile</a>@endif
@if($kind === 'students')
    <h2 class="mt-3">Enrollments</h2>
    <ul class="list-group">@foreach($enrollments as $row)<li class="list-group-item">{{ $row->batch->name }} — {{ $row->status }}</li>@endforeach</ul>
    <a class="btn btn-primary mt-3" href="{{ route('fees.student', $person) }}">Fees and receipts</a>
    <a class="btn btn-outline-primary mt-3" href="{{ route('attendance.student', $person) }}">Attendance</a>
@else
    <h2 class="mt-3">Assigned Batches</h2>
    <ul class="list-group">@foreach($batches as $batch)<li class="list-group-item"><a href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a></li>@endforeach</ul>
@endif
@endsection
