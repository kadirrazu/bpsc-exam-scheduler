<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PrintAuditController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');
Route::post('/language', [LanguageController::class, 'update'])->middleware('throttle:30,1')->name('language.update');
Route::middleware('auth')->group(function () {
    Route::post('/schedules/print-audit', [PrintAuditController::class, 'store'])->middleware('throttle:30,1')->name('schedules.print-audit');
    Route::get('/dashboard', [ScheduleController::class, 'index'])->name('dashboard');
    Route::get('/schedules/export/{format}', [ScheduleController::class, 'export'])->middleware('throttle:scheduler-export')->name('schedules.export');
    Route::resource('schedules', ScheduleController::class);
    Route::get('/profile', [StaffProfileController::class, 'edit'])->name('staff-profile.edit');
    Route::put('/profile', [StaffProfileController::class, 'update'])->name('staff-profile.update');
    Route::get('/profile/password', [StaffProfileController::class, 'password'])->name('staff-profile.password');
    Route::put('/profile/password', [StaffProfileController::class, 'updatePassword'])->name('staff-profile.update-password');
    Route::middleware('can:manage-system')->group(function () {
        Route::get('/users/{user}/delete-confirm', [UserController::class, 'confirmDelete'])->name('users.confirm-delete');
        Route::resource('users', UserController::class);
        Route::resource('designations', DesignationController::class)->except('show');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
