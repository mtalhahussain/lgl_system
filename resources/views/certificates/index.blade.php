@extends('layouts.admin')
@section('title', 'Certificates')
@push('styles')
<style>
    .certificate-workspace { display:grid; grid-template-columns:minmax(0, 1.25fr) minmax(0, 1fr); gap:24px; align-items:start; }
    .certificate-preview { padding:28px; background:var(--accent-soft); }
    .certificate-preview-sheet { background:white; border:1px solid var(--line); outline:1px solid #efdfa6; outline-offset:-9px; padding:36px 24px; text-align:center; border-radius:4px; overflow-wrap:anywhere; }
    .certificate-preview-sheet .fa-award { color:var(--gold-ink); font-size:32px; margin-bottom:18px; }
    .certificate-preview-sheet h3 { font-family:Georgia,serif; font-size:24px; margin:8px 0 24px; }
    .certificate-preview-name { color:var(--navy); font-size:22px; font-weight:700; margin:12px 0; }
    .certificate-preview-level { display:inline-block; padding:6px 18px; border-radius:20px; color:var(--gold-ink); background:var(--accent-soft); font-weight:700; margin:12px 0; }
    .certificate-revoke-form { display:flex; flex-wrap:wrap; gap:10px; align-items:end; padding:12px; background:#fff7f7; border-radius:8px; }
    .certificate-revoke-field { flex:1 1 240px; }
    @media(max-width:991.98px) { .certificate-workspace { grid-template-columns:minmax(0, 1fr); } }
    @media(max-width:575.98px) { .certificate-preview { padding:18px; } }
</style>
@endpush
@section('content')
<a class="back-link" href="{{ route('exams.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to Exams</a>
<x-list.header title="Certificates" eyebrow="Academics" subtitle="Issue completion certificates and manage previously issued records.">
    <a class="btn btn-outline-secondary" href="#issued-heading"><i class="fas fa-list me-1" aria-hidden="true"></i>Issued certificates</a>
</x-list.header>

<div class="certificate-workspace">
    <form class="panel form-panel" method="post" action="{{ route('certificates.store') }}">
        @csrf
        <div class="form-section">
        <h2 class="form-section-title">Issue Certificate</h2>
        <p class="form-section-note">Check the name, level and completion date before issuing. Required fields are marked *.</p>
        @if(old('student_exam_result_id', $preselectResultId))
            <input type="hidden" name="student_exam_result_id" value="{{ old('student_exam_result_id', $preselectResultId) }}">
            <div class="alert alert-info small">Linked to a passed exam result. The certificate level follows that result.</div>
        @endif
        @error('student_exam_result_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        <div class="mb-3">
            <label class="form-label" for="student_id">Student</label>
            <select class="form-select" id="student_id" name="student_id">
                <option value="">— Select a student —</option>
                @foreach($students as $s)
                    <option value="{{ $s->id }}" @selected((int) old('student_id', $preselectId) === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            @error('student_id')<div class="text-danger small" role="alert">{{ $message }}</div>@enderror
            <div class="form-text">Selecting a student fills the name and level below; you can edit them.</div>
        </div>
        <div class="mb-3">
            <label class="form-label required" for="student_name">Name on certificate</label>
            <input class="form-control" id="student_name" name="student_name"
                   value="{{ old('student_name', $preselect['name'] ?? '') }}" required maxlength="255">
            @error('student_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="father_name">Father&rsquo;s name (S/O)</label>
            <input class="form-control" id="father_name" name="father_name"
                   value="{{ old('father_name') }}" maxlength="255">
            @error('father_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label required" for="level">Level</label>
                <select class="form-select" id="level" name="level" required>
                    @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $lvl)
                        <option value="{{ $lvl }}" @selected(old('level', $preselect['level'] ?? 'A1') === $lvl)>{{ $lvl }}</option>
                    @endforeach
                </select>
                @error('level')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label required" for="completion_date">Completion date</label>
                <input class="form-control" id="completion_date" name="completion_date" type="date"
                       value="{{ old('completion_date', now()->toDateString()) }}" required>
                @error('completion_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-outline-secondary" href="{{ route('exams.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit"><i class="fas fa-award me-1" aria-hidden="true"></i>Issue &amp; print</button>
        </div>
    </form>
    <aside class="panel certificate-preview" aria-labelledby="preview-heading">
        <h2 class="form-section-title" id="preview-heading">Certificate details preview</h2>
        <p class="form-section-note">Check the text below. The printed certificate uses the institute template.</p>
        <div class="certificate-preview-sheet">
            <i class="fas fa-award" aria-hidden="true"></i>
            <div class="eyebrow">{{ institute_name() }}</div>
            <h3>Course completion</h3>
            <p class="text-muted small mb-0">This certificate is for</p>
            <p class="certificate-preview-name" id="preview-name">{{ old('student_name', $preselect['name'] ?? '') ?: 'Student name' }}</p>
            <p class="small text-muted" id="preview-father">{{ old('father_name') ? 'S/O '.old('father_name') : '' }}</p>
            <div class="certificate-preview-level">Level <span id="preview-level">{{ old('level', $preselect['level'] ?? 'A1') }}</span></div>
            <p class="small text-muted mt-3 mb-0">Completion date: <span id="preview-date">{{ old('completion_date', now()->toDateString()) }}</span></p>
        </div>
        <p class="form-section-note mt-3 mb-0"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>Only one active certificate per student and level. Revoke an existing certificate before reissuing.</p>
    </aside>
</div>

<section class="panel mt-4" aria-labelledby="issued-heading">
    <div class="panel-heading flex-wrap"><div><h2 id="issued-heading">Issued certificates</h2><p class="page-subtitle mb-0 mt-1">Most recent 50 records. Open a certificate to view or print it.</p></div><span class="page-subtitle">{{ $certificates->count() }} shown</span></div>
    @error('reason')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="table-responsive"><table class="table list-table">
        <thead><tr><th scope="col">Number</th><th scope="col">Student</th><th scope="col">Level</th><th scope="col">Completed</th><th scope="col">Issued</th><th scope="col">Status</th><th scope="col" class="d-print-none">Actions</th></tr></thead>
        <tbody>
        @forelse($certificates as $certificate)
            <tr>
                <td class="cell-main">{{ $certificate->certificate_number }}</td>
                <td>{{ $certificate->student_name }}@if($certificate->father_name)<span class="cell-sub">S/O {{ $certificate->father_name }}</span>@endif</td>
                <td>{{ $certificate->level }}</td>
                <td>{{ $certificate->completion_date->format('d M Y') }}</td>
                <td>{{ $certificate->issued_at?->format('d M Y') }}</td>
                <td><span class="badge {{ $certificate->isRevoked() ? 'bg-danger' : 'bg-success' }}">{{ $certificate->isRevoked() ? 'Revoked' : 'Active' }}</span></td>
                <td class="d-print-none text-nowrap">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('certificates.print', $certificate) }}" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Reprint</a>
                    @unless($certificate->isRevoked())
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#revoke-{{ $certificate->id }}" aria-expanded="false" aria-controls="revoke-{{ $certificate->id }}">Revoke</button>
                    @endunless
                </td>
            </tr>
            @unless($certificate->isRevoked())
            <tr class="collapse d-print-none" id="revoke-{{ $certificate->id }}">
                <td colspan="7">
                    <form method="post" action="{{ route('certificates.revoke', $certificate) }}" class="certificate-revoke-form">
                        @csrf
                        <div class="certificate-revoke-field">
                            <label class="form-label small" for="reason-{{ $certificate->id }}">Reason for revoking {{ $certificate->certificate_number }}</label>
                            <input class="form-control form-control-sm" id="reason-{{ $certificate->id }}" name="reason" placeholder="Enter at least 5 characters" minlength="5" maxlength="1000" required>
                            <p class="form-text mb-0">The record stays in history and its printed copy will be marked REVOKED.</p>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#revoke-{{ $certificate->id }}">Cancel</button>
                        <button class="btn btn-danger btn-sm" type="submit">Confirm revoke</button>
                    </form>
                </td>
            </tr>
            @endunless
        @empty
            <tr><td colspan="7" class="empty-state"><i class="fas fa-award fa-2x d-block mb-3" aria-hidden="true"></i>No certificates issued yet.<span class="cell-sub">Complete the form above to issue your first certificate.</span></td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<script>
    (function () {
        var meta = @json($studentMeta);
        var sel = document.getElementById('student_id');
        var name = document.getElementById('student_name');
        var level = document.getElementById('level');
        var father = document.getElementById('father_name');
        var date = document.getElementById('completion_date');
        function updatePreview() {
            document.getElementById('preview-name').textContent = name.value.trim() || 'Student name';
            document.getElementById('preview-father').textContent = father.value.trim() ? 'S/O ' + father.value.trim() : '';
            document.getElementById('preview-level').textContent = level.value;
            document.getElementById('preview-date').textContent = date.value || 'Not selected';
        }
        [name, father, level, date].forEach(function (input) { input.addEventListener('input', updatePreview); input.addEventListener('change', updatePreview); });
        updatePreview();
        if (!sel) return;
        sel.addEventListener('change', function () {
            var m = meta[sel.value];
            if (!m) return;
            if (name) name.value = m.name || name.value;
            if (level && m.level) level.value = m.level;
            updatePreview();
        });
    })();
</script>
@endsection
