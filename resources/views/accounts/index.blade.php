@extends('layouts.admin')
@section('title', 'Account Access')
@section('content')
<h1>Account Access</h1>
<p>Admin accounts can manage the whole institute. Deactivation preserves history.</p>
@foreach($accounts as $account)
    <form method="post" action="{{ route('accounts.update', $account) }}" class="card card-body mb-3" onsubmit="return confirm('Apply these account access changes?');">
        @csrf @method('PATCH')
        <h2 class="h5">{{ $account->name }}</h2><p>{{ $account->email }}</p>
        <label for="role_{{ $account->id }}">Role</label>
        <select id="role_{{ $account->id }}" name="role" class="form-select mb-2" required>
            <option value="">Choose a supported role</option>
            @foreach(\App\Models\User::ROLES as $role)<option value="{{ $role }}" @selected($account->role === $role)>{{ ucfirst($role) }}</option>@endforeach
        </select>
        <input type="hidden" name="is_active" value="0">
        <label class="mb-2"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)> Active account</label>
        <div><button class="btn btn-primary">Save access</button></div>
    </form>
@endforeach
{{ $accounts->links() }}
@endsection
