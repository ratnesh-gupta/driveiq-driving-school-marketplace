<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\GuardsAgainstBots;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends BaseFormRequest
{
    use GuardsAgainstBots;

    public function rules(): array
    {
        return [
            // Only live listings take enquiries and reviews (DIQ-1101).
            'schoolId' => ['required', 'integer', Rule::exists('schools', 'id')->where('listing_status', 'published')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            // DIQ-1002: explicit, unticked-by-default opt-in to WhatsApp updates.
            'whatsappOptIn' => ['nullable', 'boolean'],
            ...$this->botRules(),
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
        ]);

        $data['channel'] ??= 'form';
        if ($this->boolean('whatsappOptIn')) {
            // Evidence of consent for someone who may have no account.
            $data['whatsapp_opt_in_at'] = now();
            $data['whatsapp_opt_in_ip'] = $this->ip();
        }
        // New public leads always start as pending; only the school moves them on.
        $data['status'] = 'pending';

        return $data;
    }
}
