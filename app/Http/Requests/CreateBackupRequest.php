<?php

namespace App\Http\Requests;

use App\Models\BackupDestination;
use App\Models\Database;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || $user->isAdmin()) {
            return (bool) $user;
        }
        if ($this->filled('user_id') && $this->integer('user_id') !== $user->id) {
            return false;
        }
        if ($this->filled('website_id') && Website::whereKey($this->integer('website_id'))->where('user_id', '!=', $user->id)->exists()) {
            return false;
        }
        if ($this->filled('database_id') && Database::whereKey($this->integer('database_id'))->where('user_id', '!=', $user->id)->exists()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['account', 'website_files', 'website_database'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id', Rule::requiredIf($this->input('scope') === 'website_files')],
            'database_id' => ['nullable', 'integer', 'exists:databases,id', Rule::requiredIf($this->input('scope') === 'website_database')],
            'destination_id' => ['nullable', 'integer', 'exists:backup_destinations,id'],
        ];
    }

    public function targetUser(): User
    {
        return $this->user()->isAdmin() && $this->filled('user_id') ? User::findOrFail($this->integer('user_id')) : $this->user();
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $target = $this->targetUser();
            if (! $this->user()->isAdmin() && $this->filled('user_id') && $this->integer('user_id') !== $this->user()->id) {
                $validator->errors()->add('user_id', 'You cannot back up another account.');
            }
            if ($this->filled('website_id') && ! Website::whereKey($this->integer('website_id'))->where('user_id', $target->id)->exists()) {
                $validator->errors()->add('website_id', 'The selected website does not belong to this account.');
            }
            if ($this->filled('database_id') && ! Database::whereKey($this->integer('database_id'))->where('user_id', $target->id)->exists()) {
                $validator->errors()->add('database_id', 'The selected database does not belong to this account.');
            }
            if ($this->filled('destination_id') && ! BackupDestination::whereKey($this->integer('destination_id'))->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $target->id))->exists()) {
                $validator->errors()->add('destination_id', 'The selected destination is not available to this account.');
            }
        });
    }
}
