<?php
namespace App\Exceptions;
class WmsInvalidResponseException extends WmsException { public function __construct(string $code = 'WMS_INVALID_RESPONSE', string $title = 'پاسخ سرویس WMS معتبر نیست', string $message = 'پاسخ دریافت‌شده با ساختار استاندارد WMS سازگار نیست.', int $status = 422, ?\Throwable $previous = null) { parent::__construct($code, $title, $message, $status, $previous); } }
