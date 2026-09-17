<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CreateBackupScheduleRequest extends CreateBackupRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'run_at' => ['required', 'date_format:H:i'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6', Rule::requiredIf($this->input('frequency') === 'weekly')],
            'day_of_month' => ['nullable', 'integer', 'between:1,31', Rule::requiredIf($this->input('frequency') === 'monthly')],
            'retention_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
