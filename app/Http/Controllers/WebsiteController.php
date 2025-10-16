<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWebsiteRequest;
use App\Models\Website;
use App\Models\PhpVersion;
use App\Services\Websites\CreateWebsiteService;
use App\Services\Websites\DeleteWebsiteService;
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
        (new CreateWebsiteService($request->validated(), auth()->user()))->handle();

        session()->flash('success', 'Website created successfully.');

        return redirect()->route('websites.index');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $website = Website::findOrFail($id);

        Gate::authorize('update', $website);

        $validated = $request->validate([
            'php_version_id' => ['required', 'integer', 'exists:php_versions,id'],
        ]);

        // ensure selected PHP version is active
        $phpVersion = PhpVersion::active()->findOrFail($validated['php_version_id']);

        $website->update([
            'php_version_id' => $phpVersion->id,
        ]);

        session()->flash('success', 'Website updated successfully.');

        return redirect()->route('websites.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Website $website)
    {
        Gate::authorize('delete', $website);

        (new DeleteWebsiteService($website, auth()->user()))->handle();

        session()->flash('success', 'Website deleted successfully.');

        return redirect()->route('websites.index');
    }
}
