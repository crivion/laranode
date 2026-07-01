<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;
        $type = $this->input('type', 'db');

        if ($type === 'files') {
            $targetRule = Rule::exists('websites', 'url')->where('user_id', $userId);
        } else {
            $targetRule = Rule::exists('databases', 'name')->where('user_id', $userId);
        }

        return [
            'type' => ['required', 'string', Rule::in(['db', 'files'])],
            'target' => ['required', 'string', $targetRule],
            'storage' => ['required', 'string', Rule::in(['local', 's3'])],
            's3_key' => ['required_if:storage,s3', 'nullable', 'string'],
            's3_secret' => ['required_if:storage,s3', 'nullable', 'string'],
            's3_region' => ['required_if:storage,s3', 'nullable', 'string'],
            's3_bucket' => ['required_if:storage,s3', 'nullable', 'string'],
            's3_endpoint' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'target.exists' => 'The selected target does not belong to your account.',
        ];
    }
}
