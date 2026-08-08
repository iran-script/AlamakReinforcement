<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\{
    AuthController,
    DashboardController,
    MapController,
    ServicePointController,
    OperationController,
    ContractorController,
    ReportController,
    TileController,
    RiserController,
    UserController,
    GroupUserController,
    ZoneController,
    OperationCategoryController,
    MaterialCategoryController,
    MaterialController,
    RoleController,
    MbtilesController,
    ContractController,
    OperationImageController
};


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Public Tiles (OSM)
|--------------------------------------------------------------------------
*/

Route::get('/tiles/{z}/{x}/{y}.png', [
    MbtilesController::class,
    'tile'
]);

Route::get('/', function () {
    return view('landing');
})->name('landing');


// Login
Route::get('/login', [
    AuthController::class,
    'showLogin'
])->name('login');


Route::post('/login', [
    AuthController::class,
    'login'
])->name('login.post');







// Hash test
Route::get('/hash', function () {
    return Hash::make('123');
});



/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/


Route::middleware('auth')->group(function () {



    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */


    Route::post('/logout', function (Request $request) {

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    })->name('logout');




    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */


    Route::controller(DashboardController::class)
        ->group(function () {

            Route::get('/dashboard', 'index')
                ->name('dashboard');


            Route::get('/dashboard/statistics', 'statistics')
                ->name('dashboard.statistics');
        });




    /*
    |--------------------------------------------------------------------------
    | Map
    |--------------------------------------------------------------------------
    */


    Route::controller(MapController::class)
        ->group(function () {

            Route::get('/map', 'index')
                ->name('map');


            Route::get('/map/geojson', 'geojson')
                ->name('map.geojson');


            Route::get('/search-alamak', 'search');


            Route::get('/map/tools/extent', 'extent')
                ->name('myextent');
        });



    /*
    |--------------------------------------------------------------------------
    | Vector Tiles
    |--------------------------------------------------------------------------
    */


    Route::get(
        '/tiles/{layer}/{z}/{x}/{y}.pbf',
        [TileController::class, 'tile']
    )->name('tiles');





    /*
    |--------------------------------------------------------------------------
    | Riser
    |--------------------------------------------------------------------------
    */


    Route::controller(RiserController::class)
        ->group(function () {

            Route::get('/riser/index', 'index')
                ->name('riserIndex');


            Route::get('/risers/table', 'table')
                ->name('riser.table');


            Route::get('/risers/data', 'data')
                ->name('riser.data');


            Route::get('/riser/{id}', 'show')
                ->name('riser.show');


            Route::get('/api/riser/{id}', 'details')
                ->name('riser.details');


            Route::post('/riser/{riser}/bookmark', 'bookmark')
                ->name('bookmark');
        });





    /*
    |--------------------------------------------------------------------------
    | Zones
    |--------------------------------------------------------------------------
    */


    Route::prefix('api/zones')
        ->controller(ZoneController::class)
        ->group(function () {


            Route::get('/', 'index')
                ->name('zones.index');


            Route::post('/store', 'store')
                ->name('zones.store');


            Route::put('/update/{zone}', 'update')
                ->name('zones.update');


            Route::delete('/destroy/{zone}', 'destroy')
                ->name('zones.destroy');

            Route::get('/zones/manage', [
                ZoneController::class,
                'manage'
            ])->name('zones.manage');
        });





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
        [OperationController::class, 'uploadBefore']
    );


    Route::post(
        '/operations/{operation}/after-photo',
        [OperationController::class, 'uploadAfter']
    );

    Route::post('riser/photos/upload', [OperationImageController::class, 'store'])->name('riser.photos.upload');
    Route::post('operation/photos/upload', [OperationImageController::class, 'store'])->name('operation.photos.upload');
    Route::delete('operation/photos/{operationPhoto}', [OperationImageController::class, 'destroy'])->name('operation.photos.destroy');
    Route::get('riser/{riser}/pending-before-photos', [OperationImageController::class, 'pendingBefore'])->name('operation.photos.pending-before');



    /*
    |--------------------------------------------------------------------------
    | GIS Related Resources
    |--------------------------------------------------------------------------
    */


    Route::resource(
        'service-points',
        ServicePointController::class
    );


    Route::resource(
        'contractors',
        ContractorController::class
    );


    Route::resource(
        'contract',
        ContractController::class
    );





    /*
    |--------------------------------------------------------------------------
    | Materials
    |--------------------------------------------------------------------------
    */


    Route::resource(
        'material-category',
        MaterialCategoryController::class
    );


    Route::resource(
        'material',
        MaterialController::class
    );





    /*
    |--------------------------------------------------------------------------
    | Operation Categories
    |--------------------------------------------------------------------------
    */


    Route::resource(
        'operation-category',
        OperationCategoryController::class
    );





    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */


    Route::controller(ReportController::class)
        ->prefix('reports')
        ->group(function () {


            Route::get('/', 'index')
                ->name('reports.index');


            Route::get('/data', 'data')
                ->name('report.data');


            Route::get('/excel', 'excel')
                ->name('report.excel');


            Route::get('/pdf', 'pdf')
                ->name('report.pdf');
        });
});





/*
|--------------------------------------------------------------------------
| User Management
|--------------------------------------------------------------------------
*/


Route::middleware([
    'auth',
    'permission:user.view'
])
    ->group(function () {

        Route::resource(
            'users',
            UserController::class
        )->except(['show']);
    });



Route::middleware([
    'auth',
    'permission:groupuser.view'
])
    ->group(function () {

        Route::resource(
            'groupusers',
            GroupUserController::class
        );
    });





/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/


Route::middleware([
    'auth',
    'permission:role.view'
])
    ->group(function () {

        Route::resource(
            'roles',
            RoleController::class
        );
    });
