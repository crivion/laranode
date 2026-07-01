<?php

namespace App\Http\Controllers;

use App\Actions\SSL\CheckWebsiteSslStatusAction;
use App\Actions\SSL\RemoveWebsiteSslAction;
use App\Http\Requests\CreateWebsiteRequest;
use App\Http\Requests\SwitchRuntimeRequest;
use App\Http\Requests\UpdateWebsitePHPVersionRequest;
use App\Jobs\SwitchRuntimeOperationJob;
use App\Models\Operation;
use App\Models\Website;
use App\Services\Websites\CreateWebsiteService;
use App\Services\Websites\DeleteWebsiteService;
use App\Services\Websites\UpdateWebsitePHPVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class WebsiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Inertia\Response
    {
        $websites = Website::mine()->with(['user', 'phpVersion'])->orderBy('url')->get();

        try {
            $serverIp = Http::get('https://api.ipify.org')->body();
        } catch (\Exception $exception) {
            $serverIp = 'N/A';
        }

        return Inertia::render('Websites/Index', compact('websites', 'serverIp'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateWebsiteRequest $request)
    {
        $user = $request->user();

        (new CreateWebsiteService($request->validated(), $user))->handle();

        session()->flash('success', 'Website created successfully.');

        return redirect()->route('websites.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWebsitePHPVersionRequest $request, string $id)
    {
        $website = Website::findOrFail($id);

        Gate::authorize('update', $website);

        $validated = $request->validated();

        try {
            (new UpdateWebsitePHPVersionService($website, (int) $validated['php_version_id']))->handle();
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['runtime' => $e->getMessage()]);
        }

        session()->flash('success', 'Website updated successfully.');

        return redirect()->route('websites.index');
    }

    /**
     * Switch the PHP runtime for a website (async via OperationJob).
     */
    public function switchRuntime(SwitchRuntimeRequest $request, Website $website)
    {
        Gate::authorize('update', $website);

        $runtime = $request->validated()['runtime'];

        $operation = Operation::create([
            'user_id' => $request->user()->id,
            'type' => 'runtime.switch',
            'target' => $website->url,
            'status' => 'queued',
        ]);

        SwitchRuntimeOperationJob::dispatch($operation, $website, $runtime);

        return response()->json(['operation_id' => $operation->id]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Website $website)
    {
        Gate::authorize('delete', $website);

        $user = $request->user();

        (new DeleteWebsiteService($website, $user))->handle();

        session()->flash('success', 'Website deleted successfully.');

        return redirect()->route('websites.index');
    }

    /**
     * Toggle SSL certificate for a website
     */
    public function toggleSsl(Request $request, Website $website)
    {
        Gate::authorize('update', $website);

        $request->validate(['enabled' => 'required|boolean']);

        if ($request->enabled) {
            $operation = \App\Models\Operation::create([
                'user_id' => $request->user()->id,
                'type' => 'ssl.generate',
                'target' => $website->url,
                'status' => 'queued',
            ]);

            \App\Jobs\GenerateSslOperationJob::dispatch($operation, $website, $request->user()->email);

            return response()->json(['operation_id' => $operation->id]);
        }

        // Disable path stays synchronous (fast).
        try {
            (new RemoveWebsiteSslAction)->execute($website);
            session()->flash('success', 'SSL certificate removed successfully');

            return redirect()->route('websites.index');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to remove SSL certificate: '.$e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Check SSL status for a website
     */
    public function checkSslStatus(Website $website)
    {
        Gate::authorize('view', $website);

        try {
            $result = (new CheckWebsiteSslStatusAction)->execute($website);

            return response()->json([
                'success' => true,
                'ssl_status' => $result['ssl_status'],
                'ssl_enabled' => $result['ssl_enabled'],
                'status_text' => $website->getSslStatusText(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check SSL status: '.$e->getMessage(),
            ], 500);
        }
    }
}
