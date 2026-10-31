<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupUserController;
use App\Http\Controllers\GisSettingsController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MbtilesController;
use App\Http\Controllers\OperationCategoryController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\OperationImageController;
use App\Http\Controllers\Platform\FormStudioController;
use App\Http\Controllers\Platform\WorkflowStudioController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServicePointController;
use App\Http\Controllers\TileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

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
    'tile',
]);

Route::get('/', function () {
    return view('landing');
})->name('landing');

// Login
Route::get('/login', [
    AuthController::class,
    'showLogin',
])->name('login');

Route::post('/login', [
    AuthController::class,
    'login',
])->name('login.post');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/settings/gis-map', [GisSettingsController::class, 'edit'])->name('settings.gis.edit');
    Route::put('/settings/gis-map', [GisSettingsController::class, 'update'])->name('settings.gis.update');
    Route::post('/settings/gis-map/layers', [GisSettingsController::class, 'storeLayer'])->name('settings.gis.layers.store');
    Route::delete('/settings/gis-map/layers/{gisMapLayer}', [GisSettingsController::class, 'destroyLayer'])->name('settings.gis.layers.destroy');
    Route::prefix('platform')->name('platform.')->controller(WorkflowStudioController::class)->group(function () {
        Route::get('workflows', 'index')->name('workflows.index');
        Route::get('workflows/create', 'create')->name('workflows.create');
        Route::get('workflows/{workflowDefinition}', 'show')->name('workflows.show');
        Route::get('workflows/{workflowDefinition}/edit', 'edit')->name('workflows.edit');
        Route::get('workflow-versions/{workflowVersion}/designer', 'designer')->name('workflows.designer');
        Route::get('workflow-versions/{workflowVersion}/preview', 'preview')->name('workflows.preview');
        Route::get('workflow-instances/{workflowInstance}', 'instance')->name('workflows.instances.show');
    });
    Route::prefix('platform')->name('platform.')->controller(FormStudioController::class)->group(function () {
        Route::get('forms', 'index')->name('forms.index');
        Route::get('forms/create', 'create')->name('forms.create');
        Route::get('forms/{formDefinition}', 'show')->name('forms.show');
        Route::get('forms/{formDefinition}/edit', 'edit')->name('forms.edit');
        Route::get('form-versions/{formVersion}/designer', 'designer')->name('forms.designer');
        Route::get('form-versions/{formVersion}/preview', 'preview')->name('forms.preview');
        Route::get('form-submissions/{formSubmission}', 'submission')->name('forms.submissions.show');
        Route::get('form-submissions/{formSubmission}/files/{formSubmissionFile}', 'download')->name('forms.files.download');
    });

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
                ->name('dashboard')
                ->middleware('permission:dashboard.view');

            Route::get('/dashboard/statistics', 'statistics')
                ->name('dashboard.statistics')
                ->middleware('permission:dashboard.view');
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
                'manage',
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
    Route::put('operations/{operation}', [OperationController::class, 'update'])->name('operations.update');
    Route::post('operations/{operation}/approve', [OperationController::class, 'approve'])->name('operations.approve');
    Route::post('operations/{operation}/return', [OperationController::class, 'returnForRevision'])->name('operations.return');

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
        ->middleware('permission:report.view')
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
    'permission:user.view',
])
    ->group(function () {

        Route::resource(
            'users',
            UserController::class
        )->except(['show']);
    });

Route::middleware([
    'auth',
    'permission:groupuser.view',
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
    'permission:role.view',
])
    ->group(function () {

        Route::resource(
            'roles',
            RoleController::class
        );
    });
