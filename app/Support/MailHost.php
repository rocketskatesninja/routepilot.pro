<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether a company's own mail server is somewhere RoutePilot may connect: a
 * mail port, and a host that is (or resolves only to) public addresses. Without
 * this a company could point the server at internal services, and the "send a
 * test email" button would report back what answered.
 */
final class MailHost
{
    /** The ports mail servers listen on. */
    public const PORTS = [25, 465, 587, 2525];

    public static function allowed(string $host, int $port): bool
    {
        $host = trim($host, " \t\n\r\0\x0B[]");
        if ($host === '' || ! in_array($port, self::PORTS, true)) {
            return false;
        }
        if (config('mail.tenant_private_hosts')) {
            return true;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false; // loopback, private, link-local or reserved
            }
        }

        return $ips !== [];
    }

    /** @return list<string> every IPv4 and IPv6 address the name points at */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }
}
