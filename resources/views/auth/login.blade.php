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
    <title>Sign in — {{ $instituteName }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-story" aria-label="Welcome">
            <div class="login-story-main">
                <div class="login-wordmark">
                    @if($instituteLogo)
                        <img class="login-uploaded-logo" src="{{ asset('storage/'.$instituteLogo) }}" alt="{{ $instituteName }} logo">
                    @else
                        <span class="login-logo">
                            <span aria-hidden="true">LGL</span>
                        </span>
                        <span>{{ $instituteName }}<small>INSTITUTE PORTAL</small></span>
                    @endif
                </div>
                <div class="login-story-content">
                    <span class="login-kicker"><span class="login-flag" aria-hidden="true"></span> YOUR NEXT CHAPTER STARTS HERE</span>
                    <h1>Learn German. <em>Stay connected.</em></h1>
                    <p>From your next class to your latest results, keep your schedule, attendance and learning progress together in one place.</p>
                    <ul class="login-features">
                        <li>
                            <span class="login-feature-icon"><i class="fas fa-calendar-days" aria-hidden="true"></i></span>
                            <span><strong>Class schedule</strong><small>Your next classes, days and timings at a glance.</small></span>
                        </li>
                        <li>
                            <span class="login-feature-icon"><i class="fas fa-user-check" aria-hidden="true"></i></span>
                            <span><strong>Attendance</strong><small>Every session you attend, recorded in one place.</small></span>
                        </li>
                        <li>
                            <span class="login-feature-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
                            <span><strong>Results &amp; progress</strong><small>Exam results and your level, kept up to date.</small></span>
                        </li>
                    </ul>
                    <div class="login-learning-card">
                        <div class="login-learning-head">
                            <span class="login-greeting" lang="de">Guten Tag.</span>
                            <span>A little German. A little closer to your goals.</span>
                        </div>
                        <ol class="login-level-path" aria-label="German levels from A1 to C2">
                            <li>A1</li><li>A2</li><li>B1</li><li>B2</li><li>C1</li><li>C2</li>
                        </ol>
                    </div>
                </div>
            </div>
            <footer class="login-story-footer">
                <span><span class="login-flag" aria-hidden="true"></span> Language connects. Learning opens doors.</span>
                <span class="login-story-roles">Admin · Teacher · Student</span>
            </footer>
        </section>
        <section class="login-form-side">
            <div class="login-form">
                <div class="eyebrow">WELCOME TO YOUR PORTAL</div>
                <h2>Welcome back</h2>
                <p class="page-subtitle">Sign in to continue your learning journey.</p>
                @if ($errors->any())
                    <div role="alert" id="login-error" class="login-error">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="login-field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="username" placeholder="you@example.com" value="{{ old('email') }}" required @error('email') aria-invalid="true" aria-describedby="login-error" @enderror>
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required @error('password') aria-invalid="true" @enderror>
                            <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" id="passwordToggle"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <label class="login-remember" for="remember"><input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>Remember me on this device</label>
                    <button type="submit" class="btn btn-primary login-submit">Sign in <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                </form>
                <p class="login-help">Need help signing in?<br><span>Contact your institute administrator.</span></p>
            </div>
        </section>
    </main>
    <template data-page-module="auth-login"></template>
</body>
</html>
