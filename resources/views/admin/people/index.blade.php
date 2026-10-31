@extends('layouts.admin')
@section('title', ucfirst($kind))
@section('content')
<h1>{{ ucfirst($kind) }}</h1>
<a class="btn btn-primary mb-3" href="{{ route($kind.'.create') }}">Add {{ $kind === 'students' ? 'student' : 'teacher' }}</a>
<form method="get" class="mb-3"><label for="search">Name, email or phone</label> <input id="search" name="search" value="{{ request('search') }}"> <button class="btn btn-outline-primary">Search</button></form>
<table class="table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @foreach($people as $person)
        <tr><td><a href="{{ route($kind.'.show', $person) }}">{{ $person->name }}</a></td><td>{{ $person->email }}</td><td>{{ $person->is_active ? 'Active' : 'Inactive' }}</td><td>
            <a href="{{ route($kind.'.edit', $person) }}">Edit</a>
            @if($person->is_active)
                <form class="d-inline" method="post" action="{{ route($kind.'.destroy', $person) }}" onsubmit="return confirm('Deactivate this account? History will be preserved.');">@csrf @method('DELETE') <button class="btn btn-link">Deactivate</button></form>
            @endif
        </td></tr>
    @endforeach
</tbody></table>
{{ $people->links() }}
@endsection
