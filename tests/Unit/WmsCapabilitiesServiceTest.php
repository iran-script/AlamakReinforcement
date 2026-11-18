<?php

namespace Tests\Unit;

use App\Services\WmsCapabilitiesService;
use App\Exceptions\WmsInvalidResponseException;
use App\Exceptions\WmsNoLayersException;
use App\Exceptions\WmsRemoteException;
use App\Exceptions\WmsAccessDeniedException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WmsCapabilitiesServiceTest extends TestCase
{
    public function test_capabilities_extracts_named_layers_and_version(): void
    {
        Http::fake(['https://maps.example.test/*' => Http::response('<?xml version="1.0"?><WMS_Capabilities version="1.3.0"><Capability><Request><GetMap><Format>image/png</Format></GetMap></Request><Layer><Title>Root</Title><CRS>EPSG:3857</CRS><Layer queryable="1"><Name>roads</Name><Title>Roads</Title><Abstract>Road network</Abstract></Layer><Layer><Title>Group only</Title></Layer></Layer></Capability></WMS_Capabilities>', 200, ['Content-Type'=>'text/xml'])]);
        $data = app(WmsCapabilitiesService::class)->fetch('https://maps.example.test/geoserver/wms');
        $this->assertSame('1.3.0', $data['version']);
        $this->assertSame('roads', $data['layers'][0]['layer_name']);
        $this->assertTrue($data['layers'][0]['queryable']);
    }

    public function test_remote_http_errors_are_classified_without_exposing_transport_text(): void
    {
        Http::fake(['https://maps.example.test/*' => Http::response('not found', 404, ['Content-Type'=>'text/plain'])]);
        try { app(WmsCapabilitiesService::class)->fetch('https://maps.example.test/geoserver/wms'); $this->fail('Expected WMS exception.'); }
        catch (WmsRemoteException $e) { $this->assertSame('WMS_NOT_FOUND', $e->errorCode); $this->assertSame('سرویس WMS پیدا نشد', $e->title); $this->assertStringNotContainsString('404', $e->userMessage); }
    }

    public function test_invalid_xml_and_empty_capabilities_are_safe_domain_errors(): void
    {
        Http::fake(['https://maps.example.test/invalid*' => Http::response('<broken', 200, ['Content-Type'=>'text/xml']), 'https://maps.example.test/empty*' => Http::response('<?xml version="1.0"?><WMS_Capabilities version="1.3.0"><Capability/></WMS_Capabilities>', 200, ['Content-Type'=>'text/xml'])]);
        try { app(WmsCapabilitiesService::class)->fetch('https://maps.example.test/invalid'); $this->fail('Expected invalid XML exception.'); } catch (WmsInvalidResponseException $e) { $this->assertSame('WMS_INVALID_RESPONSE', $e->errorCode); }
        try { app(WmsCapabilitiesService::class)->fetch('https://maps.example.test/empty'); $this->fail('Expected no-layer exception.'); } catch (WmsNoLayersException $e) { $this->assertSame('WMS_NO_LAYERS', $e->errorCode); }
    }

    public function test_access_and_server_statuses_have_safe_categories(): void
    {
        Http::fake(['https://maps.example.test/401*'=>Http::response('',401), 'https://maps.example.test/403*'=>Http::response('',403), 'https://maps.example.test/500*'=>Http::response('',500)]);
        foreach ([['401','WMS_ACCESS_DENIED'],['403','WMS_ACCESS_DENIED'],['500','WMS_REMOTE_UNAVAILABLE']] as [$path,$expected]) {
            try { app(WmsCapabilitiesService::class)->fetch('https://maps.example.test/'.$path); $this->fail('Expected classified WMS exception.'); } catch (\App\Exceptions\WmsException $e) { $this->assertSame($expected,$e->errorCode); }
        }
    }
}
