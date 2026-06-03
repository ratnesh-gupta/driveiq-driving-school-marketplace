<?php

namespace App\Http\Requests\Api;

class UpdatePackageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'sessions' => ['sometimes', 'integer', 'min:1'],
            'vehicleType' => ['sometimes', 'string', 'max:255'],
            'transmission' => ['sometimes', 'string', 'max:255'],
            'hasPickup' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function toSnakeCase(): array
    {
        return $this->camelToSnake($this->validated(), [
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'sessions' => 'sessions',
            'vehicleType' => 'vehicle_type',
            'transmission' => 'transmission',
            'hasPickup' => 'has_pickup',
            'active' => 'active',
        ]);
    }
}
