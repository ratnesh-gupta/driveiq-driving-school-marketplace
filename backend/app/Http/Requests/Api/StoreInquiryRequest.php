<?php

namespace App\Http\Requests\Api;

class StoreInquiryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,contacted,converted,closed'],
        ];
    }

    public function toSnakeCase(): array
    {
        $data = $this->camelToSnake($this->validated(), [
            'schoolId' => 'school_id',
            'name' => 'name',
            'phone' => 'phone',
            'email' => 'email',
            'vehicleType' => 'vehicle_type',
            'area' => 'area',
            'preferredTiming' => 'preferred_timing',
            'channel' => 'channel',
            'message' => 'message',
            'status' => 'status',
        ]);

        $data['channel'] ??= 'form';
        $data['status'] ??= 'pending';

        return $data;
    }
}
