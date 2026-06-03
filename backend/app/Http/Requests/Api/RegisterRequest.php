<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'role' => ['sometimes', 'in:user,school,admin'],
        ];
    }
}
