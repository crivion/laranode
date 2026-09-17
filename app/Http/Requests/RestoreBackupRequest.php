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
        return ['confirm' => ['required', 'string']];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $backup = $this->route('backup');
            if ($this->input('confirm') !== $backup->confirmationName()) {
                $validator->errors()->add('confirm', 'The confirmation text does not match.');
            }
            if (! in_array($backup->status, ['completed', 'failed'], true) || ! filled($backup->manifest)) {
                $validator->errors()->add('confirm', 'This backup does not contain restorable data.');
            }
            if ($backup->status === 'failed' && $backup->restoreAttemptsRemaining() === 0) {
                $validator->errors()->add('confirm', 'This backup has reached the limit of three restore attempts.');
            }
        });
    }
}
