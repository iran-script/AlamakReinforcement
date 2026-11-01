<?php

namespace App\Services;

use Illuminate\Support\Str;

class MapServiceUrlValidator
{
    public function error(string $value, bool $tileTemplate = false): ?string
    {
        if ($value === '' || preg_match('/[\x00-\x20\\\\]/', $value)) return 'آدرس خالی یا دارای نویسه نامعتبر است.';
        $parts = parse_url($value);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return 'اطلاعات ورود، Query، Fragment یا ساختار نامعتبر در URL مجاز نیست.';
        }

        $path = $parts['path'] ?? '';
        if ($tileTemplate && (! str_contains($path, '{z}') || ! str_contains($path, '{x}') || ! str_contains($path, '{y}'))) {
            return 'آدرس Tile باید شامل {z}، {x} و {y} باشد.';
        }
        if (preg_match('#(?:^|/)\.\.(?:/|$)#', rawurldecode($path))) return 'مسیر نسبی خارج از پوشه سرویس مجاز نیست.';

        $isInternalPath = str_starts_with($value, '/') && ! str_starts_with($value, '//')
            && ! isset($parts['host']) && ! isset($parts['scheme']);
        if ($isInternalPath) return null;

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $normalizedForValidation = strtr($value, ['{z}' => '0', '{x}' => '0', '{y}' => '0']);
        if (! in_array($scheme, ['https', 'http'], true) || $host === '' || filter_var($normalizedForValidation, FILTER_VALIDATE_URL) === false) {
            return 'فقط URL کامل HTTP/HTTPS یا مسیر داخلی تک‌اسلش پذیرفته می‌شود.';
        }
        if ($scheme === 'http' && config('app.env') === 'production') return 'در Production فقط HTTPS یا مسیر داخلی مجاز است.';

        if (config('app.env') === 'production' && ! $this->isApprovedInternalHost($host, isset($parts['port']) ? (int) $parts['port'] : null)) {
            return 'سرویس عمومی در Production مجاز نیست؛ میزبان باید در سیاست شبکه داخلی سرور ثبت شده باشد.';
        }

        return null;
    }

    private function isApprovedInternalHost(string $host, ?int $port): bool
    {
        if ($port !== null && $port !== 443) return false;
        $allowlist = array_map(static fn ($entry): string => strtolower(rtrim(trim($entry), '.')), config('gis.allowed_internal_hosts', []));
        return in_array($host, $allowlist, true);
    }
}
