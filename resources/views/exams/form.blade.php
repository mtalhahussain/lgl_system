@extends('layouts.admin')
@section('title', 'Exam')
@section('content')<form method="post" action="{{ route('exams.store') }}">@csrf</form>@endsection
