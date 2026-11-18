<?php
namespace App\Exceptions;
class WmsEndpointException extends WmsException { public function __construct(?\Throwable $previous = null) { parent::__construct('WMS_ENDPOINT_NOT_ALLOWED', 'آدرس سرویس مجاز نیست', 'این آدرس در مقصدهای مجاز WMS ثبت یا تأیید نشده است.', 422, $previous); } }
