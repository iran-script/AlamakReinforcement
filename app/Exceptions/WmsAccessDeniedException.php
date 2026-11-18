<?php
namespace App\Exceptions;
class WmsAccessDeniedException extends WmsException { public function __construct(int $remoteStatus = 403, ?\Throwable $previous = null) { parent::__construct('WMS_ACCESS_DENIED', 'دسترسی به سرویس WMS مجاز نیست', 'سرور نقشه درخواست را رد کرد. تنظیمات دسترسی سرویس را بررسی کنید.', $remoteStatus === 401 ? 401 : 403, $previous); } }
