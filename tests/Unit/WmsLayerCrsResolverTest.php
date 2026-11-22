<?php

namespace Tests\Unit;

use App\Models\WmsLayer;
use App\Models\WmsService;
use App\Services\WmsLayerCrsResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WmsLayerCrsResolverTest extends TestCase
{
    public function test_runtime_fallback_includes_crs_inherited_from_capabilities(): void
    {
        config(['cache.stores.file.path' => storage_path('framework/testing/cache')]);
        $pathToken = bin2hex(random_bytes(8));
        $url = 'https://maps.example.test/runtime-crs-fallback-'.$pathToken.'/wms';
        $service = WmsService::make([
            'url' => $url,
            'version' => '1.3.0',
        ]);
        $layer = WmsLayer::make([
            'layer_name' => 'roads',
            'supported_crs' => ['EPSG:32639'],
        ]);
        $service->setRelation('layers', collect([$layer]));

        Http::fake(['https://maps.example.test/runtime-crs-fallback-'.$pathToken.'/*' => Http::response(
            '<?xml version="1.0"?><WMS_Capabilities version="1.3.0"><Capability><Layer><Title>Root</Title><CRS>EPSG:3857</CRS><Layer><Name>roads</Name><Title>Roads</Title></Layer></Layer></Capability></WMS_Capabilities>',
            200,
            ['Content-Type' => 'text/xml'],
        )]);

        $resolved = app(WmsLayerCrsResolver::class)->forService($service);

        $this->assertSame(['EPSG:32639', 'EPSG:3857'], $resolved['roads']);
        $this->assertSame('EPSG:3857', app(WmsLayerCrsResolver::class)->preferred($resolved['roads']));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'REQUEST=GetCapabilities'));
    }
}
