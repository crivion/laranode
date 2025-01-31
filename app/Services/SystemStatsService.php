<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class SystemStatsService
{
    /**
     * Fetch overall CPU usage.
     */
    public function getCpuUsage(): string
    {
        return trim(Process::run('top -bn1 | grep "Cpu(s)" | awk \'{print $2+$4}\'')->output());
    }


    /**
     * Fetch memory usage.
     */
    public function getMemoryUsage(): array
    {
        $memory = Process::pipe([
            'free -m',
            "awk '/Mem:/ {print $4,$3,$6,$2}'"
        ]);

        if ($memory->failed()) {
            return ['error' => $memory->errorOutput()];
        }

        $stats = $memory->output();
        $stats = explode(" ", $stats);

        return [
            'free' => $stats[0],
            'used' => $stats[1],
            'buffcache' => $stats[2],
            'total' => $stats[3]
        ];
    }

    /**
     * Fetch disk usage.
     */
    public function getDiskUsage(): array
    {
        // Fetch disk usage details
        $diskUsage = Process::run('df -h / | awk \'/\\// {print $2, $3, $4, $5}\'')->output();
        $diskUsageParts = preg_split('/\s+/', trim($diskUsage));

        if (count($diskUsageParts) >= 4) {
            return [
                'size' => $diskUsageParts[0],
                'used' => $diskUsageParts[1],
                'free' => $diskUsageParts[2],
                'percent' => $diskUsageParts[3]
            ];
        }

        return [];
    }

    /**
     * Fetch system load times.
     */
    public function getLoadTimes(): string
    {
        return trim(Process::run('uptime | awk -F\'load average:\' \'{print $2}\'')->output());
    }

    /**
     * Fetch system uptime.
     */
    public function getUptime(): string
    {
        return trim(Process::run('uptime -p')->output());
    }

    /**
     * Fetch the number of running processes.
     */
    public function getProcessCount(): string
    {
        return trim(Process::run('ps aux | wc -l')->output());
    }

    /**
     * Fetch the number of logged-in users.
     */
    public function getUserCount(): string
    {
        return trim(Process::run('who | wc -l')->output());
    }

    /**
     * Fetch Apache2 status.
     */
    public function getApacheStatus(): array
    {
        $apacheStatus = Process::run('systemctl status apache2')->output();

        // Regex to extract status and memory
        $pattern = '/Active:\s+(.*?)\n.*?Memory:\s+([\d.]+[KMG]?)/s';
        preg_match($pattern, $apacheStatus, $matches);

        // Extract status and memory
        $status = $matches[1] ?? 'Unknown';
        $memory = $matches[2] ?? 'Unknown';

        return [
            'status' => $status,
            'memory' => $memory
        ];
    }

    /**
     * Fetch Nginx status.
     */
    public function getNginxStatus(): string
    {
        return trim(Process::run('systemctl is-active nginx')->output());
    }

    /**
     * Fetch MySQL Server status.
     */
    public function getMysqlStatus(): array
    {
        $mysqlStatus = Process::run('systemctl status mysql')->output();

        /*dd($mysqlStatus);*/

        // Regex to extract status and memory
        $pattern = '/Active:\s+(.*?)\n.*?Memory:\s+([\d.]+[KMG]?)/s';
        preg_match($pattern, $mysqlStatus, $matches);

        // Extract status and memory
        $status = $matches[1] ?? 'Unknown';
        $memory = $matches[2] ?? 'Unknown';

        return [
            'status' => $status,
            'memory' => $memory
        ];
    }

    /**
     * Fetch PHP-FPM status.
     */
    public function getPhpFpmStatus(): array
    {
        // List all PHP-FPM services
        // This one is time consuming, try from cache first
        // @TBD: User can reset cache on front-end
        $phpFpmServices = Cache::rememberForever('phpFpmServices', function () {

            $output = Process::pipe([
                'systemctl list-unit-files --type=service',
                'grep php.*fpm | awk \'{print $1}\'',
                "awk '{print $1}'"
            ]);

            if ($output->failed()) {
                return ['error' => $output->errorOutput()];
            }

            return array_filter(explode("\n", $output->output()));
        });

        $phpFpmStatuses = [];

        foreach ($phpFpmServices as $service) {

            $status = Process::run('systemctl status ' . $service . ' | grep -E "Active: .*|Memory: .*"');

            if ($status->failed()) {
                $phpFpmStatuses[$service] = ['error' => $status->errorOutput()];
            } else {
                $status = $status->output();
                $status = array_filter(explode("\n", $status));
                $status = array_map('trim', $status);

                // match Memory
                $memory = 'N/A';

                if (isset($status[1])) {
                    $memory = $status[1];
                    preg_match('/Memory:\s([0-9.]+[A-Za-z])/', $memory, $matches);
                    if (isset($matches[1])) {
                        $memory = $matches[1];
                    }
                }

                $phpFpmStatuses[$service] = [
                    'status' => str_replace('Active: ', '', $status[0]),
                    'memory' => $memory
                ];
            }
        }

        return $phpFpmStatuses;
    }
    /**
     * Fetch SSL (Let's Encrypt) status.
     */
    public function getSslStatus(): string
    {
        $sslStatus = Process::run('certbot certificates | grep "VALID"')->output();
        return $sslStatus ? 'Active' : 'Inactive';
    }

    /*
     * Get nginx port
     */
    public function getNginxPort(): string
    {
        $nginxPort = Process::run('netstat -nltp | grep nginx | awk \'{print $4}\'')->output();
        return $nginxPort;
    }

    /*
 * Get whoami
 * */
    public function getWhoami(): string
    {
        $whoami = Process::run('whoami')->output();
        return $whoami;
    }


    /**
     * Fetch all system stats.
     */
    public function getAllStats(): array
    {
        $stats = [
            'whoami' => $this->getWhoami(),
            'cpuStats' => [
                'usage' => $this->getCpuUsage(),
                'loadTimes' => $this->getLoadTimes(),
                'uptime' => $this->getUptime(),
                'processCount' => $this->getProcessCount(),
            ],
            'diskStats' => $this->getDiskUsage(),
            'memoryStats' => $this->getMemoryUsage(),
            'nginxStatus' => $this->getNginxStatus(),
            'phpStatus' => $this->getPhpFpmStatus(),
            'sslStatus' => $this->getSslStatus(),
            'nginxPort' => $this->getNginxPort(),
            'apache' => $this->getApacheStatus(),
            'mysql' => $this->getMysqlStatus(),

            'domainCount' => rand(1, 100),
            'userCount' => $this->getUserCount(),
        ];

        return $stats;
    }
}
