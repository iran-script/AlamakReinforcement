<?php
namespace App\Exceptions;
class WmsNoLayersException extends WmsException { public function __construct(?\Throwable $previous = null) { parent::__construct('WMS_NO_LAYERS', 'لایه‌ای در سرویس پیدا نشد', 'اتصال برقرار شد، اما این سرویس هیچ لایه قابل نمایشی برنگرداند.', 422, $previous); } }
