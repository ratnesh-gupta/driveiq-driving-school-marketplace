<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schoolId' => $this->school_id,
            'schoolName' => $this->whenLoaded('school', fn () => $this->school->name),
            'authorName' => $this->author_name,
            'rating' => (int) $this->rating,
            'content' => $this->content,
            'approved' => (bool) $this->approved,
            'eligibilitySource' => $this->eligibility_source,
            'reportCount' => (int) ($this->report_count ?? 0),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
