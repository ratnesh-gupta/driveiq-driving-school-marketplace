<?php

namespace App\Http\Requests\Api;

class UpdateReviewRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'authorName' => ['sometimes', 'string', 'max:255'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'content' => ['sometimes', 'string'],
            'approved' => ['sometimes', 'boolean'],
        ];
    }

    public function toSnakeCase(): array
    {
        return $this->camelToSnake($this->validated(), [
            'authorName' => 'author_name',
            'rating' => 'rating',
            'content' => 'content',
            'approved' => 'approved',
        ]);
    }
}
