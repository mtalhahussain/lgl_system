@extends('layouts.admin')
@section('content')
<div class="container">
    <h2>Teacher Details</h2>
    <div class="card mb-3">
        <div class="card-body">
            <h4>{{ $teacher->name }}</h4>
            <p><strong>Email:</strong> {{ $teacher->email }}</p>
            <p><strong>Phone:</strong> {{ $teacher->phone }}</p>
            <p><strong>Employee ID:</strong> {{ $teacher->employee_id }}</p>
            <p><strong>Qualification:</strong> {{ $teacher->qualification }}</p>
            <p><strong>Salary Type:</strong> {{ ucfirst($teacher->salary_type) }}</p>
            <p><strong>Monthly Salary:</strong> {{ number_format($teacher->monthly_salary, 2) }}</p>
            <p><strong>Per Batch Amount:</strong> {{ number_format($teacher->per_batch_amount, 2) }}</p>
            <p><strong>Per Student Amount:</strong> {{ number_format($teacher->per_student_amount, 2) }}</p>
            <p><strong>Status:</strong> {{ $teacher->is_active ? 'Active' : 'Inactive' }}</p>
        </div>
    </div>
    <a href="{{ route('teachers.index') }}" class="btn btn-secondary">Back to Teachers</a>
</div>
@endsection
