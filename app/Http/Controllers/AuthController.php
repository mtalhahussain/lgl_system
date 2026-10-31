<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');
        $throttleKey = 'login:'.hash('sha256', strtolower(trim($request->string('email'))).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (Auth::attemptWhen($credentials, fn ($user) => in_array($user->role, \App\Models\User::ROLES, true), $remember)) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            
            // Check if user is active
            if (!$user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($throttleKey, 60);
                return back()->withErrors([
                    'email' => 'Your account is not active. Please contact administration.',
                ]);
            }

            RateLimiter::clear($throttleKey);

            // Redirect based on user role
            switch ($user->role) {
                case 'admin':
                    return redirect()->intended('/admin/dashboard');
                case 'teacher':
                    return redirect()->intended('/teacher/dashboard');
                case 'student':
                    return redirect()->intended('/student/dashboard');
                default:
                    return redirect()->intended('/dashboard');
            }
        }

        RateLimiter::hit($throttleKey, 60);
        throw ValidationException::withMessages([
            'email' => __('The provided credentials do not match our records.'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
