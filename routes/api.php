<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

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
Route::post('/v1/login', [LoginController::class, 'login']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/v1/logout', [LoginController::class, 'logout']);
});



Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Only accessible by users with 'admin' role
    Route::get('admin/dashboard', function () {
        return 'Welcome Admin';
    });
});

Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
    // Only accessible by users with 'user' role
    Route::get('user/dashboard', function () {
        return 'Welcome user';
    });
});