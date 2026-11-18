<?php

namespace App\Services;

use App\Exceptions\WmsAccessDeniedException;
use App\Exceptions\WmsConnectionException;
use App\Exceptions\WmsEndpointException;
use App\Exceptions\WmsInvalidResponseException;
use App\Exceptions\WmsNoLayersException;
use App\Exceptions\WmsRemoteException;
use App\Exceptions\WmsTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WmsCapabilitiesService
{
    public function __construct(private WmsUrlValidator $urls) {}

    public function fetch(string $url, ?string $version = null): array
    {
        if ($this->urls->error($url)) throw new WmsEndpointException();
        $version ??= '1.3.0'; $current = $this->withRequest($url, $version); $redirects = 0;
        while ($current->status() >= 300 && $current->status() < 400 && $redirects++ < 3) {
            $location = $current->header('Location');
            if ($location && Str::startsWith($location, '/')) throw new WmsEndpointException();
            if (! $location || $this->urls->error($location) || ! $this->urls->sameDestination($url, $location)) throw new WmsEndpointException();
            $current = $this->withRequest($location, $version);
        }
        if (! $current->successful()) $this->throwForStatus($current->status());
        $contentType = strtolower((string) $current->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'xml')) throw new WmsInvalidResponseException();
        $xml = $current->body();
        if (strlen($xml) > config('gis.wms.max_response_bytes', 8 * 1024 * 1024)) throw new WmsInvalidResponseException('WMS_RESPONSE_TOO_LARGE', 'پاسخ سرویس WMS بیش از حد بزرگ است', 'حجم پاسخ سرویس نقشه از حد مجاز بیشتر است.');
        $previous = libxml_use_internal_errors(true); $dom = new \DOMDocument(); $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA); libxml_clear_errors(); libxml_use_internal_errors($previous);
        if (! $ok || ! $dom->documentElement) throw new WmsInvalidResponseException();
        $root = $dom->documentElement; $detected = $root->getAttribute('version') ?: $version; if (! in_array($detected, ['1.1.1','1.3.0'], true)) $detected = $version;
        $formats = []; foreach ($dom->getElementsByTagName('Format') as $format) { $value = trim($format->textContent); if ($value !== '') $formats[] = $value; }
        $layers = [];
        foreach ($dom->getElementsByTagName('Layer') as $node) {
            $name=''; $title=''; $abstract=''; $crs=[]; $legend=null;
            foreach ($node->childNodes as $child) {
                if (! $child instanceof \DOMElement) continue; $local = $child->localName ?: $child->nodeName;
                if ($local === 'Name') $name=trim($child->textContent); elseif ($local === 'Title') $title=trim($child->textContent); elseif ($local === 'Abstract') $abstract=trim($child->textContent); elseif (in_array($local,['CRS','SRS'],true)) $crs[]=trim($child->textContent); elseif ($local === 'Style') foreach ($child->getElementsByTagName('OnlineResource') as $resource) $legend=$resource->getAttribute('xlink:href') ?: $resource->getAttribute('href');
            }
            if ($name !== '') $layers[]=['layer_name'=>$name,'title'=>$title ?: $name,'abstract'=>$abstract ?: null,'supported_crs'=>array_values(array_unique($crs)),'supported_formats'=>array_values(array_unique($formats)),'version'=>$detected,'queryable'=>in_array(strtolower($node->getAttribute('queryable')),['1','true'],true),'legend_url'=>$legend,'metadata'=>[]];
        }
        if ($layers === []) throw new WmsNoLayersException();
        return ['version'=>$detected,'formats'=>array_values(array_unique($formats)),'layers'=>$layers,'raw_size'=>strlen($xml)];
    }

    private function withRequest(string $url, string $version): Response
    {
        try { return Http::timeout(12)->connectTimeout(5)->withHeaders(['Accept'=>'application/xml,text/xml'])->withOptions(array_merge(['allow_redirects'=>false], $this->urls->requestOptions($url)))->get($url.'?'.http_build_query(['SERVICE'=>'WMS','REQUEST'=>'GetCapabilities','VERSION'=>$version])); }
        catch (ConnectionException $e) { $message=strtolower($e->getMessage()); throw str_contains($message,'timed out') || str_contains($message,'timeout') ? new WmsTimeoutException($e) : new WmsConnectionException($e); }
    }

    private function throwForStatus(int $status): never
    {
        if (in_array($status,[401,403],true)) throw new WmsAccessDeniedException($status);
        if ($status === 404) throw new WmsRemoteException(404);
        throw new WmsRemoteException($status);
    }
}
