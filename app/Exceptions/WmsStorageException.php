<?php
namespace App\Exceptions;
class WmsStorageException extends WmsException { public function __construct(?\Throwable $previous = null) { parent::__construct('WMS_STORAGE_FAILED', 'امکان ذخیره تنظیمات وجود ندارد', 'در ذخیره اطلاعات سرویس مشکلی رخ داد. لطفاً با مدیر سیستم تماس بگیرید.', 500, $previous); } }
