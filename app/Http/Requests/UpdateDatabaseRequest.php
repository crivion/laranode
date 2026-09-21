<?php

namespace App\Http\Requests;

use App\Models\Website;
use App\Rules\SupportedCollation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDatabaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'charset' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/D'],
            'collation' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/D', new SupportedCollation($this->input('charset'))],
            'db_password' => ['nullable', 'string', 'min:8'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('website_id') && ! Website::whereKey($this->integer('website_id'))->where('user_id', $this->user()->id)->exists()) {
                $validator->errors()->add('website_id', 'The selected website does not belong to this account.');
            }
        });
    }
}
