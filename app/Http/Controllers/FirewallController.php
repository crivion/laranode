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
            'type' => 'required|string|in:allow,deny',
            'protocol' => 'required|string|in:tcp,udp',
            'port' => 'required|integer|min:1|max:65535',
            'ip' => 'required|string', // validated below for any|ip|cidr
            'to' => 'required|string',
            'comment' => 'nullable|string|max:150',
        ]);

        $from = trim($validated['ip']);
        $to = trim($validated['to']);

        $isAny = fn(string $v) => strtolower($v) === 'any';
        $isIp = fn(string $v) => filter_var($v, FILTER_VALIDATE_IP) !== false;
        $isCidr = fn(string $v) => (bool) preg_match('/^((25[0-5]|2[0-4]\\d|1?\\d?\\d)(\\.(25[0-5]|2[0-4]\\d|1?\\d?\\d)){3})\\/(3[0-2]|[12]?\\d)$/', $v);

        if (!($isAny($from) || $isIp($from) || $isCidr($from))) {
            return back()->withErrors(['ip' => 'IP must be "any", a valid IP address, or CIDR range.'])->withInput();
        }
        if (!($isAny($to) || $isIp($to))) {
            return back()->withErrors(['to' => 'To must be "any" or a valid IP address.'])->withInput();
        }

        $proto = strtolower($validated['protocol']);
        $port = (int) $validated['port'];
        $comment = $validated['comment'] ?? '';
        $comment = trim($comment);
        $commentEscaped = str_replace("'", "\\'", $comment);

        $spec = "proto {$proto} from {$from} to {$to} port {$port}";
        if ($commentEscaped !== '') {
            $spec .= " comment '" . $commentEscaped . "'";
        }

        if ($validated['type'] === 'allow') {
            (new AddUfwRuleAction())->execute($spec);
        } else {
            (new \App\Actions\Firewall\AddUfwDenyRuleAction())->execute($spec);
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
