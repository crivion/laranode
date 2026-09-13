<?php

namespace App\Http\Controllers;

use App\Actions\Firewall\AddUfwDenyRuleAction;
use App\Actions\Firewall\AddUfwRuleAction;
use App\Actions\Firewall\BuildUfwRuleSpecAction;
use App\Actions\Firewall\DeleteUfwRuleAction;
use App\Actions\Firewall\FirewallSafety;
use App\Actions\Firewall\GetStagedUfwRulesAction;
use App\Actions\Firewall\GetUfwRulesAction;
use App\Actions\Firewall\GetUfwStatusAction;
use App\Actions\Firewall\SafeSetupFirewallAction;
use App\Actions\Firewall\ToggleUfwAction;
use App\Http\Requests\Firewall\CreateFirewallRuleRequest;
use App\Http\Requests\Firewall\ToggleFirewallRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FirewallController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $status = (new GetUfwStatusAction)->execute();
        $rules = (new GetUfwRulesAction)->execute();

        $staged = (new GetStagedUfwRulesAction)->execute();
        $panelPort = FirewallSafety::panelHttpPort();
        $safety = [
            'panelPort' => $panelPort,
            'coversSsh' => FirewallSafety::coversSsh($staged),
            'coversWeb' => FirewallSafety::coversWeb($staged, $panelPort),
            'missing' => FirewallSafety::missingProtections($staged, $panelPort),
            'detectedIp' => $request->ip(),
        ];

        return Inertia::render('Firewall/Index', compact('status', 'rules', 'safety'));
    }

    public function toggle(ToggleFirewallRequest $request): RedirectResponse
    {
        $enable = (bool) $request->validated('enabled');

        // Lockout guard: never enable UFW without SSH + panel/web allow rules.
        // Protects direct API calls too, not just the UI.
        if ($enable) {
            $panelPort = FirewallSafety::panelHttpPort();
            $missing = FirewallSafety::missingProtections(
                (new GetStagedUfwRulesAction)->execute(),
                $panelPort
            );

            if (! empty($missing)) {
                session()->flash(
                    'error',
                    'Refusing to enable the firewall — no rule allows '.implode('; ', $missing)
                        .'. Add the missing rule(s) or use Safe Setup first.'
                );

                return redirect()->route('firewall.index');
            }
        }

        (new ToggleUfwAction)->execute($enable);

        session()->flash('success', 'Firewall '.($enable ? 'enabled' : 'disabled').' successfully.');

        return redirect()->route('firewall.index');
    }

    /**
     * Stage a lockout-proof baseline (SSH + HTTP + HTTPS + panel port) and enable UFW.
     */
    public function safeSetup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ssh_from_ip' => ['nullable', 'ip'],
        ]);

        (new SafeSetupFirewallAction)->execute(
            FirewallSafety::panelHttpPort(),
            $validated['ssh_from_ip'] ?? null
        );

        session()->flash('success', 'Firewall enabled with a safe baseline (SSH, HTTP, HTTPS).');

        return redirect()->route('firewall.index');
    }

    public function store(CreateFirewallRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $spec = (new BuildUfwRuleSpecAction)->execute(
            strtolower($validated['direction']),
            strtolower($validated['protocol']),
            trim($validated['ip']),
            trim($validated['to']),
            (int) $validated['port']
        );

        if ($validated['type'] === 'allow') {
            (new AddUfwRuleAction)->execute($spec);
        } else {
            (new AddUfwDenyRuleAction)->execute($spec);
        }

        session()->flash('success', 'Rule '.$validated['type'].'ed successfully.');

        return redirect()->route('firewall.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        (new DeleteUfwRuleAction)->execute($id);

        session()->flash('success', 'Rule deleted successfully.');

        return redirect()->route('firewall.index');
    }
}
