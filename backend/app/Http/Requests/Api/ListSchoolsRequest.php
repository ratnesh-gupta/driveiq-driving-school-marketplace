<?php

namespace App\Http\Requests\Api;

class ListSchoolsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'locality' => ['nullable', 'string'],
            'vehicleType' => ['nullable', 'string'],
            'minRating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'hasPickup' => ['nullable', 'boolean'],
            'womenInstructor' => ['nullable', 'boolean'],
            'weekendClasses' => ['nullable', 'boolean'],
            'maxPrice' => ['nullable', 'numeric', 'min:0'],
            'transmission' => ['nullable', 'string'],
            'nearLat' => ['nullable', 'numeric', 'between:-90,90'],
            'nearLng' => ['nullable', 'numeric', 'between:-180,180'],
            'radiusKm' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->query());
    }
}
