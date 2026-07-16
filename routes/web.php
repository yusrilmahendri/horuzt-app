<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment', function () {
    return view('payment');
});

Route::post('/payment', [PaymentController::class, 'create'])->name('payment.create');

Route::get('/local/admin/music-catalog', function () {
    return view('admin.music-catalog');
});

$frontendBaseUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://www.sena-digital.com')), '/');

Route::get('/payment/success', function (\Illuminate\Http\Request $request) use ($frontendBaseUrl) {
    $query = array_merge($request->query(), ['payment' => 'finish']);
    return redirect()->away($frontendBaseUrl . '/dashboard?' . http_build_query($query), 302);
});

Route::get('/payment/pending', function (\Illuminate\Http\Request $request) use ($frontendBaseUrl) {
    $query = array_merge($request->query(), ['payment' => 'unfinish']);
    return redirect()->away($frontendBaseUrl . '/dashboard/payment-pending?' . http_build_query($query), 302);
});

Route::get('/payment/error', function (\Illuminate\Http\Request $request) use ($frontendBaseUrl) {
    $query = array_merge($request->query(), ['payment' => 'error']);
    return redirect()->away($frontendBaseUrl . '/pilih-paket?' . http_build_query($query), 302);
});
