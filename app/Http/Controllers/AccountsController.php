<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAccountRequest;
use App\Models\User;
use App\Services\Accounts\CreateAccountException;
use App\Services\Accounts\CreateAccountService;
use App\Services\Accounts\DeleteAccountService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Inertia\Response
    {
        $accounts = User::all();
        return Inertia::render('Accounts/Index', compact('accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateAccountRequest $request): RedirectResponse
    {
        try {

            (new CreateAccountService($request->validated()))->handle();

            session()->flash('success', 'Account created successfully!');

            return redirect()->route('accounts.index');
        } catch (CreateAccountException $e) {
            session()->flash('error', $e->getMessage());
            return back();
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
            return back();
        }
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
    public function destroy($account): RedirectResponse
    {
        (new DeleteAccountService(User::findOrFail($account)))->handle();

        return redirect()->route('accounts.index');
    }

    /**
     * Impersonate a user
     */
    public function impersonate(User $user): RedirectResponse
    {
        auth()->user()->impersonate($user);
        return redirect()->route('dashboard');
    }

    /**
     * Leave impersonation
     */
    public function leaveImpersonation(): RedirectResponse
    {
        auth()->user()->leaveImpersonation();
        return redirect()->route('dashboard');
    }
}
