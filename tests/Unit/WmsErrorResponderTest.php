<?php

namespace Tests\Unit;

use App\Exceptions\WmsConnectionException;
use App\Services\WmsErrorResponder;
use Tests\TestCase;

class WmsErrorResponderTest extends TestCase
{
    public function test_json_error_has_safe_standard_shape(): void
    {
        $response = app(WmsErrorResponder::class)->json(new WmsConnectionException(), 'http://user:secret@example.test:8080/geoserver/wms');
        $this->assertSame(502, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('WMS_CONNECTION_FAILED', $payload['error']['code']);
        $this->assertStringNotContainsString('secret', json_encode($payload));
        $this->assertStringNotContainsString('cURL', json_encode($payload));
    }
}
