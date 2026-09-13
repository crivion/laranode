<?php

namespace App\Http\Controllers;

use App\Models\PhpVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class PHPManagerController extends Controller
{
    private function isVersionInstalled(string $version): bool
    {
        $scriptPath = base_path('laranode-scripts/bin/laranode-php-list.sh');
        $result = Process::run(['sudo', 'bash', $scriptPath]);
        $installed = json_decode($result->output(), true) ?? [];

        return collect($installed)->contains('version', $version);
    }

    public function getVersions(): JsonResponse
    {
        $versions = PhpVersion::active()->get();

        return response()->json($versions);
    }

    /**
     * Render the PHP management page
     */
    public function index()
    {
        return inertia('PHP/Index');
    }

    /**
     * List all installed PHP versions with their systemctl status
     */
    public function list(): JsonResponse
    {
        $scriptPath = base_path('laranode-scripts/bin/laranode-php-list.sh');
        $output = shell_exec("sudo bash {$scriptPath}");

        $phpVersions = json_decode($output, true) ?? [];

        return response()->json($phpVersions);
    }

    /**
     * Install a new PHP version
     */
    public function install(Request $request): JsonResponse
    {
        $request->validate([
            'version' => 'required|string|regex:/^\d+\.\d+$/',
        ]);

        $version = $request->input('version');

        if ($this->isVersionInstalled($version)) {
            return response()->json([
                'success' => false,
                'message' => "PHP {$version} is already installed",
            ], 409);
        }

        $scriptPath = base_path('laranode-scripts/bin/laranode-php-install.sh');

        // Execute installation script
        $result = Process::run(['sudo', 'bash', $scriptPath, $version]);
        $output = $result->output();

        // Check if installation was successful
        if (strpos($output, 'installed successfully') !== false) {
            return response()->json([
                'success' => true,
                'message' => "PHP {$version} installed successfully",
                'output' => $output,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to install PHP {$version}",
            'output' => $output,
        ], 500);
    }

    /**
     * Uninstall a PHP version
     */
    public function uninstall(Request $request): JsonResponse
    {
        $request->validate([
            'version' => 'required|string|regex:/^\d+\.\d+$/',
        ]);

        $version = $request->input('version');
        $scriptPath = base_path('laranode-scripts/bin/laranode-php-uninstall.sh');

        // Execute uninstallation script
        $output = shell_exec("sudo bash {$scriptPath} {$version} 2>&1");

        // Check if uninstallation was successful
        if (strpos($output, 'uninstalled successfully') !== false) {
            return response()->json([
                'success' => true,
                'message' => "PHP {$version} uninstalled successfully",
                'output' => $output,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to uninstall PHP {$version}",
            'output' => $output,
        ], 500);
    }

    /**
     * Toggle PHP-FPM service (enable/disable)
     */
    public function toggleService(Request $request): JsonResponse
    {
        $request->validate([
            'version' => 'required|string|regex:/^\d+\.\d+$/',
            'enabled' => 'required|boolean',
        ]);

        $version = $request->input('version');
        $enabled = $request->input('enabled');
        $action = $enabled ? 'enable' : 'disable';

        $scriptPath = base_path('laranode-scripts/bin/laranode-php-service.sh');

        // Execute service management script
        $output = shell_exec("sudo bash {$scriptPath} {$action} {$version} 2>&1");

        // Check if action was successful
        if (strpos($output, 'completed successfully') !== false) {
            return response()->json([
                'success' => true,
                'message' => "PHP {$version}-FPM service {$action}d successfully",
                'output' => $output,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to {$action} PHP {$version}-FPM service",
            'output' => $output,
        ], 500);
    }

    /**
     * Restart PHP-FPM service
     */
    public function restartService(Request $request): JsonResponse
    {
        $request->validate([
            'version' => 'required|string|regex:/^\d+\.\d+$/',
        ]);

        $version = $request->input('version');
        $scriptPath = base_path('laranode-scripts/bin/laranode-php-service.sh');

        // Execute restart script
        $output = shell_exec("sudo bash {$scriptPath} restart {$version} 2>&1");

        // Check if restart was successful
        if (strpos($output, 'completed successfully') !== false) {
            return response()->json([
                'success' => true,
                'message' => "PHP {$version}-FPM service restarted successfully",
                'output' => $output,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to restart PHP {$version}-FPM service",
            'output' => $output,
        ], 500);
    }
}
