<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - German Language Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="25" r="1" fill="white" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .login-right {
            padding: 60px 40px;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-text {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .flag-icon {
            display: inline-block;
            width: 30px;
            height: 20px;
            background: linear-gradient(to bottom, #000 33%, #ff0000 33%, #ff0000 66%, #ffff00 66%);
            margin-right: 10px;
            border-radius: 3px;
            position: relative;
            z-index: 1;
        }
        
        .features {
            list-style: none;
            padding: 0;
            margin-top: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .features li {
            padding: 8px 0;
            display: flex;
            align-items: center;
        }
        
        .features i {
            margin-right: 10px;
            width: 20px;
        }
        
        .demo-credentials {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }
        
        .demo-credentials h6 {
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .demo-credentials .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 40px 30px;
                text-align: center;
            }
            
            .login-right {
                padding: 40px 30px;
            }
            
            .logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="row g-0">
                <div class="col-lg-6">
                    <div class="login-left">
                        <div>
                            <div class="logo">
                                <span class="flag-icon"></span>
                                German Language Institute
                            </div>
                            <p class="welcome-text">
                                Welcome to our comprehensive ERP system. Access your courses, track progress, manage payments, and connect with your learning community.
                            </p>
                            
                            <ul class="features">
                                <li><i class="fas fa-graduation-cap"></i> Course Management (A1-C2)</li>
                                <li><i class="fas fa-users"></i> Student & Teacher Portal</li>
                                <li><i class="fas fa-calendar-alt"></i> Class Scheduling & Attendance</li>
                                <li><i class="fas fa-money-bill"></i> Fee Management System</li>
                                <li><i class="fas fa-video"></i> Online Classes Support</li>
                                <li><i class="fas fa-chart-bar"></i> Progress Tracking</li>
                            </ul>
                            
                            <div class="demo-credentials">
                                <h6><i class="fas fa-key"></i> Demo Credentials</h6>
                                <div class="credential-item">
                                    <span><strong>Admin:</strong></span>
                                    <span>admin@germanlanguage.de / admin123</span>
                                </div>
                                <div class="credential-item">
                                    <span><strong>Teacher:</strong></span>
                                    <span>hans.mueller@germanlanguage.de / teacher123</span>
                                </div>
                                <div class="credential-item">
                                    <span><strong>Student:</strong></span>
                                    <span>student1@example.com / student123</span>
                                </div>
                                <div class="credential-item">
                                    <span><strong>Accountant:</strong></span>
                                    <span>accounts@germanlanguage.de / accounts123</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="login-right">
                        <h2 class="mb-4 text-center">
                            <i class="fas fa-sign-in-alt me-2" style="color: #667eea;"></i>
                            Login to Your Account
                        </h2>
                        
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                {{ $errors->first() }}
                            </div>
                        @endif
                        
                        <form method="POST" action="{{ route('login.post') }}">
                            @csrf
                            
                            <div class="form-floating">
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       id="email" 
                                       name="email" 
                                       placeholder="name@example.com"
                                       value="{{ old('email') }}"
                                       required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Password"
                                       required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me on this device
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                Secure login powered by Laravel
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill demo credentials on click
        document.querySelectorAll('.credential-item').forEach(item => {
            item.addEventListener('click', function() {
                const credentialText = this.querySelector('span:last-child').textContent;
                const [email, password] = credentialText.split(' / ');
                
                document.getElementById('email').value = email.trim();
                document.getElementById('password').value = password.trim();
                
                // Add visual feedback
                this.style.background = 'rgba(255,255,255,0.2)';
                setTimeout(() => {
                    this.style.background = '';
                }, 300);
            });
        });
        
        // Add loading state to login button
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
            btn.disabled = true;
        });
    </script>
</body>
</html>