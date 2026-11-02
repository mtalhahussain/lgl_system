@extends('layouts.admin')
@section('title', 'Reports')
@section('content')
<x-list.header title="Reports" eyebrow="Analytics" subtitle="Filter, print or export each report as CSV" />
@php $icons = ['enrollments' => 'fa-user-graduate', 'collections' => 'fa-coins', 'dues' => 'fa-wallet', 'attendance' => 'fa-clipboard-check']; @endphp
<div class="report-tiles">
    @foreach($reportTypes as $key => $report)
        <a class="panel report-tile" href="{{ route('reports.generate', $key) }}">
            <span class="metric-icon" aria-hidden="true"><i class="fas {{ $icons[$key] ?? 'fa-list' }}"></i></span>
            <strong>{{ $report['title'] }}</strong>
            <p>{{ $report['description'] }}</p>
        </a>
    @endforeach
</div>
@endsection
