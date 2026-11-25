<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $v = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['boolean'],
        ]);
        $key = 'admin-login:'.Str::lower($v['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $s = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Demasiados intentos. Intenta de nuevo en {$s} segundos."]);
        }
        if (! Auth::attempt(['email' => $v['email'], 'password' => $v['password']], (bool) ($v['remember'] ?? false))) {
            RateLimiter::hit($key, 120);
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return response()->json(['name' => $request->user()->name, 'email' => $request->user()->email]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function password(Request $request): JsonResponse
    {
        $v = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        $request->user()->forceFill(['password' => Hash::make($v['password'])])->save();

        return response()->json(['ok' => true]);
    }
}
