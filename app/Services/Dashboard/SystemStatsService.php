<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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
        /* $mysqlStatus = Process::run('systemctl status mysql')->output(); */

        $mysqlStatus = Process::pipe([
            'systemctl status mysql',
            "awk '/PID:/ {pid=$3} /Memory:/ {mem=$2} /CPU:/ {cpu=$2\" \"$3\" \"$4} /Active:/ {split($0,a,\";\"); active=a[2]} END {print pid,\"|\",mem,\"|\",cpu,\"|\",active}'"
        ])->output();

        $mysqlStatus = explode("|", $mysqlStatus);

        $mysqlStatus = array_map(function ($item) {
            return trim($item);
        },  $mysqlStatus);

        return [
            'pid' => $mysqlStatus[0],
            'memory' => $mysqlStatus[1],
            'cpuTime' => $mysqlStatus[2],
            'uptime' => $mysqlStatus[3]
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

            $status = Process::pipe([
                'systemctl status ' . $service,
                "awk '/PID:/ {pid=$3} /Memory:/ {mem=$2} /CPU:/ {cpu=$2\" \"$3\" \"$4} /Active:/ {split($0,a,\";\"); active=a[2]} END {print pid,\"|\",mem,\"|\",cpu,\"|\",active}'"
            ])->output();

            $status = explode("|", $status);

            $status = array_map(function ($item) {
                return trim($item);
            },  $status);

            $phpFpmStatuses[strtoupper(str_ireplace(".service", "", $service))] = [
                'pid' => $status[0],
                'memory' => $status[1],
                'cpuTime' => $status[2],
                'uptime' => $status[3]
            ];
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

    /*
 *  Get Network Stats
 */
    public function getNetworkStats()
    {
        $procFile = File::get('/proc/net/dev');
        $lines = explode("\n", $procFile);

        /*
        cat /proc/net/dev | awk '// {print $1,$2,$10}'                                                                                                      08:59:12
        Inter-| Receive
        face |bytes packets
        lo: 1255453024 1255453024
        enp1s0: 226070613 421595340
        wlo1: 0 0
        */

        $cmd = Process::pipe([
            'cat /proc/net/dev',
            'awk \'// {print $1,$2,$10}\'',
        ]);

        if ($cmd->failed()) {
            return [];
        }

        $output = $cmd->output();

        $lines = explode("\n", $output);
        $lines = array_map('trim', array_filter($lines));
        $lines = array_slice($lines, 1);

        $stats = [];
        // nerd way to do 1024*1024*1024
        $gb = 1 << 30;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(\S+):\s*(\d+)\s+(\d+)/', trim($line), $matches)) {
                $stats[] = [
                    'interface' => rtrim($matches[1], ":"),
                    'rx' => round($matches[2] / $gb, 2),
                    'tx' => round($matches[3] / $gb, 2),
                ];
            }
        }

        return $stats;
    }


    /**
     * Fetch all system stats.
     */
    public function getAllStats(): array
    {
        $stats = [
            /*'whoami'           => $this->getWhoami(),*/
            'cpuStats'         => [
                'usage'        => $this->getCpuUsage(),
                'loadTimes'    => $this->getLoadTimes(),
                'uptime'       => $this->getUptime(),
                'processCount' => $this->getProcessCount(),
            ],
            'diskStats'        => $this->getDiskUsage(),
            'memoryStats'      => $this->getMemoryUsage(),
            /*'nginxStatus'      => $this->getNginxStatus(),*/
            'phpFpm'        => $this->getPhpFpmStatus(),
            /*'sslStatus'        => $this->getSslStatus(),*/
            /*'nginxPort'        => $this->getNginxPort(),*/
            'apache'           => $this->getApacheStatus(),
            'mysql'            => $this->getMysqlStatus(),
            'network'          => $this->getNetworkStats(),
            'domainCount'      => rand(1, 100),
            'userCount'        => $this->getUserCount(),
        ];

        return $stats;
    }
}
