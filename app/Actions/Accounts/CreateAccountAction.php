<?php

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

class CreateAccountAction
{
    public function execute(array $validated): void
    {
        $user = User::create($validated);

        event(new Registered($user));

        if ($validated['notify']) {
            \Illuminate\Support\Facades\Log::info('Would notify ' . $user->email);
        }
    }
}
