<?php

namespace App\Http\Requests;

use App\Models\Database;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDatabaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if (!$user) {
            return;
        }

        $prefix = $user->username . '_';

        $name = $this->input('name');
        $nameSuffix = $this->input('name_suffix');
        if (empty($name) && !empty($nameSuffix)) {
            $this->merge([
                'name' => $prefix . $nameSuffix,
            ]);
        }

        $dbUser = $this->input('db_user');
        $dbUserSuffix = $this->input('db_user_suffix');
        if (empty($dbUser) && !empty($dbUserSuffix)) {
            $this->merge([
                'db_user' => $prefix . $dbUserSuffix,
            ]);
        }
    }
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
        $user = $this->user();
        $prefix = $user->username . '_';

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^' . preg_quote($prefix) . '[a-zA-Z0-9_]+$/',
                'unique:' . Database::class . ',name'
            ],
            'db_user' => [
                'required',
                'string',
                'max:32',
                'regex:/^' . preg_quote($prefix) . '[a-zA-Z0-9_]+$/'
            ],
            'db_pass' => ['required', 'string', 'min:8'],
            'charset' => ['required', 'string'],
            'collation' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $user = $this->user();
        $prefix = $user->username . '_';

        return [
            'name.regex' => 'Database name must start with ' . $prefix . ' and contain only letters, numbers, and underscores.',
            'db_user.regex' => 'Database username must start with ' . $prefix . ' and contain only letters, numbers, and underscores.',
            'name.unique' => 'A database with this name already exists.',
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (!$user) {
                return;
            }

            $limit = $user->database_limit;
            if (is_null($limit) || (is_int($limit) && $limit <= 0)) {
                // Null or <= 0 interpreted as unlimited
                return;
            }

            $currentCount = \App\Models\Database::where('user_id', $user->id)->count();
            if ($currentCount >= (int) $limit) {
                $validator->errors()->add('name', 'You have reached your database limit and cannot create more databases.');
            }
        });
    }
}

