<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $backup = $this->route('backup');

        return [
            'new_target' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9_]{1,64}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($backup) {
                    if ($backup && $value === $backup->target) {
                        $fail('The new target must differ from the original backup target.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_target.required' => 'A new target name is required.',
            'new_target.regex' => 'The new target name may only contain letters, numbers, and underscores (max 64 characters).',
        ];
    }
}
