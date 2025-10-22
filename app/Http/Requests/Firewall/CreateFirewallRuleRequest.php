<?php

namespace App\Http\Requests\Firewall;

use Illuminate\Foundation\Http\FormRequest;

class CreateFirewallRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:allow,deny'],
            'direction' => ['required', 'string', 'in:in,out'],
            'protocol' => ['required', 'string', 'in:tcp,udp'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ip' => ['required', 'string'],
            'to' => ['required', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $ip = strtolower(trim($this->input('ip')));
            $to = strtolower(trim($this->input('to')));

            $isAny = fn(string $v) => $v === 'any';
            $isIp = fn(string $v) => filter_var($v, FILTER_VALIDATE_IP) !== false;
            $isCidr = fn(string $v) => (bool) preg_match('/^((25[0-5]|2[0-4]\\d|1?\\d?\\d)(\\.(25[0-5]|2[0-4]\\d|1?\\d?\\d)){3})\\/(3[0-2]|[12]?\\d)$/', $v);

            if (!($isAny($ip) || $isIp($ip) || $isCidr($ip))) {
                $validator->errors()->add('ip', 'IP must be "any", a valid IP address, or CIDR range.');
            }
            if (!($isAny($to) || $isIp($to))) {
                $validator->errors()->add('to', 'To must be "any" or a valid IP address.');
            }
        });
    }
}
