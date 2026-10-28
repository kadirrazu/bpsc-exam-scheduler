<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ActiveApiAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('auth.login');
    Route::middleware(['auth:sanctum', ActiveApiAccount::class, 'throttle:scheduler-api'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/options', [AuthController::class, 'options'])->name('options');
        Route::get('/schedules/export/{format}', [ScheduleController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->middleware('throttle:scheduler-export')->name('schedules.export');
        Route::apiResource('schedules', ScheduleController::class);
        Route::put('/profile', [StaffProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [StaffProfileController::class, 'updatePassword'])->name('profile.password');
        Route::middleware('can:manage-system')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::apiResource('designations', DesignationController::class);
            Route::apiResource('exam-types', CatalogController::class)->parameters(['exam-types' => 'entry']);
            Route::apiResource('units', CatalogController::class)->parameters(['units' => 'entry']);
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });
    });
});
