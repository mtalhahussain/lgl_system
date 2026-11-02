@extends('layouts.admin')
@section('title', 'Outstanding Dues')
@section('content')
<x-list.header title="Outstanding Dues" eyebrow="Reports" subtitle="Scheduled installments that still have a balance">
    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Print</button>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search student…" :csv="true" :per-page-default="25" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <th scope="col">Student</th><th scope="col">Batch</th>
                <x-list.th sort="due" default="due">Due date</x-list.th>
                <x-list.th sort="amount" default="due" class="num">Scheduled</x-list.th>
                <th scope="col" class="num">Outstanding</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr><td class="cell-main">{{ $row->enrollment->student->name }}</td><td>{{ $row->enrollment->batch->name }}</td>
                    <td>{{ $row->due_date->format('d M Y') }} @if($row->due_date->lt(today()))<x-list.status value="overdue" />@endif</td>
                    <td class="num">{{ currency_format($row->amount) }}</td><td class="num"><strong>{{ currency_format($row->outstanding_amount) }}</strong></td></tr>
            @empty
                <x-list.empty colspan="5" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$rows" label="installments" />
</div>
@endsection
