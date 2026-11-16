<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        // Deactivated users and users of an archived department are refused like a wrong password
        $allowed = $credentials + [
            'is_active' => true,
            fn ($query) => $query->whereDoesntHave('department', fn ($d) => $d->whereNotNull('archived_at')),
        ];

        if (Auth::attempt($allowed, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function showSettings(): View
    {
        return view('settings');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $firstTime = !$user->password_changed_at;
        $user->update(['password' => Hash::make($validated['password']), 'password_changed_at' => now()]);

        return $firstTime && $user->hasRole('student')
            ? redirect()->route('dashboard')->with('status', 'Password changed. Welcome to ClearTrack!')
            : back()->with('status', 'Password changed.');
    }
}
