<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBackupDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $driver = $this->input('driver');
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', Rule::in(['local', 's3', 'sftp'])],
            'is_default' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
            'config.key' => [Rule::requiredIf($creating && $driver === 's3'), 'nullable', 'string'],
            'config.secret' => [Rule::requiredIf($creating && $driver === 's3'), 'nullable', 'string'],
            'config.region' => [Rule::requiredIf($creating && $driver === 's3'), 'nullable', 'string'],
            'config.bucket' => [Rule::requiredIf($creating && $driver === 's3'), 'nullable', 'string'],
            'config.endpoint' => ['nullable', 'url'],
            'config.use_path_style_endpoint' => ['nullable', 'boolean'],
            'config.host' => [Rule::requiredIf($creating && $driver === 'sftp'), 'nullable', 'string'],
            'config.port' => ['nullable', 'integer', 'between:1,65535'],
            'config.username' => [Rule::requiredIf($creating && $driver === 'sftp'), 'nullable', 'string'],
            'config.password' => ['nullable', 'string'],
            'config.privateKey' => ['nullable', 'string'],
            'config.root' => ['nullable', 'string'],
        ];
    }
}
