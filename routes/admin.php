<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\Admin\HomeController::class, 'index']);
Route::resource('products', ProductController::class);
Route::resource('clients', ClientController::class);
Route::resource('users', UserController::class);

Route::middleware(['can:view admin'])->group(
    function () {
        Route::middleware(['can:edit settings'])->group(
            function () {
                Route::get('/settings', [SettingController::class, 'show']);
                Route::post('/settings', [SettingController::class, 'save']);
            }
        );
    }
);
