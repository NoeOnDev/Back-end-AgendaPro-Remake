<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Rutas públicas de autenticación
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Verificación de email (ruta firmada)
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed'])
        ->name('verification.verify');

    // Rutas públicas de tipos de negocio (no requieren autenticación)
    Route::prefix('business-types')->group(function () {
        Route::get('/', [BusinessTypeController::class, 'index']);
        Route::get('/search', [BusinessTypeController::class, 'search']);
        Route::get('/stats', [BusinessTypeController::class, 'stats']);
        Route::get('/{businessType}', [BusinessTypeController::class, 'show']);
        Route::get('/{businessType}/template', [BusinessTypeController::class, 'getDefaultTemplate']);
    });
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Rutas protegidas de autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'update']);
    Route::patch('/profile', [AuthController::class, 'update']);

    // Reenviar verificación
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);

    // Rutas de proyectos
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::patch('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);

        // Rutas adicionales
        Route::get('/{project}/stats', [ProjectController::class, 'stats']);
        Route::patch('/{project}/settings', [ProjectController::class, 'updateSettings']);
    });

    // Rutas de gestión de miembros
    Route::prefix('projects/{project}')->group(function () {
        // Gestión de miembros del equipo
        Route::prefix('members')->group(function () {
            Route::get('/', [ProjectUserController::class, 'index']);
            Route::post('/invite', [ProjectUserController::class, 'invite']);
            Route::patch('/{projectUser}/role', [ProjectUserController::class, 'updateRole']);
            Route::delete('/{projectUser}', [ProjectUserController::class, 'removeMember']);
            Route::post('/leave', [ProjectUserController::class, 'leaveProject']);
        });

        // Gestión de invitaciones
        Route::prefix('invitations')->group(function () {
            Route::delete('/{invitation}', [ProjectUserController::class, 'cancelInvitation']);
            Route::post('/{token}/accept', [ProjectUserController::class, 'acceptInvitation']);
            Route::post('/{token}/reject', [ProjectUserController::class, 'rejectInvitation']);
        });

        // Roles disponibles
        Route::get('/roles', [ProjectUserController::class, 'getAvailableRoles']);
    });
});
