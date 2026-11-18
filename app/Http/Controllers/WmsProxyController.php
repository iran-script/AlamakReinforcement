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
use App\Services\WmsUrlValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class WmsProxyController extends Controller
{
    public function __invoke(Request $request, WmsService $wmsService, WmsLayer $wmsLayer, WmsUrlValidator $urls, WmsErrorResponder $errors)
    {
        abort_unless($request->user()?->can('map.view'), 403);
        abort_unless($wmsLayer->wms_service_id === $wmsService->id && $wmsService->is_active && $wmsLayer->is_enabled, 404);
        abort_unless($wmsService->organization_id === null || (int)$wmsService->organization_id === (int)$request->user()->organization_id, 403);
        try {
            if ($urls->error($wmsService->url)) throw new WmsEndpointException();
            $crs = in_array('EPSG:3857',$wmsLayer->supported_crs ?: [],true) ? 'EPSG:3857' : (in_array('EPSG:4326',$wmsLayer->supported_crs ?: [],true) ? 'EPSG:4326' : (($wmsLayer->supported_crs ?: [])[0] ?? 'EPSG:3857'));
            $allowed = ['SERVICE'=>'WMS','REQUEST'=>'GetMap','VERSION'=>$wmsService->version,'LAYERS'=>$wmsLayer->layer_name,'STYLES'=>'','FORMAT'=>'image/png','TRANSPARENT'=>$wmsService->default_transparent ? 'TRUE' : 'FALSE','CRS'=>$crs,'SRS'=>$crs,'WIDTH'=>min((int)$request->input('WIDTH',256),2048),'HEIGHT'=>min((int)$request->input('HEIGHT',256),2048),'BBOX'=>$request->input('BBOX','')];
            foreach (['STYLES','FORMAT','TRANSPARENT','CRS','SRS','WIDTH','HEIGHT','BBOX','TIME'] as $key) if ($request->has($key)) $allowed[$key]=(string)$request->input($key);
            try { $response=Http::timeout(15)->connectTimeout(5)->withOptions(array_merge(['allow_redirects'=>false],$urls->requestOptions($wmsService->url)))->get($wmsService->url.'?'.http_build_query($allowed)); }
            catch (ConnectionException $e) { $message=strtolower($e->getMessage()); throw str_contains($message,'timeout') || str_contains($message,'timed out') ? new WmsTimeoutException($e) : new WmsConnectionException($e); }
            $status=$response->status();
            if ($status === 401 || $status === 403) throw new WmsAccessDeniedException($status);
            if ($status === 404) throw new WmsRemoteException(404);
            if (! $response->successful() || $status >= 500) throw new WmsRemoteException($status);
            if (! str_starts_with(strtolower((string)$response->header('Content-Type')), 'image/')) throw new WmsInvalidResponseException('WMS_INVALID_IMAGE','پاسخ لایه WMS معتبر نیست','سرور نقشه تصویر قابل نمایش برنگرداند.',502);
            if (strlen($response->body()) > config('gis.wms.max_response_bytes',8*1024*1024)) throw new WmsInvalidResponseException('WMS_IMAGE_TOO_LARGE','پاسخ لایه WMS بیش از حد بزرگ است','حجم تصویر نقشه از حد مجاز بیشتر است.',502);
            return response($response->body(),200)->header('Content-Type',$response->header('Content-Type','image/png'))->header('Cache-Control','private, max-age=60');
        } catch (Throwable $e) { return $errors->json($e, $wmsService->url); }
    }
}
