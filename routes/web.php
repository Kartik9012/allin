<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::group(['middleware' => ['XssSanitization']], function () {
    Route::controller(LoginController::class)->group(function () {
        Route::get('/login', 'login')->name('login');
        Route::post('login', 'loginPost')->name('loginPost');
    });
    Route::group(['middleware' => ['auth','checkAuth']], function () {
        Route::controller(LoginController::class)->group(function () {
            Route::post('logout', 'logout')->name('logout');
        });
        Route::controller(DashboardController::class)->group(function () {
            Route::get('/', 'index')->name('dashboard');
        });
        Route::controller(UserController::class)->group(function () {
            Route::get('user-list', 'index')->name('userList');
            Route::post('user-list-post', 'indexPost')->name('userListPost');
        });
    });
});
