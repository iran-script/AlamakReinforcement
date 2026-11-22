<?php

namespace Tests\Unit;

use App\Exceptions\WmsInvalidResponseException;
use App\Http\Controllers\WmsProxyController;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WmsGetMapRequestTest extends TestCase
{
    public static function invalidBboxes(): array
    {
        return [
            'wrong number of values' => ['49,34,50', 'EPSG:4326', '1.1.1'],
            'latitude and longitude swapped for WMS 1.3' => ['120,34,121,35', 'EPSG:4326', '1.3.0'],
            'invalid Web Mercator bounds' => ['-20050000,-1,20050000,1', 'EPSG:3857', '1.3.0'],
            'non-numeric coordinate' => ['a,34,50,35', 'EPSG:4326', '1.1.1'],
        ];
    }

    public function test_epsg_4326_axis_order_is_validated_by_wms_version(): void
    {
        $controller = new WmsProxyController();
        $method = new \ReflectionMethod($controller, 'validatedBbox');
        self::assertSame('34.5,49.5,34.6,49.6', $method->invoke($controller, '34.5,49.5,34.6,49.6', 'EPSG:4326', '1.3.0'));
        self::assertSame('49.5,34.5,49.6,34.6', $method->invoke($controller, '49.5,34.5,49.6,34.6', 'EPSG:4326', '1.1.1'));
    }

    #[DataProvider('invalidBboxes')]
    public function test_invalid_bbox_is_rejected_before_remote_request(string $bbox, string $crs, string $version): void
    {
        $controller = new WmsProxyController();
        $method = new \ReflectionMethod($controller, 'validatedBbox');
        try {
            $method->invoke($controller, $bbox, $crs, $version);
            self::fail('Invalid GetMap BBOX must be rejected.');
        } catch (WmsInvalidResponseException $exception) {
            self::assertSame(422, $exception->status);
            self::assertSame('WMS_INVALID_GETMAP', $exception->errorCode);
        }
    }
}
