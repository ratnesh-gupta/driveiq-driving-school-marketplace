<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\GuardsAgainstBots;

class StoreContactMessageRequest extends BaseFormRequest
{
    use GuardsAgainstBots;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            ...$this->botRules(),
        ];
    }
}
