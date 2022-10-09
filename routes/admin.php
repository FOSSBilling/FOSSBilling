<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;
Route::resource("clients", ClientController::class);
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
