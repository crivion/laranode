<?php

namespace App\Http\Controllers;

use App\Actions\Firewall\AddUfwRuleAction;
use App\Actions\Firewall\AddUfwDenyRuleAction;
use App\Actions\Firewall\DeleteUfwRuleAction;
use App\Actions\Firewall\GetUfwRulesAction;
use App\Actions\Firewall\GetUfwStatusAction;
use App\Actions\Firewall\ToggleUfwAction;
use App\Http\Requests\Firewall\ToggleFirewallRequest;
use App\Http\Requests\Firewall\CreateFirewallRuleRequest;
use App\Actions\Firewall\BuildUfwRuleSpecAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FirewallController extends Controller
{
    public function index(): \Inertia\Response
    {
        $status = (new GetUfwStatusAction())->execute();
        $rules = (new GetUfwRulesAction())->execute();
        return Inertia::render('Firewall/Index', compact('status', 'rules'));
    }

    public function toggle(ToggleFirewallRequest $request): RedirectResponse
    {
        $enable = (bool) $request->validated('enabled');
        (new ToggleUfwAction())->execute($enable);

        session()->flash('success', 'Firewall ' . ($enable ? 'enabled' : 'disabled') . ' successfully.');
        return redirect()->route('firewall.index');
    }

    public function store(CreateFirewallRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $spec = (new BuildUfwRuleSpecAction())->execute(
            strtolower($validated['protocol']),
            trim($validated['ip']),
            trim($validated['to']),
            (int) $validated['port'],
            $validated['comment'] ?? ''
        );

        if ($validated['type'] === 'allow') {
            (new AddUfwRuleAction())->execute($spec);
        } else {
            (new AddUfwDenyRuleAction())->execute($spec);
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
