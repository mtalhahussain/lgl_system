@extends('layouts.admin')
@section('title', 'Multi-level Package')
@section('content')
<x-list.header title="Multi-level package" eyebrow="Admissions" subtitle="Two to four levels in a row (A1 + A2, A1 to B1, A1 to B2) at one agreed price." />
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('enrollment-packages.store') }}" data-confirm="Enroll this student in every selected batch with this package price?">
    @csrf
    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="panel p-4">
                <h2 class="h5 mb-3">Student and levels</h2>
                <label class="form-label required" for="student_id">Existing student</label>
                <select class="form-select mb-3" id="student_id" name="student_id" required>
                    <option value="">Choose student</option>
                    @foreach($students as $student)<option value="{{ $student->id }}" data-meta="{{ $student->email }}" @selected(old('student_id') == $student->id)>{{ $student->name }}</option>@endforeach
                </select>
                @forelse($levels as $index => $level)
                    <label class="form-label" for="batch_{{ $level }}">{{ $level }} batch</label>
                    <select class="form-select mb-3" id="batch_{{ $level }}" name="batch_ids[]" data-package-batch data-level="{{ $level }}">
                        <option value="">Not in this package</option>
                        @foreach($batches->filter(fn ($batch) => $batch->course->level === $level) as $batch)
                            <option value="{{ $batch->id }}" data-fee="{{ \App\Support\Money::minor($batch->course->total_fee) }}"
                                data-meta="{{ $batch->start_date?->format('d M Y') }} – {{ $batch->end_date?->format('d M Y') ?? 'no end date' }} · {{ $batch->teacher?->name }} · {{ $batch->meeting_platform === 'in_person' ? 'On-site' : 'Online' }} · {{ max(0, $batch->max_students - $batch->active_enrollments_count) }} seats left · fee {{ currency_format($batch->course->total_fee) }}"
                                @selected((old('batch_ids')[$index] ?? null) == $batch->id)>{{ $batch->name }}</option>
                        @endforeach
                    </select>
                @empty
                    <p class="text-muted">No open batches. Create batches for each level first.</p>
                @endforelse
                <p class="form-text">Choose levels in a row without gaps. Each next batch must start after the previous one ends, so later-level batches must already be scheduled. Seats are reserved now; this is not automatic promotion. Each level keeps its own attendance, enrollment and fee ledger.</p>
                <div id="package-allocation" class="alert alert-light border mb-0" role="status" aria-live="polite">Select at least two levels to preview the split.</div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="panel p-4">
                <h2 class="h5 mb-3">Price and payment</h2>
                <label class="form-label required" for="price">Package price</label>
                <input class="form-control" id="price" name="price" type="number" step="0.01" min="0.01" value="{{ old('price') }}" required>
                <p class="form-text" id="price-help">Agreed total for all selected levels, including any special discount for this student.</p>
                <fieldset class="mb-3">
                    <legend class="form-label">Payment plan</legend>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="plan" id="plan_full" value="full" @checked(old('plan', 'full') === 'full')>
                        <label class="form-check-label" for="plan_full">Full payment received now</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="plan" id="plan_installments" value="installments" @checked(old('plan') === 'installments')>
                        <label class="form-check-label" for="plan_installments">Monthly installments per level</label>
                    </div>
                </fieldset>
                <div data-plan="installments">
                    <label class="form-label required" for="installments">Installments per level</label>
                    <input class="form-control" id="installments" name="installments" type="number" min="2" max="12" value="{{ old('installments', 2) }}">
                    <p class="form-text">Each level's share is split into equal monthly installments, starting when that level starts (or today if already running). Collect them later from the fee ledger.</p>
                </div>
                <div data-plan="full">
                    <label class="form-label required" for="method">Payment method</label>
                    <select class="form-select mb-3" id="method" name="method">
                        @foreach(['cash' => 'Cash', 'bank' => 'Bank', 'card' => 'Card', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'online' => 'Online'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="form-label" for="reference">Payment reference (optional)</label>
                    <input class="form-control mb-3" id="reference" name="reference" maxlength="255" value="{{ old('reference') }}">
                    <p class="form-text">Only record money already received. The package receipt summarizes the level allocations; it is not an extra collection.</p>
                </div>
                <p class="form-text">Level prices come from this package and cannot be edited separately afterwards.</p>
                <button class="btn btn-primary w-100">Enroll package</button>
            </section>
        </div>
    </div>
</form>
<script>
(() => {
    const selects = [...document.querySelectorAll('[data-package-batch]')];
    const output = document.getElementById('package-allocation');
    const price = document.getElementById('price');
    const count = document.getElementById('installments');
    const plans = [...document.querySelectorAll('input[name=plan]')];
    const currency = @json(setting('currency_code', 'PKR'));
    const format = minor => currency + ' ' + (minor / 100).toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const plan = () => plans.find(input => input.checked)?.value || 'full';
    function togglePlan() {
        document.querySelectorAll('[data-plan]').forEach(block => {
            const on = block.dataset.plan === plan();
            block.hidden = ! on;
            block.querySelectorAll('input, select').forEach(field => { field.disabled = ! on; });
        });
        document.getElementById('method').required = plan() === 'full';
        count.required = plan() === 'installments';
    }
    function update() {
        const chosen = selects.filter(select => select.value).map(select => ({level: select.dataset.level, fee: Number(select.selectedOptions[0].dataset.fee)}));
        if (chosen.length < 2) { output.textContent = 'Select at least two levels to preview the split.'; return; }
        const gross = chosen.reduce((sum, row) => sum + row.fee, 0);
        const total = Math.round(Number(price.value || 0) * 100);
        if (! total) { output.textContent = 'Normal fees total ' + format(gross) + '. Enter the package price.'; return; }
        if (total > gross) { output.textContent = 'Price is more than the normal fees total (' + format(gross) + ').'; return; }
        let used = 0;
        const parts = chosen.map((row, index) => {
            const share = index === chosen.length - 1 ? total - used
                : Number((BigInt(total) * BigInt(row.fee) + BigInt(Math.floor(gross / 2))) / BigInt(gross));
            used += share;
            const each = plan() === 'installments' && Number(count.value) >= 2 ? ' (' + count.value + ' × ~' + format(Math.floor(share / Number(count.value))) + ')' : '';
            return row.level + ': ' + format(share) + each;
        });
        const discount = gross - total;
        output.textContent = parts.join(' · ') + '. Normal ' + format(gross) + ', package ' + format(total)
            + ', discount ' + format(discount) + ' (' + (discount * 100 / gross).toFixed(2) + '%).';
    }
    selects.forEach(select => select.addEventListener('change', update));
    [price, count].forEach(input => input.addEventListener('input', update));
    plans.forEach(input => input.addEventListener('change', () => { togglePlan(); update(); }));
    togglePlan();
    update();
})();
</script>
@endsection
