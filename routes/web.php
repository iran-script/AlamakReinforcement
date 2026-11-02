<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupUserController;
use App\Http\Controllers\GisSettingsController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MaintenanceCaseController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MbtilesController;
use App\Http\Controllers\OperationCategoryController;
use App\Http\Controllers\OrganizationController;
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
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/maintenance-cases', [MaintenanceCaseController::class, 'index'])->name('maintenance-cases.index');
    Route::post('/maintenance-cases', [MaintenanceCaseController::class, 'store'])->name('maintenance-cases.store');
    Route::get('/maintenance-cases/{maintenanceCase}', [MaintenanceCaseController::class, 'show'])->whereNumber('maintenanceCase')->name('maintenance-cases.show');
    Route::get('/maintenance-cases/photos/{operationPhoto}', [MaintenanceCaseController::class, 'photo'])->whereNumber('operationPhoto')->name('maintenance-cases.photos.show');
    Route::post('/maintenance-cases/{maintenanceCase}/operation', [MaintenanceCaseController::class, 'complete'])->whereNumber('maintenanceCase')->name('maintenance-cases.operation.store');
    Route::put('/maintenance-cases/{maintenanceCase}', [MaintenanceCaseController::class, 'update'])->whereNumber('maintenanceCase')->name('maintenance-cases.update');
    Route::post('/maintenance-cases/{maintenanceCase}/review', [MaintenanceCaseController::class, 'review'])->whereNumber('maintenanceCase')->name('maintenance-cases.review');
    Route::get('/settings/gis-map', [GisSettingsController::class, 'edit'])->name('settings.gis.edit');
    Route::post('/settings/gis-map/test-service', [GisSettingsController::class, 'testService'])->name('settings.gis.test-service');
    Route::put('/settings/gis-map', [GisSettingsController::class, 'update'])->name('settings.gis.update');
    Route::post('/settings/gis-map/profiles', [GisSettingsController::class, 'storeProfile'])->name('settings.gis.profiles.store');
    Route::put('/settings/gis-map/profiles/{gisMapSetting}', [GisSettingsController::class, 'updateProfile'])->whereNumber('gisMapSetting')->name('settings.gis.profiles.update');
    Route::post('/settings/gis-map/profiles/{gisMapSetting}/activate', [GisSettingsController::class, 'activateProfile'])->whereNumber('gisMapSetting')->name('settings.gis.profiles.activate');
    Route::delete('/settings/gis-map/profiles/{gisMapSetting}', [GisSettingsController::class, 'destroyProfile'])->whereNumber('gisMapSetting')->name('settings.gis.profiles.destroy');
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
                ->name('map')
                ->middleware('check_permission:map.view');

            Route::get('/map/geojson', 'geojson')
                ->name('map.geojson')
                ->middleware('check_permission:map.view');

            Route::get('/search-alamak', 'search')
                ->middleware('check_permission:map.view');

            Route::get('/map/tools/extent', 'extent')
                ->name('myextent')
                ->middleware('check_permission:map.view');
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
                ->name('riserIndex')
                ->middleware('check_permission:riser.view');

            Route::get('/risers/table', 'table')
                ->name('riser.table')
                ->middleware('check_permission:riser.view');

            Route::get('/risers/data', 'data')
                ->name('riser.data')
                ->middleware('check_permission:riser.view');

            Route::get('/riser/{id}', 'show')
                ->name('riser.show')
                ->middleware('check_permission:riser.view');

            Route::get('/api/riser/{id}', 'details')
                ->name('riser.details')
                ->middleware('check_permission:riser.view');

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

            Route::get('/boundary', 'boundary')
                ->name('zones.boundary');

            Route::post('/store', 'store')
                ->name('zones.store');

            Route::put('/update/{zone}', 'update')
                ->name('zones.update');

            Route::delete('/destroy/{zone}', 'destroy')
                ->name('zones.destroy');

        });

    Route::get('/zones/manage', [ZoneController::class, 'manage'])
        ->name('zones.manage');

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

    Route::resource('contract', ContractController::class)
        ->middlewareFor(['index', 'show'], 'check_permission:contract.view')
        ->middlewareFor(['create', 'store'], 'check_permission:contract.create')
        ->middlewareFor(['edit', 'update'], 'check_permission:contract.edit')
        ->middlewareFor('destroy', 'check_permission:contract.delete');

    /*
    |--------------------------------------------------------------------------
    | Materials
    |--------------------------------------------------------------------------
    */

    Route::resource('material-category', MaterialCategoryController::class)
        ->middlewareFor(['index', 'show'], 'check_permission:material-category.view')
        ->middlewareFor(['create', 'store'], 'check_permission:material-category.create')
        ->middlewareFor(['edit', 'update'], 'check_permission:material-category.edit')
        ->middlewareFor('destroy', 'check_permission:material-category.delete');

    Route::resource('material', MaterialController::class)
        ->middlewareFor(['index', 'show'], 'check_permission:material.view')
        ->middlewareFor(['create', 'store'], 'check_permission:material.create')
        ->middlewareFor(['edit', 'update'], 'check_permission:material.edit')
        ->middlewareFor('destroy', 'check_permission:material.delete');

    /*
    |--------------------------------------------------------------------------
    | Operation Categories
    |--------------------------------------------------------------------------
    */

    Route::resource('operation-category', OperationCategoryController::class)
        ->middlewareFor(['index', 'show'], 'check_permission:operation-category.view')
        ->middlewareFor(['create', 'store'], 'check_permission:operation-category.create')
        ->middlewareFor(['edit', 'update'], 'check_permission:operation-category.edit')
        ->middlewareFor('destroy', 'check_permission:operation-category.delete');

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

            Route::post('/saved', 'storeSaved')->name('reports.saved.store');
            Route::get('/saved/{savedReport}/open', 'openSaved')->whereNumber('savedReport')->name('reports.saved.open');
            Route::delete('/saved/{savedReport}', 'destroySaved')->whereNumber('savedReport')->name('reports.saved.destroy');
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

Route::middleware('auth')->group(function () {
    Route::resource('organizations', OrganizationController::class)->except(['show']);
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
