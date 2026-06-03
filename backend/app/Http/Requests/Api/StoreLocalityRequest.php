<?php

namespace App\Http\Requests\Api;

class StoreLocalityRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:localities,slug'],
            'description' => ['nullable', 'string'],
        ];
    }
}
