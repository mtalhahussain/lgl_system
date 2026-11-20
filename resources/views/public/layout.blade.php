<!DOCTYPE html>
@php
    $hasSettings = \Illuminate\Support\Facades\Schema::hasTable('settings');
    $instituteName = $hasSettings ? trim((string) setting('institute_name', '')) : '';
    if ($instituteName === '' || in_array($instituteName, ['Language Institute', 'LGL Institute', 'German Language Learning Institute'])) {
        $instituteName = 'Land of German Language';
    }
    $instituteLogo = $hasSettings ? setting('institute_logo') : null;
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Apply') — {{ $instituteName }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/public-application.css', 'resources/js/public-application.js'])
</head>
<body>
    <header class="apply-top py-2">
        <div class="container d-flex align-items-center justify-content-between">
            <span class="brand">
                @if($instituteLogo)
                    <img class="apply-brand-logo" src="{{ asset('storage/'.$instituteLogo) }}" alt="{{ $instituteName }} logo">
                @else
                    <span class="brand-logo">LGL</span>
                    {{ $instituteName }}
                @endif
            </span>
            <span class="apply-flag" aria-hidden="true"></span>
        </div>
    </header>

    <main class="apply-card">
        @yield('content')
    </main>

    <footer class="apply-foot">{{ $instituteName }} · Language connects. Learning opens doors.</footer>
</body>
</html>
