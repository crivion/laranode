<?php

namespace App\Actions\Firewall;

/**
 * Pure helpers that decide whether the staged UFW rules are safe enough to
 * enable the firewall without locking the operator out.
 *
 * "Safe" requires BOTH:
 *   - an allow rule covering SSH (port 22) — else remote access is lost, and
 *   - an allow rule covering the panel/websites (port 80, 443, or the panel's
 *     own HTTP port) — else the operator loses access to this control panel.
 */
class FirewallSafety
{
    /**
     * The HTTP port the panel itself is served on, derived from APP_URL.
     * Apache serves the panel on :80 by default; honour an explicit port.
     */
    public static function panelHttpPort(): int
    {
        $url = (string) config('app.url');
        $port = parse_url($url, PHP_URL_PORT);
        if (is_int($port) && $port > 0) {
            return $port;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80;
    }

    /**
     * Extract the set of numeric ports referenced by `ufw show added` lines.
     *
     * @param  array<int, string>  $lines
     * @return array<int, int>
     */
    public static function coveredPorts(array $lines): array
    {
        $ports = [];

        foreach ($lines as $line) {
            // "22/tcp", "443/udp"
            if (preg_match_all('/(?:^|\s)(\d{1,5})\/(?:tcp|udp)\b/i', $line, $m)) {
                foreach ($m[1] as $p) {
                    $ports[] = (int) $p;
                }
            }
            // "to any port 22", "port 8443"
            if (preg_match_all('/\bport\s+(\d{1,5})\b/i', $line, $m)) {
                foreach ($m[1] as $p) {
                    $ports[] = (int) $p;
                }
            }
            // bare "allow 80"
            if (preg_match('/\ballow\s+(\d{1,5})(?:\s|$)/i', $line, $m)) {
                $ports[] = (int) $m[1];
            }
        }

        return array_values(array_unique($ports));
    }

    /**
     * @param  array<int, string>  $lines
     */
    public static function coversSsh(array $lines): bool
    {
        if (in_array(22, self::coveredPorts($lines), true)) {
            return true;
        }

        // UFW application profiles that open SSH
        foreach ($lines as $line) {
            if (preg_match('/\b(openssh|ssh)\b/i', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $lines
     */
    public static function coversWeb(array $lines, int $panelPort): bool
    {
        $ports = self::coveredPorts($lines);
        if (array_intersect([80, 443, $panelPort], $ports)) {
            return true;
        }

        // UFW application profiles that open HTTP/HTTPS
        foreach ($lines as $line) {
            if (preg_match('/\b(apache|www|nginx)\b/i', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Human-readable list of what is missing, or [] when safe to enable.
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    public static function missingProtections(array $lines, int $panelPort): array
    {
        $missing = [];

        if (! self::coversSsh($lines)) {
            $missing[] = 'SSH (port 22) — you would lose remote access to the server';
        }

        if (! self::coversWeb($lines, $panelPort)) {
            $missing[] = "the panel/websites (port {$panelPort}, 80 or 443) — you would lose access to this control panel";
        }

        return $missing;
    }
}
