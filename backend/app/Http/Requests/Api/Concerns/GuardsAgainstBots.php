<?php

namespace App\Http\Requests\Api\Concerns;

use Illuminate\Validation\Validator;

/**
 * Anti-spam for public forms (DIQ-404/603): a hidden "website" field people
 * never see (must stay empty) and the time the form was rendered, in ms since
 * epoch (humans take longer than MIN_FILL_MS to fill a form in).
 */
trait GuardsAgainstBots
{
    protected function botRules(): array
    {
        return [
            'website' => ['prohibited'],
            'formStartedAt' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $elapsed = (int) (microtime(true) * 1000) - (int) $this->input('formStartedAt');

                if ($validator->errors()->isEmpty() && $elapsed < 3000) {
                    $validator->errors()->add('formStartedAt', 'Please take a moment to fill in the form and try again.');
                }
            },
        ];
    }
}
