<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\{AuthController, RiserApiController, OperationApiController};

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // این دو کنترلر رو بعد از اینکه فایل‌های مدل رو برام فرستادی می‌نویسم
    Route::get('risers', [RiserApiController::class, 'index']);
    Route::get('risers/{riser}/operations', [OperationApiController::class, 'index']);
    Route::post('operations', [OperationApiController::class, 'store']);
});



