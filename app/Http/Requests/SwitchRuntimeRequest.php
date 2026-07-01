<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchRuntimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Ownership is gated by Gate::authorize in the controller.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'runtime' => ['required', 'string', Rule::in(['php-fpm', 'frankenphp'])],
        ];
    }
}
