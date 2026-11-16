<?php

use App\Http\Controllers\Api\AccessManagementController;
use App\Http\Controllers\Api\CondominiumController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ResidentController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:6,1');
Route::post('/forgot-password', [PasswordController::class, 'requestLink'])->middleware('throttle:6,1');
Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [SessionController::class, 'user']);
    Route::get('/user/permissions', [SessionController::class, 'permissions']);
    Route::post('/logout', [SessionController::class, 'logout']);
    Route::get('/my-access', [AccessManagementController::class, 'myAccess']);
    Route::get('/condominiums', [CondominiumController::class, 'index']);
    Route::post('/condominiums', [CondominiumController::class, 'store']);
    Route::get('/condominiums/{condominium}', [CondominiumController::class, 'show']);
    Route::put('/condominiums/{condominium}', [CondominiumController::class, 'update']);
    Route::patch('/condominiums/{condominium}/status', [CondominiumController::class, 'changeStatus']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::get('/resident-condominiums', [ResidentController::class, 'condominiums']);
    Route::get('/employee-condominiums', [EmployeeController::class, 'condominiums']);
    Route::prefix('condominiums/{condominium}')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::get('/residents', [ResidentController::class, 'index']);
        Route::post('/blocks', [ResidentController::class, 'storeBlock']);
        Route::post('/units', [ResidentController::class, 'storeUnit']);
        Route::post('/residents', [ResidentController::class, 'store']);
        Route::get('/residents/{link}', [ResidentController::class, 'show']);
        Route::put('/residents/{link}', [ResidentController::class, 'update']);
        Route::delete('/residents/{link}', [ResidentController::class, 'destroy']);
    });

    Route::post('/email/verification-notification', function (Request $request) {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Solicitação de verificação processada.']);
    })->middleware('throttle:6,1');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return response()->json(['message' => 'E-mail verificado com sucesso.']);
    })->middleware(['signed:relative', 'throttle:6,1'])->name('verification.verify');

    Route::prefix('admin')->middleware('can:manage-access')->group(function () {
        Route::get('/access', [AccessManagementController::class, 'index']);
        Route::post('/users/invite', [AccessManagementController::class, 'invite']);
        Route::post('/roles', [AccessManagementController::class, 'createRole']);
        Route::post('/permissions', [AccessManagementController::class, 'createPermission']);
        Route::put('/roles/{role}/permissions', [AccessManagementController::class, 'updateRolePermissions']);
        Route::post('/memberships', [AccessManagementController::class, 'grant']);
        Route::post('/memberships/{membership}/revoke', [AccessManagementController::class, 'revoke']);
    });
});
