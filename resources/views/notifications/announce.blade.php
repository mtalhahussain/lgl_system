@extends('layouts.admin')

@section('title', 'New Announcement')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-bullhorn me-2"></i>Send Announcement</h1>
    <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('notifications.announce.send') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="120" value="{{ old('title') }}" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="4" maxlength="1000" required>{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label d-block">Send to</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="audience[]" value="student" id="aud-student" {{ in_array('student', old('audience', ['student'])) ? 'checked' : '' }}>
                    <label class="form-check-label" for="aud-student">Students</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="audience[]" value="teacher" id="aud-teacher" {{ in_array('teacher', old('audience', [])) ? 'checked' : '' }}>
                    <label class="form-check-label" for="aud-teacher">Teachers</label>
                </div>
                @error('audience')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Announcement</button>
        </form>
    </div>
</div>
@endsection
