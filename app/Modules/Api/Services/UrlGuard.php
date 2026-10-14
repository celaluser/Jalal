<?php

namespace App\Modules\Api\Services;

/**
 * Keeps webhooks from being aimed at the server's own network: https only, no credentials in the address, and the host must
 * resolve to public addresses only (no loopback, private, link-local or reserved ranges).
 */
class UrlGuard
{
    public function allowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && ! in_array($parts['port'], [443, 8443], true))) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The address a delivery must connect to: the first public IP the host resolves to, checked here. Passing it to cURL (CURLOPT_RESOLVE)
     * means the connection cannot be sent somewhere else by a second, different DNS answer between this check and the request.
     * Null for a host given as an IP literal (nothing to pin) or one that does not resolve.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    public function pin(string $url): ?array
    {
        $parts = parse_url($url);
        $host = trim($parts['host'] ?? '', '[]');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || ! $this->allowed($url)) {
            return null;
        }

        $ip = $this->resolve($host)[0] ?? null;

        return $ip ? ['host' => $host, 'port' => (int) ($parts['port'] ?? 443), 'ip' => $ip] : null;
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
    }
}
