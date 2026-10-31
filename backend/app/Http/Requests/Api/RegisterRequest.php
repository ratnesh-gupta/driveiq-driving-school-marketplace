<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        // BSEC-23: sign-up cannot hide that an address is taken, but it does not shout it.
        return ['email.unique' => 'We could not create an account with these details. If you already have an account, sign in or reset your password.'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', Password::min(8)->mixedCase()->numbers()],
            // Self-registration: school owners and learners only. Admins are
            // created via `php artisan driveiq:create-admin`; managers and
            // instructors are added by their school.
            'role' => ['required', 'in:school,learner'],
            // A "school" registrant runs a school or works as an independent trainer.
            'listingType' => ['nullable', 'in:school,trainer'],
            'womenInstructor' => ['sometimes', 'boolean'],
            // First-visit marketing tags (DIQ-1108).
            'attribution' => ['sometimes', 'array:utm_source,utm_medium,utm_campaign'],
            'attribution.*' => ['nullable', 'string', 'max:100'],
        ];
    }
}
