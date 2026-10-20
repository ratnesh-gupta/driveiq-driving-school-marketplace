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
