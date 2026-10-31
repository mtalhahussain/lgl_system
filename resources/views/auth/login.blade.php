<!DOCTYPE html>
@php($instituteName = \Illuminate\Support\Facades\Schema::hasTable('settings') ? institute_name() : config('app.name', 'LGL Institute'))
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — {{ $instituteName }}</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-story" aria-label="Welcome">
            <div>
                <div class="login-wordmark"><span class="brand-mark"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>{{ $instituteName }}</div>
                <h1>A better place<br>to learn. A simpler<br>way to manage.</h1>
                <p>Your classes, people and progress.<br>One connected institute workspace.</p>
                <div class="login-preview" aria-hidden="true">
                    <div class="tw:flex tw:items-center tw:justify-between"><span style="font-size:12px;color:#bdcbe0">Learning, moving forward</span><i class="fas fa-arrow-trend-up" style="color:#a78bfa"></i></div>
                    <div class="preview-bars"><span style="height:28%"></span><span style="height:42%"></span><span style="height:36%"></span><span style="height:65%"></span><span style="height:55%"></span><span style="height:82%"></span><span style="height:100%"></span></div>
                    <div style="margin-top:16px;font-size:10px;color:#8399bc">A little progress. Every single day.</div>
                </div>
            </div>
            <footer style="margin-top:45px;color:#7e94b3;font-size:11px">Built around your learning community.</footer>
        </section>
        <section class="login-form-side">
            <div class="login-form">
                <div class="eyebrow">YOUR INSTITUTE WORKSPACE</div>
                <h2>Welcome back</h2>
                <p class="page-subtitle" style="line-height:1.7">Enter your details to sign in to your account.</p>
                @if ($errors->any())
                    <div role="alert" style="background:#fff0f2;color:#b4233a;padding:14px;border-radius:8px;margin-top:22px;font-size:13px">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="login-field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="username" placeholder="you@example.com" value="{{ old('email') }}" required @error('email') aria-invalid="true" @enderror>
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required @error('password') aria-invalid="true" @enderror>
                            <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" id="passwordToggle"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <label style="display:flex;align-items:center;gap:9px;font-weight:400;color:#66748b;cursor:pointer" for="remember"><input type="checkbox" name="remember" id="remember" value="1" style="accent-color:#7856ef" @checked(old('remember'))>Remember me on this device</label>
                    <button type="submit" class="btn btn-primary login-submit" style="border:0;color:white;cursor:pointer">Sign in <i class="fas fa-arrow-right" style="margin-left:10px" aria-hidden="true"></i></button>
                </form>
                <p class="login-fine"><i class="fas fa-lock" aria-hidden="true"></i> Your workspace. Securely connected.</p>
                <p class="login-fine" style="margin-top:40px">Need an account? Contact your institute administrator.</p>
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
