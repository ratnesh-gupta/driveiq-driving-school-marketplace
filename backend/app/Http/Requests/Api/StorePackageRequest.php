<?php

namespace App\Http\Requests\Api;

class StorePackageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'sessions' => ['required', 'integer', 'min:1'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'transmission' => ['required', 'string', 'max:255'],
            'hasPickup' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function toSnakeCase(): array
    {
        $data = $this->camelToSnake($this->validated(), [
            'schoolId' => 'school_id',
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'sessions' => 'sessions',
            'vehicleType' => 'vehicle_type',
            'transmission' => 'transmission',
            'hasPickup' => 'has_pickup',
            'active' => 'active',
        ]);

        $data['has_pickup'] ??= false;
        $data['active'] ??= true;

        return $data;
    }
}
