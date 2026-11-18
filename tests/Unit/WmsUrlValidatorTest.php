<?php

namespace Tests\Unit;

use App\Services\WmsUrlValidator;
use Tests\TestCase;

class WmsUrlValidatorTest extends TestCase
{
    public function test_only_the_configured_local_geoserver_endpoint_is_allowed(): void
    {
        $this->app['env'] = 'local';
        $validator = app(WmsUrlValidator::class);
        $this->assertNull($validator->error('http://127.0.0.1:8080/geoserver/tamirat/wms'));
        $this->assertNotNull($validator->error('http://127.0.0.1:8081/geoserver/tamirat/wms'));
        $this->assertNotNull($validator->error('http://127.0.0.1:8080/geoserver/other/wms'));
        $this->assertNotNull($validator->error('http://127.0.0.1:8080/geoserver/tamirat/wms?request=GetMap'));
        $this->assertNotNull($validator->error('http://10.0.0.1:8080/geoserver/tamirat/wms'));
        $this->assertNotNull($validator->error('https://user:secret@example.com/wms'));
        $this->assertNull($validator->error('https://maps.example.test/geoserver/wms'));
    }

    public function test_local_exception_is_disabled_outside_local_environment(): void
    {
        $this->app['env'] = 'production';
        $this->assertNotNull(app(WmsUrlValidator::class)->error('http://127.0.0.1:8080/geoserver/tamirat/wms'));
    }
}
