<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\GuardsAgainstBots;
use App\Support\TextSanitizer;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends BaseFormRequest
{
    use GuardsAgainstBots;

    /** BSEC-03: visitor text is rendered into branded messages, so strip links and control characters first. */
    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['name' => 100, 'vehicleType' => 50, 'area' => 100, 'preferredTiming' => 100] as $key => $max) {
            if (is_string($this->input($key))) {
                $clean[$key] = TextSanitizer::plain($this->input($key), $max);
            }
        }
        if (is_string($this->input('message'))) {
            $clean['message'] = TextSanitizer::clean($this->input('message'), 2000);
        }
        $this->merge($clean);
    }

    public function rules(): array
    {
        return [
            // Only live listings take enquiries and reviews (DIQ-1101).
            'schoolId' => ['required', 'integer', Rule::exists('schools', 'id')->where('listing_status', 'published')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-.\s]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
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
            // BSEC-03: this is only the visitor's stated intent. Nothing is sent to
            // the number on this basis until it is verified (no OTP provider yet).
            $data['whatsapp_opt_in_at'] = now();
            $data['whatsapp_opt_in_ip'] = $this->ip();
        }
        // New public leads always start as pending; only the school moves them on.
        $data['status'] = 'pending';

        return $data;
    }
}
