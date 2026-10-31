<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Platform\FormDefinitionController;
use App\Http\Controllers\Api\Platform\FormResolutionController;
use App\Http\Controllers\Api\Platform\FormSubmissionController;
use App\Http\Controllers\Api\Platform\WorkflowDefinitionController;
use App\Http\Controllers\Api\Platform\WorkflowRuntimeController;
use App\Http\Controllers\Api\Platform\WorkOrderController;
use App\Http\Controllers\Api\RiserApiController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\OperationImageController;
use App\Http\Controllers\RiserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API روت‌های اپ موبایل (Flutter)
|--------------------------------------------------------------------------
| این فایل جدیده، به routes/web.php کاری نداره.
| کنترلرهای RiserController, OperationController, OperationImageController
| همون کنترلرهای وب هستن که reuse شدن (چون قبلاً JSON برمی‌گردوندن و
| از session خاصی استفاده نمی‌کردن)، فقط AuthController و RiserApiController جدیدن.
*/

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('platform')->group(function () {
        Route::get('form-definitions', [FormDefinitionController::class, 'index']);
        Route::post('form-definitions', [FormDefinitionController::class, 'store']);
        Route::get('form-definitions/{formDefinition}', [FormDefinitionController::class, 'show']);
        Route::patch('form-definitions/{formDefinition}', [FormDefinitionController::class, 'update']);
        Route::post('form-definitions/{formDefinition}/versions', [FormDefinitionController::class, 'createVersion']);
        Route::get('form-versions/{formVersion}', [FormDefinitionController::class, 'showVersion']);
        Route::patch('form-versions/{formVersion}', [FormDefinitionController::class, 'updateVersion']);
        Route::post('form-versions/{formVersion}/fields', [FormDefinitionController::class, 'addField']);
        Route::patch('form-fields/{formField}', [FormDefinitionController::class, 'updateField']);
        Route::delete('form-fields/{formField}', [FormDefinitionController::class, 'removeField']);
        Route::post('form-versions/{formVersion}/fields/reorder', [FormDefinitionController::class, 'reorder']);
        Route::post('form-versions/{formVersion}/publish', [FormDefinitionController::class, 'publish'])->name('forms.publish');
        Route::post('form-versions/{formVersion}/validate', [FormDefinitionController::class, 'validateVersion']);
        Route::post('form-versions/{formVersion}/clone', [FormDefinitionController::class, 'clone']);
        Route::post('form-versions/{formVersion}/archive', [FormDefinitionController::class, 'archive']);
        Route::get('form-submissions', [FormSubmissionController::class, 'index']);
        Route::post('form-submissions', [FormSubmissionController::class, 'store']);
        Route::get('form-submissions/{formSubmission}', [FormSubmissionController::class, 'show']);
        Route::patch('form-submissions/{formSubmission}/draft', [FormSubmissionController::class, 'save']);
        Route::post('form-submissions/{formSubmission}/submit', [FormSubmissionController::class, 'submit']);
        Route::post('form-submissions/{formSubmission}/files', [FormSubmissionController::class, 'upload']);
        Route::delete('form-submissions/{formSubmission}/files/{formSubmissionFile}', [FormSubmissionController::class, 'destroyFile']);
        Route::get('forms/resolve', FormResolutionController::class);
        Route::get('workflow-definitions', [WorkflowDefinitionController::class, 'index']);
        Route::post('workflow-definitions', [WorkflowDefinitionController::class, 'store']);
        Route::get('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'show']);
        Route::patch('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'update']);
        Route::post('workflow-definitions/{workflowDefinition}/versions', [WorkflowDefinitionController::class, 'createVersion']);
        Route::get('workflow-versions/{workflowVersion}', [WorkflowDefinitionController::class, 'showVersion']);
        Route::patch('workflow-versions/{workflowVersion}', [WorkflowDefinitionController::class, 'updateVersion']);
        Route::post('workflow-versions/{workflowVersion}/states', [WorkflowDefinitionController::class, 'addState']);
        Route::patch('workflow-states/{workflowState}', [WorkflowDefinitionController::class, 'updateState']);
        Route::delete('workflow-states/{workflowState}', [WorkflowDefinitionController::class, 'removeState']);
        Route::post('workflow-versions/{workflowVersion}/transitions', [WorkflowDefinitionController::class, 'addTransition']);
        Route::patch('workflow-transitions/{workflowTransition}', [WorkflowDefinitionController::class, 'updateTransition']);
        Route::delete('workflow-transitions/{workflowTransition}', [WorkflowDefinitionController::class, 'removeTransition']);
        Route::post('workflow-versions/{workflowVersion}/validate', [WorkflowDefinitionController::class, 'validateVersion']);
        Route::post('workflow-versions/{workflowVersion}/publish', [WorkflowDefinitionController::class, 'publish']);
        Route::post('workflow-versions/{workflowVersion}/clone', [WorkflowDefinitionController::class, 'clone']);
        Route::post('workflow-versions/{workflowVersion}/archive', [WorkflowDefinitionController::class, 'archive']);
        Route::post('workflows/instances', [WorkflowRuntimeController::class, 'start']);
        Route::get('workflow-instances/{workflowInstance}', [WorkflowRuntimeController::class, 'show']);
        Route::get('workflow-instances/{workflowInstance}/available-transitions', [WorkflowRuntimeController::class, 'available']);
        Route::post('workflow-instances/{workflowInstance}/transitions/{workflowTransition}', [WorkflowRuntimeController::class, 'transition']);
        Route::get('workflow-tasks', [WorkflowRuntimeController::class, 'tasks']);
        Route::post('workflow-tasks/{workflowTask}/complete', [WorkflowRuntimeController::class, 'completeTask']);
        Route::get('work-order-types', [WorkOrderController::class, 'types']);
        Route::get('work-orders', [WorkOrderController::class, 'index']);
        Route::post('work-orders', [WorkOrderController::class, 'store']);
        Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show']);
        Route::patch('work-orders/{workOrder}', [WorkOrderController::class, 'update']);
        Route::post('work-orders/{workOrder}/assign', [WorkOrderController::class, 'assign']);
        Route::post('work-orders/{workOrder}/status', [WorkOrderController::class, 'status']);
        Route::post('work-orders/{workOrder}/assets', [WorkOrderController::class, 'attachAsset']);
        Route::delete('work-orders/{workOrder}/assets/{asset}', [WorkOrderController::class, 'detachAsset']);
    });

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('change-password', [AuthController::class, 'changePassword']);

    // نقشه: لیست رایزرهای قابل مشاهده (GeoJSON)
    Route::get('risers', [RiserApiController::class, 'index']);

    // جزئیات یک رایزر (عملیات، گردش‌کار، مصالح) — همون چیزی که وب استفاده می‌کنه
    Route::get('risers/{id}/details', [RiserController::class, 'details']);
    Route::get('risers/{riser}/history', [OperationController::class, 'history']);

    // فلگ کردن رایزر برای نیاز به عملیات
    Route::post('risers/{riser}/flag-operation-required', [RiserController::class, 'flagOperationRequired']);

    // ثبت عملیات جدید (دقیقاً همون کنترلر وب — شامل اتصال عکس‌های before، مصالح، و شروع WorkflowService)
    Route::post('operations', [OperationController::class, 'store']);
    Route::post('operations/{operation}/approve', [OperationController::class, 'approve']);
    Route::post('operations/{operation}/return', [OperationController::class, 'returnForRevision']);

    // آپلود عکس (before/after) — همون کنترلر وب
    Route::post('operation-images', [OperationImageController::class, 'store']);
    Route::get('risers/{riserId}/pending-before-photos', [OperationImageController::class, 'pendingBefore']);
    Route::delete('operation-images/{operationPhoto}', [OperationImageController::class, 'destroy']);
});
