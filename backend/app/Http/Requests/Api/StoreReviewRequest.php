<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreReviewRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Only live listings take enquiries and reviews (DIQ-1101).
            'schoolId' => ['required', 'integer', Rule::exists('schools', 'id')->where('listing_status', 'published')],
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
