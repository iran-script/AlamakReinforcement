<?php
namespace App\Exceptions;
class WmsRemoteException extends WmsException { public function __construct(int $remoteStatus, ?\Throwable $previous = null) { $notFound = $remoteStatus === 404; parent::__construct($notFound ? 'WMS_NOT_FOUND' : 'WMS_REMOTE_UNAVAILABLE', $notFound ? 'سرویس WMS پیدا نشد' : 'سرویس نقشه موقتاً در دسترس نیست', $notFound ? 'مسیر سرویس واردشده صحیح نیست یا سرویس در این آدرس منتشر نشده است.' : 'سرور WMS با خطا مواجه شده است. لطفاً بعداً دوباره تلاش کنید.', in_array($remoteStatus, [408, 429, 500, 502, 503, 504], true) ? 502 : $remoteStatus, $previous); } }
