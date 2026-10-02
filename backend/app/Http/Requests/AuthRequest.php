<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->routeIs('auth.password')) {
            return ['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'string', 'confirmed', Password::min(8)]];
        }
        $rules = ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string']];
        if ($this->routeIs('auth.register')) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'][] = 'unique:users,email';
            $rules['password'][] = 'confirmed';
            $rules['password'][] = Password::min(8);
        }

        return $rules;
    }
}
