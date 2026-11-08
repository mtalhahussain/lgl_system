@extends('layouts.admin')
@section('title', 'Enrollments')
@section('content')
<x-list.header title="Enrollments" eyebrow="Admissions" subtitle="Student admissions, fee agreements and status">
    <a class="btn btn-outline-primary" href="{{ route('enrollment-packages.create') }}">Multi-level package</a>
    <a class="btn btn-primary" href="{{ route('enrollments.create') }}"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Enroll student</a>
</x-list.header>
<x-list.kpis :items="$kpis" />
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search student name or email…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="student" default="-admitted">Student</x-list.th>
                <th scope="col">Course / batch</th>
                <x-list.th sort="admitted" default="-admitted">Admitted</x-list.th>
                <th scope="col">Status</th>
                <th scope="col" class="num">Discount</th>
                <th scope="col" class="num">Outstanding</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr></thead>
            <tbody>
            @forelse($enrollments as $enrollment)
                <tr>
                    <td><a class="cell-main" href="{{ route('enrollments.show', $enrollment) }}">{{ $enrollment->student->name }}</a><span class="cell-sub">{{ $enrollment->student->email }}</span></td>
                    <td>{{ $enrollment->batch->course?->name }}@if($enrollment->batch->course?->level) ({{ $enrollment->batch->course?->level }})@endif<span class="cell-sub">{{ $enrollment->batch->name }}</span></td>
                    <td>{{ $enrollment->enrollment_date?->format('d M Y') }}</td>
                    <td><x-list.status :value="$enrollment->status" :label="$statuses[$enrollment->status] ?? null" /></td>
                    <td class="num">{{ $enrollment->discount_percentage }}%@if(\App\Support\Money::minor($enrollment->discount_amount) > 0)<span class="cell-sub">{{ currency_format($enrollment->discount_amount) }}</span>@endif</td>
                    <td class="num">{{ currency_format($enrollment->remaining_fee) }}</td>
                    <td class="text-end">
                        <x-list.row-menu :label="$enrollment->student->name">
                            <li><a class="dropdown-item" href="{{ route('enrollments.show', $enrollment) }}"><i class="fas fa-eye me-2" aria-hidden="true"></i>View / transfer / withdraw</a></li>
                            <li><a class="dropdown-item" href="{{ route('fees.student', $enrollment->student) }}"><i class="fas fa-money-bill me-2" aria-hidden="true"></i>Student fees</a></li>
                            <li><a class="dropdown-item" href="{{ route('students.show', $enrollment->student) }}"><i class="fas fa-user me-2" aria-hidden="true"></i>Student profile</a></li>
                        </x-list.row-menu>
                    </td>
                </tr>
            @empty
                <x-list.empty colspan="7" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$enrollments" label="enrollments" />
</div>
@endsection
