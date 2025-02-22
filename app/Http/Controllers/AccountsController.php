<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\CreateAccountAction;
use App\Http\Requests\CreateAccountRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $accounts = User::all();
        return Inertia::render('Accounts/Index', compact('accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateAccountRequest $request, CreateAccountAction $createAccount)
    {
        $createAccount->execute($request->validated());

        return redirect()->route('accounts.index');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($account)
    {
        User::findOrFail($account)->delete();

        return redirect()->route('accounts.index');
    }

    /**
     * Impersonate a user
     */
    public function impersonate(User $user)
    {
        auth()->user()->impersonate($user);
        return redirect()->route('dashboard');
    }

    /**
     * Leave impersonation
     */
    public function leaveImpersonation()
    {
        auth()->user()->leaveImpersonation();
        return redirect()->route('dashboard');
    }
}
