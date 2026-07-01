<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DbServiceRequest extends FormRequest
{
    /**
     * Admin-only: returns false for non-admins, triggering a 403 before rules() runs.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validate engine against configured keys and action against a closed allowlist.
     * Rule::in implicitly rejects leading dashes, control chars, and arbitrary strings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'engine' => ['required', 'string', Rule::in(array_keys(config('laranode.db_engines', [])))],
            'action' => ['required', 'string', Rule::in(['start', 'stop', 'restart'])],
        ];
    }
}
