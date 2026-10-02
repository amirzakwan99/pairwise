<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(AuthRequest $request)
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user->only(['id', 'name', 'email'])], 201);
    }

    public function login(AuthRequest $request)
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $request->user()->only(['id', 'name', 'email'])]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function password(AuthRequest $request)
    {
        $request->user()->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        return response()->json(['message' => 'Password changed.']);
    }
}
