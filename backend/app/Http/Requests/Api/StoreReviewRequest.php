<?php

namespace App\Http\Requests\Api;

class StoreReviewRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'authorName' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string'],
            'approved' => ['sometimes', 'boolean'],
        ];
    }

    public function toSnakeCase(): array
    {
        return $this->camelToSnake($this->validated(), [
            'schoolId' => 'school_id',
            'authorName' => 'author_name',
            'rating' => 'rating',
            'content' => 'content',
            'approved' => 'approved',
        ]);
    }
}
