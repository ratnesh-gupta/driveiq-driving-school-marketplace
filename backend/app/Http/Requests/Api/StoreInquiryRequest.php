<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Validator;

class StoreInquiryRequest extends BaseFormRequest
{
    /** Humans take longer than this to fill in the enquiry form (DIQ-404). */
    private const MIN_FILL_MS = 3000;

    public function rules(): array
    {
        return [
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            // Anti-spam: a hidden field people never see (must stay empty) and
            // the time the form was rendered (ms since epoch).
            'website' => ['prohibited'],
            'formStartedAt' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $startedAt = (int) $this->input('formStartedAt');
                $elapsed = (int) (microtime(true) * 1000) - $startedAt;

                if ($validator->errors()->isEmpty() && $elapsed < self::MIN_FILL_MS) {
                    $validator->errors()->add('formStartedAt', 'Please take a moment to fill in the form and try again.');
                }
            },
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
        // New public leads always start as pending; only the school moves them on.
        $data['status'] = 'pending';

        return $data;
    }
}
