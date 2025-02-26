<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebsiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (auth()->user()->domain_limit) {
            return auth()->user()->websites->count() < auth()->user()->domain_limit;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $domainRegex = 'regex:/^(?!:\/\/)(?=.{1,255}$)(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)+([a-zA-Z]{2,})$/';

        return [
            'url' => ['required', 'string', 'max:255', 'unique:websites,url', $domainRegex],
            'document_root' => ['required', 'string', 'max:255'],
            'php_version_id' => ['required', 'integer', 'exists:php_versions,id'],
        ];
    }
}
