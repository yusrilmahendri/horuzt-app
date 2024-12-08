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
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PernikahanController;
use App\Http\Controllers\ResultPernikahanController;
use App\Http\Controllers\TestimoniController;
use App\Http\Controllers\BukuTamuController;
use App\Http\Controllers\PengunjungController;
use App\Http\Controllers\RekeningController;
use App\Http\Controllers\CeritaController;

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
Route::get('/v1/all-bank', [BankController::class, 'index'])->name('bank.index');


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
    Route::controller(OrderController::class)->group(function() {
        Route::get('/v1/admin/order-nikah', 'index')->name('order.index');
    });
    Route::controller(PembayaranController::class)->group(function() {
        Route::get('/v1/admin/transaction-nikah', 'index')->name('transaction.index');
    });
    Route::controller(PernikahanController::class)->group(function() {
        Route::get('/v1/admin/pernikahan', 'index')->name('pernikahan.index');
    });
    Route::controller(ResultPernikahanController::class)->group(function() {
        Route::get('/v1/admin/result-pernikahan', 'index');
    });
    Route::controller(TestimoniController::class)->group(function() {
        Route::get('/v1/admin/result-testimoni', 'index');
    });
 });

Route::group(['middleware' => ['role:user']], function () { 
    Route::controller(UserController::class)->group(function(){
        Route::get('/v1/user-profile', 'index')->name('index-profile');
    });
    Route::controller(RekeningController::class)->group(function() {
        Route::post('/v1/user/send-rekening', 'store');
    }); 
    Route::controller(BukuTamuController::class)->group(function () {
        Route::get('/v1/user/result-bukutamu', 'index');
        Route::delete('/v1/user/buku-tamu/delete-all', 'deleteAll');
        Route::delete('/v1/user/buku-tamu/{id}', 'deleteById');
    });   
    Route::controller(PengunjungController::class)->group(function () {
        Route::get('/v1/user/result-pengunjung', 'index');
        Route::delete('/v1/user/pengunjung/delete-all', 'deleteAll');
        Route::delete('/v1/user/pengunjung/{id}', 'deleteById');
    });    
    Route::controller(CeritaController::class)->group(function () {
        Route::post('/v1/user/send-cerita', 'store');
    });
    Route::controller(TestimoniController::class)->group(function() {
        Route::post('/v1/user/post-testimoni', 'store')->name('testimoni.store');
    });
 });

