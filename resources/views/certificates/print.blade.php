<!doctype html>
<html lang="de"><head><meta charset="utf-8"><title>Certificate {{ $cert->certificate_number }}</title></head>
<body>{{ $cert->student_name }} — Niveau {{ $cert->level }} — {{ $cert->completion_date->format('d.m.Y') }}</body></html>
