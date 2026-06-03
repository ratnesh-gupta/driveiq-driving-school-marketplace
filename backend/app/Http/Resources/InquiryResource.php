<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schoolId' => $this->school_id,
            'schoolName' => $this->whenLoaded('school', fn () => $this->school->name),
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'vehicleType' => $this->vehicle_type,
            'area' => $this->area,
            'preferredTiming' => $this->preferred_timing,
            'channel' => $this->channel,
            'message' => $this->message,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
