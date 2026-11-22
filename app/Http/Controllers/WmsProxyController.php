<?php

namespace App\Http\Controllers;

use App\Exceptions\WmsAccessDeniedException;
use App\Exceptions\WmsConnectionException;
use App\Exceptions\WmsEndpointException;
use App\Exceptions\WmsInvalidResponseException;
use App\Exceptions\WmsRemoteException;
use App\Exceptions\WmsTimeoutException;
use App\Models\WmsLayer;
use App\Models\WmsService;
use App\Services\WmsErrorResponder;
use App\Services\WmsLayerCrsResolver;
use App\Services\WmsUrlValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class WmsProxyController extends Controller
{
    public function __invoke(Request $request, WmsService $wmsService, WmsLayer $wmsLayer, WmsUrlValidator $urls, WmsLayerCrsResolver $crsResolver, WmsErrorResponder $errors)
    {
        abort_unless($request->user()?->can('map.view'), 403);
        abort_unless((int) $wmsLayer->wms_service_id === (int) $wmsService->id && $wmsService->is_active && $wmsLayer->is_enabled, 404);
        abort_unless($wmsService->organization_id === null || (int) $wmsService->organization_id === (int) $request->user()->organization_id, 403);

        try {
            if ($urls->error($wmsService->url)) throw new WmsEndpointException();

            $resolved = $crsResolver->forService($wmsService);
            $crs = $crsResolver->preferred($crsResolver->forLayer($wmsLayer, $resolved));
            if ($crs === null) $this->invalidRequest('این لایه از EPSG:3857 یا EPSG:4326 پشتیبانی نمی‌کند.');

            $width = filter_var($request->query('WIDTH', 256), FILTER_VALIDATE_INT);
            $height = filter_var($request->query('HEIGHT', 256), FILTER_VALIDATE_INT);
            if ($width === false || $width < 64 || $width > 2048) $this->invalidRequest('عرض تصویر WMS نامعتبر است.');
            if ($height === false || $height < 64 || $height > 2048) $this->invalidRequest('ارتفاع تصویر WMS نامعتبر است.');
            $bbox = $this->validatedBbox((string) $request->query('BBOX', ''), $crs, $wmsService->version);

            $allowed = [
                'SERVICE' => 'WMS', 'REQUEST' => 'GetMap', 'VERSION' => $wmsService->version,
                'LAYERS' => $wmsLayer->layer_name, 'STYLES' => '', 'FORMAT' => 'image/png',
                'TRANSPARENT' => $wmsService->default_transparent ? 'TRUE' : 'FALSE',
                'WIDTH' => $width, 'HEIGHT' => $height, 'BBOX' => $bbox,
                ($wmsService->version === '1.3.0' ? 'CRS' : 'SRS') => $crs,
            ];

            try {
                $response = Http::timeout(15)->connectTimeout(5)
                    ->withOptions(array_merge(['allow_redirects' => false, 'stream' => true], $urls->requestOptions($wmsService->url)))
                    ->get($wmsService->url.'?'.http_build_query($allowed));
            } catch (ConnectionException $exception) {
                $message = strtolower($exception->getMessage());
                throw str_contains($message, 'timeout') || str_contains($message, 'timed out')
                    ? new WmsTimeoutException($exception)
                    : new WmsConnectionException($exception);
            }

            $status = $response->status();
            if ($status === 401 || $status === 403) throw new WmsAccessDeniedException($status);
            if ($status === 404) throw new WmsRemoteException(404);
            if (! $response->successful() || $status >= 500) throw new WmsRemoteException($status);

            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if ($contentType !== 'image/png') {
                throw new WmsInvalidResponseException('WMS_INVALID_IMAGE', 'پاسخ WMS باید تصویر PNG باشد', 'پاسخ سرویس نقشه تصویر PNG معتبر نیست.', 502);
            }
            $maxBytes = (int) config('gis.wms.max_response_bytes', 8 * 1024 * 1024);
            if ((int) $response->header('Content-Length', 0) > $maxBytes) {
                throw new WmsInvalidResponseException('WMS_IMAGE_TOO_LARGE', 'پاسخ WMS بیش از حد بزرگ است', 'حجم تصویر نقشه از حد مجاز بیشتر است.', 502);
            }
            $stream = $response->toPsrResponse()->getBody();
            $image = '';
            while (! $stream->eof()) {
                $image .= $stream->read(65536);
                if (strlen($image) > $maxBytes) {
                    throw new WmsInvalidResponseException('WMS_IMAGE_TOO_LARGE', 'پاسخ WMS بیش از حد بزرگ است', 'حجم تصویر نقشه از حد مجاز بیشتر است.', 502);
                }
            }
            if (! str_starts_with($image, "\x89PNG\r\n\x1a\n")) {
                throw new WmsInvalidResponseException('WMS_INVALID_IMAGE', 'پاسخ WMS تصویر PNG معتبر نیست', 'تصویر دریافتی از سرویس نقشه قابل نمایش نیست.', 502);
            }

            return response($image, 200)->header('Content-Type', 'image/png')->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            return $errors->json($exception, $wmsService->url);
        }
    }

    private function validatedBbox(string $bbox, string $crs, string $version): string
    {
        $values = array_map('trim', explode(',', $bbox));
        if (count($values) !== 4 || in_array('', $values, true)) $this->invalidRequest('محدوده BBOX برای درخواست WMS نامعتبر است.');
        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) $this->invalidRequest('مختصات BBOX نامعتبر است.');
        }
        [$firstMin, $secondMin, $firstMax, $secondMax] = array_map('floatval', $values);
        if (! ($firstMin < $firstMax && $secondMin < $secondMax)) $this->invalidRequest('ترتیب مختصات BBOX نامعتبر است.');

        if ($crs === 'EPSG:3857') {
            $limit = 20037508.3427892;
            if (! ($firstMin >= -$limit && $firstMax <= $limit && $secondMin >= -$limit && $secondMax <= $limit)) $this->invalidRequest('مختصات BBOX خارج از محدوده Web Mercator است.');
        } elseif ($version === '1.3.0') {
            if (! ($firstMin >= -90 && $firstMax <= 90 && $secondMin >= -180 && $secondMax <= 180)) $this->invalidRequest('ترتیب محورهای EPSG:4326 در BBOX نامعتبر است.');
        } else {
            if (! ($firstMin >= -180 && $firstMax <= 180 && $secondMin >= -90 && $secondMax <= 90)) $this->invalidRequest('مختصات BBOX خارج از محدوده EPSG:4326 است.');
        }
        return implode(',', $values);
    }

    private function invalidRequest(string $message): never
    {
        throw new WmsInvalidResponseException('WMS_INVALID_GETMAP', 'درخواست WMS نامعتبر است', $message, 422);
    }
}
