<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => 'required|regex:/^[a-zA-Z0-9_-]+$/|string|max:25|unique:' . User::class,
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', 'in:admin,user'],
            'domain_limit' => ['nullable', 'integer', 'min:1'],
            'database_limit' => ['nullable', 'integer', 'min:1'],
            'notify' => ['nullable', 'boolean'],
            'ssh_access' => ['required', 'boolean'],
        ];
    }
}
