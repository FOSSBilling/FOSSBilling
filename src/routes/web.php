<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\Home;
use App\Http\Controllers\Admin\Settings as AdminSettings;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



// Client Route
Route::get('/', [Home::class, 'index']);

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::prefix('admin')->middleware(['can:view admin'])->group(
    function () {
        Route::middleware(['can:edit settings'])->group(
            function () {
                Route::get('/settings', [AdminSettings::class, 'show']);
                Route::post('/settings', [AdminSettings::class, 'save']);
            }
        );
    }
);


require __DIR__ . '/auth.php';
