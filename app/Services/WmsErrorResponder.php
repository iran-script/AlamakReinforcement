<?php

namespace App\Services;

use App\Exceptions\WmsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class WmsErrorResponder
{
    public function json(Throwable $exception, ?string $url = null): JsonResponse
    {
        $error = $this->error($exception);
        $this->log($exception, $url, $error);
        return response()->json(['success'=>false, 'error'=>['code'=>$error['code'], 'title'=>$error['title'], 'message'=>$error['message']]], $error['status']);
    }

    public function flash(Request $request, Throwable $exception, ?string $url = null, string $field = 'url')
    {
        $error = $this->error($exception);
        $this->log($exception, $url, $error);
        return back()->withInput()->withErrors([$field => $error['title'].' — '.$error['message']]);
    }

    public function error(Throwable $exception): array
    {
        if ($exception instanceof WmsException) return ['code'=>$exception->errorCode, 'title'=>$exception->title, 'message'=>$exception->userMessage, 'status'=>$exception->status];
        if ($exception instanceof QueryException) return ['code'=>'WMS_STORAGE_FAILED', 'title'=>'امکان ذخیره تنظیمات وجود ندارد', 'message'=>'در ذخیره اطلاعات سرویس مشکلی رخ داد. لطفاً با مدیر سیستم تماس بگیرید.', 'status'=>500];
        return ['code'=>'WMS_UNEXPECTED_ERROR', 'title'=>'خطای سرویس نقشه', 'message'=>'در ارتباط با سرویس نقشه مشکلی رخ داد. لطفاً دوباره تلاش کنید.', 'status'=>502];
    }

    private function log(Throwable $exception, ?string $url, array $error): void
    {
        Log::error('WMS operation failed', ['error_code'=>$error['code'], 'exception'=>get_class($exception), 'technical_message'=>$this->safeTechnicalMessage($exception->getMessage()), 'url'=>$this->safeUrl($url)]);
    }

    private function safeUrl(?string $url): ?string
    {
        if (! $url) return null; $parts = parse_url($url); if ($parts === false) return '[invalid-url]';
        return ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
    }

    private function safeTechnicalMessage(string $message): string
    {
        $message = preg_replace('~https?://[^\s/@]+:[^\s/@]+@~i', 'https://[redacted]@', $message) ?? $message;
        return mb_substr($message, 0, 1000);
    }
}
