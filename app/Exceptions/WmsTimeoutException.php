<?php
namespace App\Exceptions;
class WmsTimeoutException extends WmsException { public function __construct(?\Throwable $previous = null) { parent::__construct('WMS_TIMEOUT', 'زمان اتصال به سرور به پایان رسید', 'سرور نقشه در زمان مشخص پاسخ نداد. لطفاً دوباره تلاش کنید.', 504, $previous); } }
