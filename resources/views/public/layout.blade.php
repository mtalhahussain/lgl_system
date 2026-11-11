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
    <style>
        :root { --lgl-black:#111; --lgl-red:#c8102e; --lgl-gold:#d4af37; }
        body { background:#f4f4f5; color:var(--lgl-black); min-height:100vh; }
        .apply-top { background:var(--lgl-black); color:#fff; }
        .apply-top .brand { display:flex; align-items:center; gap:.6rem; font-weight:700; letter-spacing:.02em; }
        .apply-top .brand img, .apply-top .brand .brand-logo { height:34px; width:34px; object-fit:contain; border-radius:6px; background:#fff; color:var(--lgl-red); display:inline-flex; align-items:center; justify-content:center; font-weight:800; }
        .apply-flag { width:34px; height:6px; border-radius:3px; background:linear-gradient(90deg,var(--lgl-black) 0 33%,var(--lgl-red) 33% 66%,var(--lgl-gold) 66% 100%); }
        .apply-card { max-width:640px; margin:2rem auto; }
        .apply-card .card { border:none; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
        .apply-card .card-header { background:#fff; border-bottom:3px solid var(--lgl-gold); border-radius:16px 16px 0 0; padding:1.4rem 1.6rem .9rem; }
        .apply-card .card-header h1 { font-size:1.4rem; font-weight:800; margin:0; }
        .apply-card .card-body { padding:1.4rem 1.6rem; }
        .btn-lgl { background:var(--lgl-red); border-color:var(--lgl-red); color:#fff; font-weight:600; }
        .btn-lgl:hover { background:#a50d25; border-color:#a50d25; color:#fff; }
        .req { color:var(--lgl-red); }
        .apply-foot { color:#6b7280; font-size:.85rem; text-align:center; padding:1rem; }
    </style>
</head>
<body>
    <header class="apply-top py-2">
        <div class="container d-flex align-items-center justify-content-between">
            <span class="brand">
                @if($instituteLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($instituteLogo) }}" alt="{{ $instituteName }} logo">
                @else
                    <span class="brand-logo">LGL</span>
                @endif
                {{ $instituteName }}
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
