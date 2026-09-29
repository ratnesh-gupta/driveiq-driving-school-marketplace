<?php

namespace App\Http\Requests\Api;

use App\Models\Inquiry;
use Illuminate\Validation\Rule;

class UpdateInquiryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['sometimes', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(Inquiry::STATUSES)],
            'lostReason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toSnakeCase(): array
    {
        return $this->camelToSnake($this->validated(), [
            'name' => 'name',
            'phone' => 'phone',
            'email' => 'email',
            'vehicleType' => 'vehicle_type',
            'area' => 'area',
            'preferredTiming' => 'preferred_timing',
            'channel' => 'channel',
            'message' => 'message',
            'status' => 'status',
            'lostReason' => 'lost_reason',
        ]);
    }
}
