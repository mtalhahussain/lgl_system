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
            <div>
                <div class="login-wordmark">
                    <span class="login-logo">
                        @if($instituteLogo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($instituteLogo) }}" alt="{{ $instituteName }} logo">
                        @else
                            <span aria-hidden="true">LGL</span>
                        @endif
                    </span>
                    <span>{{ $instituteName }}<small>INSTITUTE PORTAL</small></span>
                </div>
                <div class="login-story-content">
                    <span class="login-kicker"><span class="login-flag" aria-hidden="true"></span> YOUR NEXT CHAPTER STARTS HERE</span>
                    <h1>A new language.<br>A world of<br><em>possibilities.</em></h1>
                    <p>Stay connected to your German learning journey. Your classes, attendance and progress, all in one place.</p>
                    <div class="login-learning-card">
                        <span class="login-greeting" lang="de">Guten Tag.</span>
                        <span>A little German. A little closer to your goals.</span>
                        <div class="login-levels" aria-label="German language learning"><span>Learn</span><span>Practice</span><span>Progress</span></div>
                    </div>
                </div>
            </div>
            <footer class="login-story-footer"><span class="login-flag" aria-hidden="true"></span> Language connects. Learning opens doors.</footer>
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
                <p class="login-fine"><i class="fas fa-lock" aria-hidden="true"></i> Admin · Teacher · Student</p>
                <p class="login-help">Need help signing in?<br><span>Contact your institute administrator.</span></p>
            </div>
        </section>
    </main>
    <script>
        document.getElementById('passwordToggle').addEventListener('click', function () {
            const input = document.getElementById('password');
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            this.setAttribute('aria-pressed', String(visible));
            this.querySelector('i').className = visible ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
        document.querySelector('form').addEventListener('submit', function () {
            const button = this.querySelector('[type=submit]');
            button.disabled = true;
            button.textContent = 'Signing in…';
        });
    </script>
</body>
</html>
