@extends('layouts.admin')
@section('title', 'A1 + A2 Package')
@section('content')
<x-list.header title="A1 + A2 package" eyebrow="Admissions" subtitle="Two levels. One full advance payment." />
<div class="alert alert-info">Package price: <strong>{{ currency_format(\App\Services\EnrollmentService::PACKAGE_AMOUNT) }}</strong>, paid in full today. For installments, use <a href="{{ route('enrollments.create') }}">module-wise enrollment</a>.</div>
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('enrollment-packages.store') }}" data-confirm="Record the full package payment and enroll this student in both selected batches?">
    @csrf
    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="panel p-4">
                <h2 class="h5 mb-3">Student and modules</h2>
                <label class="form-label" for="student_id">Existing student</label>
                <select class="form-select mb-3" id="student_id" name="student_id" required data-combobox>
                    <option value="">Choose student</option>
                    @foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>{{ $student->name }} — {{ $student->email }}</option>@endforeach
                </select>
                @foreach(['A1' => 'a1_batch_id', 'A2' => 'a2_batch_id'] as $level => $field)
                    <label class="form-label" for="{{ $field }}">{{ $level }} batch</label>
                    <select class="form-select mb-3" id="{{ $field }}" name="{{ $field }}" required data-package-batch>
                        <option value="">Choose {{ $level }} batch</option>
                        @foreach($batches as $batch)
                            @if(strtoupper(str_replace(['-', ' '], '', $batch->course->level)) === $level)
                                <option value="{{ $batch->id }}" data-fee="{{ \App\Support\Money::minor($batch->course->total_fee) }}" @selected(old($field) == $batch->id)>{{ $batch->name }} — {{ $batch->start_date?->format('d M Y') }} to {{ $batch->end_date?->format('d M Y') ?? 'No end date' }} — normal fee {{ currency_format($batch->course->total_fee) }}</option>
                            @endif
                        @endforeach
                    </select>
                @endforeach
                <p class="form-text">A2 must start after A1 ends. Both batches must be open and have seats. Each module keeps its own attendance, enrollment and payment history. A2's seat is reserved now; it is not automatic promotion.</p>
                <div id="package-allocation" class="alert alert-light border mb-0" role="status" aria-live="polite">Select both batches to preview the fee split.</div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="panel p-4">
                <h2 class="h5 mb-3">Full payment</h2>
                <label class="form-label" for="amount">Amount received</label>
                <input class="form-control mb-3" id="amount" name="amount" type="number" step="0.01" value="{{ \App\Services\EnrollmentService::PACKAGE_AMOUNT }}" readonly required>
                <label class="form-label" for="method">Payment method</label>
                <select class="form-select mb-3" id="method" name="method" required>
                    @foreach(['cash' => 'Cash', 'bank' => 'Bank', 'card' => 'Card', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'online' => 'Online'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="reference">Payment reference (optional)</label>
                <input class="form-control mb-3" id="reference" name="reference" maxlength="255" value="{{ old('reference') }}">
                <p class="form-text">Only record money already received. The combined receipt summarizes two module allocations; it is not an extra collection. Package discounts cannot be changed separately.</p>
                <button class="btn btn-primary w-100">Enroll both levels and record full payment</button>
            </section>
        </div>
    </div>
</form>
<script>
(() => {
    const selects = [...document.querySelectorAll('[data-package-batch]')];
    const output = document.getElementById('package-allocation');
    const total = {{ \App\Support\Money::minor(\App\Services\EnrollmentService::PACKAGE_AMOUNT) }};
    const currency = @json(setting('currency_code', 'PKR'));
    const format = minor => currency + ' ' + (minor / 100).toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function update() {
        const fees = selects.map(select => Number(select.selectedOptions[0]?.dataset.fee || 0));
        if (fees.some(fee => fee <= 0)) { output.textContent = 'Select both batches to preview the fee split.'; return; }
        const gross = fees[0] + fees[1];
        if (gross < total) { output.textContent = 'Normal module fees total less than the package price. Check course fees or use module-wise enrollment.'; return; }
        const first = Number((BigInt(total) * BigInt(fees[0]) + BigInt(Math.floor(gross / 2))) / BigInt(gross));
        output.textContent = 'A1: ' + format(first) + ' · A2: ' + format(total - first) + '. Total: ' + format(total) + '. Split follows normal module fees.';
    }
    selects.forEach(select => select.addEventListener('change', update));
    update();
})();
</script>
@endsection
