<?php

namespace App\Http\Requests\Firewall;

use Illuminate\Foundation\Http\FormRequest;

class ToggleFirewallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
