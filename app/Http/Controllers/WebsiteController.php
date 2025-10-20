<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWebsiteRequest;
use App\Http\Requests\UpdateWebsitePHPVersionRequest;
use App\Models\Website;
use App\Models\PhpVersion;
use App\Services\Websites\CreateWebsiteService;
use App\Services\Websites\DeleteWebsiteService;
use App\Services\Websites\UpdateWebsitePHPVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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

        (new UpdateWebsitePHPVersionService($website, (int) $validated['php_version_id']))->handle();

        session()->flash('success', 'Website updated successfully.');

        return redirect()->route('websites.index');
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

        $request->validate([
            'enabled' => 'required|boolean',
            'email' => 'required_if:enabled,true|email'
        ]);

        try {
            if ($request->enabled) {
                // Generate SSL certificate
                $this->generateSslCertificate($website, $request->email);
            } else {
                // Remove SSL certificate
                $this->removeSslCertificate($website);
            }

            return response()->json([
                'success' => true,
                'message' => $request->enabled ? 'SSL certificate generated successfully' : 'SSL certificate removed successfully',
                'ssl_status' => $website->fresh()->ssl_status,
                'ssl_enabled' => $website->fresh()->ssl_enabled
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ' . ($request->enabled ? 'generate' : 'remove') . ' SSL certificate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate SSL certificate for a website
     */
    private function generateSslCertificate(Website $website, string $email): void
    {
        // Update status to pending
        $website->update([
            'ssl_status' => 'pending',
            'ssl_enabled' => true
        ]);

        // Run SSL generation script
        $scriptPath = base_path('laranode-scripts/bin/laranode-ssl-manager.sh');
        $result = Process::run([
            'bash', $scriptPath, 'generate', 
            $website->url, 
            $email,
            $website->fullDocumentRoot
        ]);

        if ($result->failed()) {
            $website->update([
                'ssl_status' => 'inactive',
                'ssl_enabled' => false
            ]);
            throw new \Exception($result->errorOutput());
        }

        // Check SSL status
        $statusResult = Process::run([
            'bash', $scriptPath, 'status', $website->url
        ]);

        $sslStatus = trim($statusResult->output());
        
        // Update website with SSL information
        $website->update([
            'ssl_status' => $sslStatus === 'active' ? 'active' : 'inactive',
            'ssl_generated_at' => now(),
            'ssl_expires_at' => $sslStatus === 'active' ? now()->addDays(90) : null
        ]);
    }

    /**
     * Remove SSL certificate for a website
     */
    private function removeSslCertificate(Website $website): void
    {
        // Run SSL removal script
        $scriptPath = base_path('laranode-scripts/bin/laranode-ssl-manager.sh');
        $result = Process::run([
            'bash', $scriptPath, 'remove', $website->url
        ]);

        if ($result->failed()) {
            throw new \Exception($result->errorOutput());
        }

        // Update website SSL status
        $website->update([
            'ssl_enabled' => false,
            'ssl_status' => 'inactive',
            'ssl_expires_at' => null,
            'ssl_generated_at' => null
        ]);
    }

    /**
     * Check SSL status for a website
     */
    public function checkSslStatus(Website $website)
    {
        Gate::authorize('view', $website);

        try {
            $scriptPath = base_path('laranode-scripts/bin/laranode-ssl-manager.sh');
            $result = Process::run([
                'bash', $scriptPath, 'status', $website->url
            ]);

            $sslStatus = trim($result->output());
            
            // Update website SSL status
            $website->update([
                'ssl_status' => $sslStatus,
                'ssl_enabled' => $sslStatus === 'active'
            ]);

            return response()->json([
                'success' => true,
                'ssl_status' => $sslStatus,
                'ssl_enabled' => $sslStatus === 'active',
                'status_text' => $website->getSslStatusText()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check SSL status: ' . $e->getMessage()
            ], 500);
        }
    }
}
