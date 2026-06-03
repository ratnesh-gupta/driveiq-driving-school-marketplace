<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['errors' => $validator->errors()], 422)
        );
    }

    protected function camelToSnake(array $data, array $map): array
    {
        $payload = [];
        foreach ($map as $camelKey => $snakeKey) {
            if (array_key_exists($camelKey, $data)) {
                $payload[$snakeKey] = $data[$camelKey];
            }
        }
        return $payload;
    }
}
