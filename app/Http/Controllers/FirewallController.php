<?php

namespace App\Http\Controllers;

use App\Actions\Firewall\AddUfwRuleAction;
use App\Actions\Firewall\DeleteUfwRuleAction;
use App\Actions\Firewall\GetUfwRulesAction;
use App\Actions\Firewall\GetUfwStatusAction;
use App\Actions\Firewall\ToggleUfwAction;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FirewallController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', AdminMiddleware::class]);
    }

    public function index(): \Inertia\Response
    {
        $status = (new GetUfwStatusAction())->execute();
        $rules = (new GetUfwRulesAction())->execute();
        return Inertia::render('Firewall/Index', compact('status', 'rules'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enable = (bool) $validated['enabled'];
        (new ToggleUfwAction())->execute($enable);

        session()->flash('success', 'Firewall ' . ($enable ? 'enabled' : 'disabled') . ' successfully.');
        return redirect()->route('firewall.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule' => 'required|string|min:2',
            'type' => 'required|string|in:allow,deny',
        ]);

        if ($validated['type'] === 'allow') {
            (new AddUfwRuleAction())->execute($validated['rule']);
        } else {
            (new \App\Actions\Firewall\AddUfwDenyRuleAction())->execute($validated['rule']);
        }

        session()->flash('success', 'Rule ' . $validated['type'] . 'ed successfully.');
        return redirect()->route('firewall.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        (new DeleteUfwRuleAction())->execute($id);

        session()->flash('success', 'Rule deleted successfully.');
        return redirect()->route('firewall.index');
    }
}
