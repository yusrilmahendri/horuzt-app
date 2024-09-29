<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ThemaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\JenisThemaController;
use App\Http\Controllers\ResultThemaController;
use App\Http\Controllers\PaketController;
use App\Http\Controllers\BankController;

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
    Route::controller(ThemaController::class)->group(function() {
        Route::get('/v1/admin/get-themas', 'index')->name('thema.index');
    });
    Route::controller(CategoryController::class)->group(function() {
        Route::get('/v1/admin/categorys', 'index')->name('category.index');
    });
    Route::controller(JenisThemaController::class)->group(function() {
        Route::get('/v1/admin/jenis-themas', 'index')->name('jenis.index');
    });
    Route::controller(ResultThemaController::class)->group(function() {
        Route::get('/v1/admin/result-themas', 'index')->name('result.index');
    });
    Route::controller(PaketController::class)->group(function() {
        Route::get('/v1/admin/paket-nikah', 'index')->name('paket.index');
    });
    Route::get('/v1/admin/all-bank', [BankController::class, 'index'])->name('bank.index');
 });

Route::group(['middleware' => ['role:user']], function () { 
    Route::controller(UserController::class)->group(function(){
        Route::get('/v1/user-profile', 'index')->name('index');
    });
 });

