<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DemoSessionController extends Controller
{
    public function store(DemoSeeder $seeder): RedirectResponse
    {
        abort_unless(config('laranode.demo.enabled'), 404);

        $seeder->run();

        $user = User::where('email', config('laranode.demo.email'))->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'success',
            'Welcome to the limited public demo. Actions are safely simulated.'
        );
    }
}
