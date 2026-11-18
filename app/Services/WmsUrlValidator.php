<?php

namespace App\Services;

class WmsUrlValidator
{
    public function __construct(private WmsEndpointPolicy $endpoints) {}

    public function error(string $url): ?string
    {
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) return 'آدرس سرویس معتبر نیست.';
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return 'آدرس WMS باید URL کامل HTTP/HTTPS بدون نام کاربری و رمز باشد.';
        if (isset($parts['query']) || isset($parts['fragment'])) return 'آدرس WMS نباید query یا fragment داشته باشد؛ پارامترهای WMS توسط برنامه اضافه می‌شوند.';
        $host = strtolower(rtrim($parts['host'], '.'));
        if ($this->isAllowedLocalGeoServer($parts, $host)) return null;
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === 'metadata.google.internal' || $host === '169.254.169.254') return 'دسترسی به میزبان داخلی مجاز نیست.';

        $ips = $this->endpoints->resolve($host);
        $hasPrivateIp = false;
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) $hasPrivateIp = true;
        }
        if ($hasPrivateIp) {
            $approved = $this->endpoints->approved($parts, $host);
            if (! $approved) return 'این مقصد داخلی هنوز توسط مدیر امنیتی تأیید نشده است.';
        }
        $allowed = array_map(fn ($v) => strtolower(trim($v)), config('gis.allowed_internal_hosts', []));
        if (! $hasPrivateIp && ! in_array($host, $allowed, true) && $ips !== [] && $this->containsNonPublic($ips)) return 'آدرس‌های خصوصی یا داخلی برای WMS مجاز نیستند.';
        return null;
    }

    public function requestOptions(string $url): array { return $this->endpoints->requestOptions($url); }

    public function sameDestination(string $source, string $target): bool
    {
        $a = parse_url($source); $b = parse_url($target);
        if ($a === false || $b === false) return false;
        $port = fn (array $p): int => (int) ($p['port'] ?? (strtolower($p['scheme'] ?? '') === 'https' ? 443 : 80));
        return strtolower($a['scheme'] ?? '') === strtolower($b['scheme'] ?? '') && strtolower(rtrim($a['host'] ?? '', '.')) === strtolower(rtrim($b['host'] ?? '', '.')) && $port($a) === $port($b) && rtrim($a['path'] ?? '/', '/') === rtrim($b['path'] ?? '/', '/');
    }

    private function containsNonPublic(array $ips): bool
    {
        foreach ($ips as $ip) if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
        return false;
    }

    private function isAllowedLocalGeoServer(array $parts, string $host): bool
    {
        $local = config('gis.wms.local_geoserver', []);
        return app()->environment('local') && config('gis.wms.allow_local_geoserver') && strtolower($parts['scheme'] ?? '') === $local['scheme'] && $host === $local['host'] && (int) ($parts['port'] ?? 0) === (int) $local['port'] && rtrim($parts['path'] ?? '', '/') === rtrim($local['path_prefix'], '/');
    }
}
