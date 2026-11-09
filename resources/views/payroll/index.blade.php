@extends('layouts.admin')
@section('title', $isAdmin ? 'Payroll' : 'My Earnings')
@section('content')
<x-list.header :title="$isAdmin ? 'Payroll' : 'My Earnings'" eyebrow="Finance"
    :subtitle="$isAdmin ? 'Teacher and staff salaries, per-student batch earnings and payouts' : 'Your salary and batch earnings with payment slips'" />
<x-list.kpis :items="$kpis" />
@if($isAdmin)
    <div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:lg:grid-cols-2 tw:mb-6 d-print-none">
        <section class="panel" aria-labelledby="generate-salaries">
            <div class="panel-heading"><h2 id="generate-salaries">Generate month</h2></div>
            <form method="post" action="{{ route('payroll.salaries') }}" class="row g-2 align-items-end" style="padding:18px 22px">
                @csrf
                <div class="col-sm-7">
                    <label class="form-label" for="period">Month</label>
                    <input class="form-control" type="month" id="period" name="period" max="{{ now()->format('Y-m') }}" value="{{ old('period', now()->format('Y-m')) }}" required>
                </div>
                <div class="col-sm-5"><button class="btn btn-primary w-100">Generate</button></div>
                <p class="form-text mb-0">Creates fixed monthly salaries for teachers and staff, and per-student earnings (rate × students in the batch that month) for per-student teachers. Anything already generated is skipped.</p>
            </form>
        </section>
        <section class="panel" aria-labelledby="generate-batch">
            <div class="panel-heading"><h2 id="generate-batch">One batch, one month</h2></div>
            <form method="post" action="{{ route('payroll.batches') }}" class="row g-2 align-items-end" style="padding:18px 22px">
                @csrf
                <div class="col-sm-4">
                    <label class="form-label" for="batch_period">Month</label>
                    <input class="form-control" type="month" id="batch_period" name="period" max="{{ now()->format('Y-m') }}" value="{{ old('period', now()->format('Y-m')) }}" required>
                </div>
                <div class="col-sm-5">
                    <label class="form-label" for="batch_id">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required @disabled($eligibleBatches->isEmpty())>
                        <option value="">{{ $eligibleBatches->isEmpty() ? 'No eligible batches' : 'Choose batch' }}</option>
                        @foreach($eligibleBatches as $batch)
                            <option value="{{ $batch->id }}" data-meta="{{ $batch->teacher?->name }} · {{ currency_format($batch->teacher?->per_student_amount ?? 0) }} per student per month" @selected(old('batch_id') == $batch->id)>{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3"><button class="btn btn-primary w-100" @disabled($eligibleBatches->isEmpty())>Generate</button></div>
                <p class="form-text mb-0">Per-student teachers are paid monthly: course rate × students enrolled during that month (students who left count until they left).</p>
            </form>
        </section>
    </div>
@endif
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" :search="$isAdmin ? 'Search name or email…' : null" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                @if($isAdmin)<x-list.th sort="teacher" default="-created">Person</x-list.th>@endif
                <th scope="col">For</th>
                <x-list.th sort="amount" default="-created" class="num">Amount</x-list.th>
                <th scope="col" class="num">Paid</th>
                <th scope="col" class="num">Outstanding</th>
                <th scope="col">Status</th>
                <x-list.th sort="created" default="-created">Created</x-list.th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($dues as $due)
                <tr>
                    @if($isAdmin)<td><span class="cell-main">{{ $due->teacher->name }}</span></td>@endif
                    <td><span class="cell-main">{{ $due->label() }}</span>@if($due->kind === 'batch')<span class="cell-sub">{{ $due->student_count }} students × {{ currency_format($due->rate) }}</span>@endif</td>
                    <td class="num">{{ currency_format($due->amount) }}</td>
                    <td class="num">{{ currency_format(\App\Support\Money::decimal($due->paidMinor())) }}</td>
                    <td class="num"><strong>{{ currency_format(\App\Support\Money::decimal($due->outstandingMinor())) }}</strong></td>
                    <td><x-list.status :value="$due->state()" :label="$due->voided_at ? 'voided' : null" /></td>
                    <td>{{ $due->created_at?->format('d M Y') }}</td>
                    <td class="text-end"><a class="btn btn-icon" href="{{ route('payroll.show', $due) }}" aria-label="Open {{ $due->label() }}{{ $isAdmin ? ' for '.$due->teacher->name : '' }}"><i class="fas fa-arrow-right" aria-hidden="true"></i></a></td>
                </tr>
            @empty
                <x-list.empty :colspan="$isAdmin ? 8 : 7" :message="$isAdmin ? 'No dues yet. Generate a month or a batch earning above.' : 'No earnings recorded yet.'" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$dues" label="dues" />
</div>
@endsection
