@extends('layouts.admin')
@section('title', 'Certificates')
@section('content')
<div>{{ $preselect['name'] ?? '' }} {{ $preselect['level'] ?? '' }}</div>
<form method="post" action="{{ route('certificates.store') }}">@csrf</form>
@endsection
