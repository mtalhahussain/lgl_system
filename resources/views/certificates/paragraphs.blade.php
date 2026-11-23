@extends('layouts.admin')
@section('title', 'Certificate paragraphs')
@section('content')
<x-list.header icon="fa-align-left" title="Certificate paragraphs" eyebrow="Settings" subtitle="Set the German paragraph once for each level. New certificates use the selected level automatically.">
    <a class="btn btn-outline-secondary" href="{{ route('certificates.index') }}">Back to Certificates</a>
</x-list.header>
<form method="post" action="{{ route('settings.certificate-paragraphs.update') }}" class="panel form-panel">
    @csrf @method('PUT')
    <div class="form-section">
        <p class="form-section-note">Previously issued certificates keep their original wording. Use plain text, up to 350 characters per level. Text must fit above the signature; keep it concise.</p>
        <div class="row g-4">
            @foreach($paragraphs as $level => $paragraph)
                <div class="col-lg-6">
                    <label class="form-label required" for="paragraph-{{ $level }}">{{ $level }} paragraph</label>
                    <textarea class="form-control @error('paragraphs.'.$level) is-invalid @enderror" id="paragraph-{{ $level }}" name="paragraphs[{{ $level }}]" rows="6" maxlength="350" required>{{ old('paragraphs.'.$level, $paragraph) }}</textarea>
                    @error('paragraphs.'.$level)<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save level paragraphs</button></div>
</form>
@endsection
