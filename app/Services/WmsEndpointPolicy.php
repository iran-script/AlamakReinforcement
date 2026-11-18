<?php

namespace App\Services;

use App\Models\WmsEndpointAllowlist;
use Throwable;

class WmsEndpointPolicy
{
    public function approved(array $parts, string $host): ?WmsEndpointAllowlist
    {
        try {
            $endpoint = WmsEndpointAllowlist::query()->where('status', 'approved')
                ->where('scheme', strtolower($parts['scheme'] ?? ''))
                ->where('host', $host)->where('port', (int) ($parts['port'] ?? 0))->get()
                ->first(fn (WmsEndpointAllowlist $candidate): bool => str_starts_with(rtrim($parts['path'] ?? '', '/').'/', rtrim($candidate->path_prefix, '/').'/'));
            if (! $endpoint) return null;
            $currentIps = $this->resolve($host);
            $approvedIps = array_values(array_unique(array_map('strval', $endpoint->resolved_ips ?: [])));
            sort($currentIps); sort($approvedIps);
            return $currentIps !== $approvedIps ? null : $endpoint;
        } catch (Throwable) {
            return null;
        }
    }

    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];
        return array_values(array_unique(gethostbynamel($host) ?: []));
    }

    public function requestOptions(string $url): array
    {
        $parts = parse_url($url); $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        $endpoint = $this->approved($parts ?: [], $host);
        if (! $endpoint || filter_var($host, FILTER_VALIDATE_IP)) return [];
        $port = (int) $endpoint->port;
        return ['resolve' => array_map(fn (string $ip): string => $host.':'.$port.':'.$ip, $endpoint->resolved_ips ?: [])];
    }
}
