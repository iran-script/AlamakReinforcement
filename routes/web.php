<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ServicePointController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TileController;
use App\Http\Controllers\RiserController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ZoneController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::get('/hash', function () {
    return Hash::make('123');
});

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.post');

Route::get('/riser/{id}', [RiserController::class, 'show'])
    ->name('riser.show');

Route::prefix('api')->group(function () {

    Route::get('/riser/{id}', [RiserController::class, 'details'])
        ->name('riser.details');

});


Route::get('/reports', [ReportController::class,'index'])
    ->name('reports.index');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'permission:manage-users'])->group(function () {
    Route::resource('users', UserController::class)
        ->except(['show']);
});

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    
    Route::get('/tiles/{layer}/{z}/{x}/{y}.pbf', [TileController::class, 'tile'])->name('tiles');
    Route::get('/search-alamak', [MapController::class,'search']);
    Route::get('map/tools/extent', [MapController::class, 'extent'])->name('myextent');
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('riser/index', [RiserController::class,'index'])->name('riserIndex');

    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics'])
        ->name('dashboard.statistics');

    /*
    |--------------------------------------------------------------------------
    | GIS Map
    |--------------------------------------------------------------------------
    */

    Route::get('/map', [MapController::class, 'index'])
        ->name('map');

    Route::get('/map/geojson', [MapController::class, 'geojson'])
        ->name('map.geojson');

    /*
    |--------------------------------------------------------------------------
    | Service Points (علمک ها)
    |--------------------------------------------------------------------------
    */


    Route::get('/zones/manage', [ZoneController::class, 'manage'])
        ->name('zones.manage');

    Route::prefix('api')->group(function () {
        Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');
        Route::put('/zones/store', [ZoneController::class, 'store'])->name('zones.store');
        Route::put('/zones/update/{zone}', [ZoneController::class, 'update'])->name('zones.update');
        Route::delete('/zones/destroy/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');
    });

    Route::resource(
        'service-points',
        ServicePointController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Operations (عملیات مقاوم سازی)
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'operations',
        OperationController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Photos
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/operations/{operation}/before-photo',
        [OperationController::class, 'uploadBefore']
    )->name('operations.before-photo');

    Route::post(
        '/operations/{operation}/after-photo',
        [OperationController::class, 'uploadAfter']
    )->name('operations.after-photo');

    /*
    |--------------------------------------------------------------------------
    | Contractors (پیمانکاران)
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'contractors',
        ContractorController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    )->name('reports.index');

    Route::get(
        '/reports/export-excel',
        [ReportController::class, 'exportExcel']
    )->name('reports.export-excel');

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
});