<?php
namespace App\Exceptions;
class WmsConnectionException extends WmsException { public function __construct(?\Throwable $previous = null) { parent::__construct('WMS_CONNECTION_FAILED', 'ارتباط با سرور WMS برقرار نشد', 'سرور نقشه در آدرس واردشده پاسخ نمی‌دهد. آدرس IP، پورت و وضعیت سرویس GeoServer را بررسی کنید.', 502, $previous); } }
