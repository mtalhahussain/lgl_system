@extends('layouts.admin')
@section('title', $isAdmin ? 'Teacher Payroll' : 'My Earnings')
@section('content')
<x-list.header :title="$isAdmin ? 'Teacher Payroll' : 'My Earnings'" eyebrow="Finance"
    :subtitle="$isAdmin ? 'Monthly salaries, per-student batch earnings and payouts' : 'Your salary and batch earnings with payment slips'" />
<x-list.kpis :items="$kpis" />
@if($isAdmin)
    <div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:lg:grid-cols-2 tw:mb-6 d-print-none">
        <section class="panel" aria-labelledby="generate-salaries">
            <div class="panel-heading"><h2 id="generate-salaries">Generate monthly salaries</h2></div>
            <form method="post" action="{{ route('payroll.salaries') }}" class="row g-2 align-items-end" style="padding:18px 22px">
                @csrf
                <div class="col-sm-7">
                    <label class="form-label" for="period">Month</label>
                    <input class="form-control" type="month" id="period" name="period" max="{{ now()->format('Y-m') }}" value="{{ old('period', now()->format('Y-m')) }}" required>
                </div>
                <div class="col-sm-5"><button class="btn btn-primary w-100">Generate</button></div>
                <p class="form-text mb-0">Active fixed-salary teachers with a salary set. Months already generated are skipped.</p>
            </form>
        </section>
        <section class="panel" aria-labelledby="generate-batch">
            <div class="panel-heading"><h2 id="generate-batch">Per-student batch earning</h2></div>
            <form method="post" action="{{ route('payroll.batches') }}" class="row g-2 align-items-end" style="padding:18px 22px">
                @csrf
                <div class="col-sm-7">
                    <label class="form-label" for="batch_id">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required @disabled($eligibleBatches->isEmpty())>
                        <option value="">{{ $eligibleBatches->isEmpty() ? 'No eligible batches' : 'Choose batch' }}</option>
                        @foreach($eligibleBatches as $batch)
                            <option value="{{ $batch->id }}" data-meta="{{ $batch->teacher?->name }} · {{ currency_format($batch->course?->teacher_per_student_amount ?? 0) }} per student" @selected(old('batch_id') == $batch->id)>{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-5"><button class="btn btn-primary w-100" @disabled($eligibleBatches->isEmpty())>Generate</button></div>
                <p class="form-text mb-0">Ongoing or completed batches of per-student teachers; active and completed students count once.</p>
            </form>
        </section>
    </div>
@endif
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" :search="$isAdmin ? 'Search teacher name or email…' : null" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                @if($isAdmin)<x-list.th sort="teacher" default="-created">Teacher</x-list.th>@endif
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
                <x-list.empty :colspan="$isAdmin ? 8 : 7" :message="$isAdmin ? 'No teacher dues yet. Generate salaries or a batch earning above.' : 'No earnings recorded yet.'" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$dues" label="dues" />
</div>
@endsection
