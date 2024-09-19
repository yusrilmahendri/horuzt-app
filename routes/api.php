<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/v1/register', [RegisterController::class, 'index']);
Route::post('/v1/login', [LoginController::class, 'login'])->name('login');


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/v1/logout', [LoginController::class, 'logout']);
});

Route::group(['middleware' => ['role:admin']], function () { 
    Route::controller(UserController::class)->group(function(){
        Route::get('/v1/admin/get-users', 'index')->name('index');
    });
 });

Route::group(['middleware' => ['role:user']], function () { 
    Route::controller(UserController::class)->group(function(){
        Route::get('/v1/user-profile', 'index')->name('index');
    });
 });

