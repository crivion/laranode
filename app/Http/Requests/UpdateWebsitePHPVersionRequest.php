<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsitePHPVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via Gate
        return true;
    }

    public function rules(): array
    {
        return [
            'php_version_id' => ['required', 'integer', 'exists:php_versions,id'],
        ];
    }
}


