<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

    Route::middleware('permission:packages.view,packages.manage')->group(function () {
        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    });
    Route::middleware('permission:packages.manage')->group(function () {
        Route::get('/packages/create', [PackageController::class, 'create'])->name('packages.create');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
        Route::get('/packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
        Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
    });

    Route::middleware('permission:tokens.view')->group(function () {
        Route::get('/tokens', [TokenController::class, 'index'])->name('tokens.index');
        Route::get('/tokens/batches/{batch}', [TokenController::class, 'batch'])->name('tokens.batches.show');
    });
    Route::middleware('permission:tokens.generate')->group(function () {
        Route::get('/tokens/create', [TokenController::class, 'create'])->name('tokens.create');
        Route::post('/tokens', [TokenController::class, 'store'])->name('tokens.store');
    });
    Route::middleware('permission:tokens.print')->group(function () {
        Route::get('/tokens/batches/{batch}/print', [TokenController::class, 'print'])->name('tokens.batches.print');
    });
    Route::middleware('permission:tokens.revoke')->group(function () {
        Route::post('/tokens/{token}/revoke', [TokenController::class, 'revoke'])->name('tokens.revoke');
        Route::post('/tokens/{token}/suspend', [TokenController::class, 'suspend'])->name('tokens.suspend');
        Route::post('/tokens/{token}/unsuspend', [TokenController::class, 'unsuspend'])->name('tokens.unsuspend');
    });
    Route::get('/tokens/{token}', [TokenController::class, 'show'])
        ->middleware('permission:tokens.view')->whereNumber('token')->name('tokens.show');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('permission:settings.manage,network.configure')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')->name('audit.index');
});
