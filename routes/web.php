<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

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
use App\Http\Controllers\GroupUserController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\OperationCategoryController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\MbtilesController;
use App\Http\Controllers\ContractController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/


// Home
Route::get('/', function () {
     return view('landing');
})->name('landing');


// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.post');


// Logout
Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('landing');
})->name('logout');


Route::get('/tiles/{z}/{x}/{y}.png', [MbtilesController::class,'tile']);
Route::get('/risers/table', [RiserController::class, 'table'])->name('riser.table');
Route::get('/risers/data', [RiserController::class, 'data'])->name('riser.data');
Route::post('/riser/{riser}/bookmark', [RiserController::class, 'bookmark'])
    ->name('bookmark');
// Hash test
Route::get('/hash', function () {
    return Hash::make('123');
});


// Public Riser View
Route::get('/riser/{id}', [RiserController::class, 'show'])
    ->name('riser.show');



/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->group(function () {

    Route::get('/riser/{id}', [
        RiserController::class,
        'details'
    ])->name('riser.details');
});



/*
|--------------------------------------------------------------------------
| Public Resources
|--------------------------------------------------------------------------
*/


Route::resource(
    'operation-category',
    OperationCategoryController::class
);

Route::resource('contract', ContractController::class);


Route::resource(
    'material-category',
    MaterialCategoryController::class
);


Route::resource(
    'material',
    MaterialController::class
);


Route::resource(
    'roles',
    RoleController::class
);



/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/


Route::middleware('auth')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */


    Route::get('/dashboard', [
        DashboardController::class,
        'index'
    ])->name('dashboard');


    Route::get('/dashboard/statistics', [
        DashboardController::class,
        'statistics'
    ])->name('dashboard.statistics');



    /*
    |--------------------------------------------------------------------------
    | GIS Map
    |--------------------------------------------------------------------------
    */


    Route::get('/map', [
        MapController::class,
        'index'
    ])->name('map');


    Route::get('/map/geojson', [
        MapController::class,
        'geojson'
    ])->name('map.geojson');


    Route::get('/search-alamak', [
        MapController::class,
        'search'
    ]);


    Route::get('/map/tools/extent', [
        MapController::class,
        'extent'
    ])->name('myextent');



    /*
    |--------------------------------------------------------------------------
    | Vector Tiles
    |--------------------------------------------------------------------------
    */


    Route::get(
        '/tiles/{layer}/{z}/{x}/{y}.pbf',
        [
            TileController::class,
            'tile'
        ]
    )->name('tiles');



    /*
    |--------------------------------------------------------------------------
    | Riser
    |--------------------------------------------------------------------------
    */


    Route::get(
        '/riser/index',
        [
            RiserController::class,
            'index'
        ]
    )->name('riserIndex');



    /*
    |--------------------------------------------------------------------------
    | Zones
    |--------------------------------------------------------------------------
    */


    Route::get(
        '/zones/manage',
        [
            ZoneController::class,
            'manage'
        ]
    )->name('zones.manage');


    Route::prefix('api/zones')->group(function () {

        Route::get('/', [
            ZoneController::class,
            'index'
        ])->name('zones.index');


        Route::post('/store', [
            ZoneController::class,
            'store'
        ])->name('zones.store');


        Route::put('/update/{zone}', [
            ZoneController::class,
            'update'
        ])->name('zones.update');


        Route::delete('/destroy/{zone}', [
            ZoneController::class,
            'destroy'
        ])->name('zones.destroy');
    });



    /*
    |--------------------------------------------------------------------------
    | Service Points
    |--------------------------------------------------------------------------
    */


    Route::resource(
        'service-points',
        ServicePointController::class
    );



    /*
    |--------------------------------------------------------------------------
    | Operations
    |--------------------------------------------------------------------------
    */


    Route::resource(
        'operations',
        OperationController::class
    );


    Route::post(
        '/operations/{operation}/before-photo',
        [
            OperationController::class,
            'uploadBefore'
        ]
    )->name('operations.before-photo');


    Route::post(
        '/operations/{operation}/after-photo',
        [
            OperationController::class,
            'uploadAfter'
        ]
    )->name('operations.after-photo');



    /*
    |--------------------------------------------------------------------------
    | Contractors
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
        [
            ReportController::class,
            'index'
        ]
    )->name('reports.index');


     

    Route::get('/reports/data',
        [ReportController::class,'data']
    )->name('report.data');


    Route::get('/reports/excel',
        [ReportController::class,'excel']
    )->name('report.excel');


    Route::get('/reports/pdf',
        [ReportController::class,'pdf']
    )->name('report.pdf');
});



/*
|--------------------------------------------------------------------------
| User Management
|--------------------------------------------------------------------------
*/


Route::middleware([
    'auth',
    'permission:user.view'
])->group(function () {


    Route::resource(
        'users',
        UserController::class
    )->except([
        'show'
    ]);

    
});

Route::middleware([
    'auth',
    'permission:groupuser.view'
])->group(function () {


    Route::resource('groupusers', GroupUserController::class);

    
});
