<?php

use App\Http\Controllers\AcaraController;
use App\Http\Controllers\Admin\SettingControllerAdmin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BukuTamuController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CeritaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GaleryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JenisThemaController;
use App\Http\Controllers\MempelaiController;
use App\Http\Controllers\MethodePembayaran;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaketController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PengunjungController;
use App\Http\Controllers\PernikahanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QouteController;
use App\Http\Controllers\RekeningController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\Admin\AdminBankAccountController;
use App\Http\Controllers\ResultPernikahanController;
use App\Http\Controllers\ResultThemaController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TestimoniController;
use App\Http\Controllers\ThemaController;
use App\Http\Controllers\UcapanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeddingProfileController;
use Illuminate\Support\Facades\Route;

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
Route::get('/v1/paket-undangan', [SettingControllerAdmin::class, 'indexPaket']);

// Public Ucapan (Wedding Wishes & Attendance) endpoints
Route::controller(UcapanController::class)->group(function () {
    Route::get('/v1/ucapan', 'index');
    Route::post('/v1/ucapan', 'store');
    Route::get('/v1/ucapan/{id}', 'show');
    Route::delete('/v1/ucapan/{id}', 'destroy');
    Route::get('/v1/ucapan-statistics', 'statistics');
});

// Public Testimonial endpoints (for landing page display)
Route::controller(TestimoniController::class)->group(function () {
    Route::get('/v1/testimoni/public', 'public');
});

// Public Wedding Profile endpoints (for viewing wedding invitations)
Route::get('/v1/wedding-profile/public', [WeddingProfileController::class, 'publicProfile']);
Route::get('/v1/wedding-profile/couple/{domain}', [WeddingProfileController::class, 'publicProfileByDomain']);

// Public Music Streaming endpoint (for wedding invitations)
Route::get('/v1/music/stream/public', [MusicController::class, 'streamPublic']);

// Public Attendance endpoints (for wedding attendance confirmation)
Route::controller(AttendanceController::class)->group(function () {
    Route::post('/v1/attendance', 'store');
    Route::delete('/v1/attendance/{id}', 'destroy');
    Route::delete('/v1/attendance/user/{user_id}/all', 'destroyAllByUser');
    Route::get('/v1/attendance/stats/{user_id}', 'statistics');
    Route::get('/v1/attendance/user/{user_id}', 'getByUser');
});

Route::controller(InvitationController::class)->group(function () {
    Route::get('/v1/master-tagihan', 'masterTagihan');
    Route::post('/v1/one-step', 'storeStepOne');
    Route::post('/v1/two-step', 'storeStepTwo');
    Route::post('/v1/three-step', 'storeStepThree');
    Route::post('/v1/for-step', 'storeStepFor');
});

Route::controller(MethodePembayaran::class)->group(function () {
    Route::get('/v1/admin/get-methode-transaction', 'index')->name('index');
    Route::get('/v1/list-methode-transaction/all', 'getAllMethodeTransactions');
    Route::get('/v1/list-paket-undangan', 'getPaketUndangan');
});

Route::controller(MempelaiController::class)->group(function () {
    Route::put('/v1/update/status-bayar', 'updateStatusBayar');
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/v1/logout', [LoginController::class, 'logout']);
});

Route::group(['middleware' => ['auth:sanctum', 'role:admin']], function () {
    // Admin Profile Management endpoints (prefixed with admin)
    Route::controller(ProfileController::class)->prefix('admin/profile')->group(function () {
        Route::get('/', 'show')->name('admin.profile.show');
        Route::put('/', 'update')->name('admin.profile.update');
        Route::post('/photo', 'uploadPhoto')->name('admin.profile.upload-photo');
        Route::delete('/photo', 'deletePhoto')->name('admin.profile.delete-photo');
        Route::post('/change-password', 'changePassword')->name('admin.profile.change-password');
    });

    Route::controller(SettingControllerAdmin::class)->group(function () {
        Route::get('/v1/all-tagihan', 'masterTagihan');
        Route::get('/v1/admin/midtrans', 'indexMidtrans');
        Route::get('/v1/admin/midtrans/{id}', 'showMidtrans');
        Route::post('/v1/admin/send-midtrans', 'storeMidtrans');
        Route::put('/v1/admin/midtrans/{id}', 'updateMidtrans');
        Route::delete('/v1/admin/midtrans/{id}', 'destroyMidtrans');
        Route::get('/v1/admin/tripay', 'indexTripay');
        Route::get('/v1/admin/tripay/{id}', 'showTripay');
        Route::post('/v1/admin/send-tripay', 'storeTripay');
        Route::put('/v1/admin/tripay/{id}', 'updateTripay');
        Route::delete('/v1/admin/tripay/{id}', 'destroyTripay');
        Route::get('/v1/admin/paket-undangan', 'indexPaket');
        Route::put('/v1/admin/paket-undangan/{id}', 'updatePaket');
        Route::post('/v1/admin/method-transaction', 'storeMethodTransaction');
    });
    Route::controller(TestimoniController::class)->group(function () {
        Route::get('/v1/admin/testimoni', 'index');
        Route::put('/v1/admin/testimoni/{id}/status', 'updateStatus');
        Route::put('/v1/admin/testimoni/bulk-status', 'bulkUpdateStatus');
        Route::delete('/v1/admin/testimoni/delete-all', 'deleteAll');
        Route::delete('/v1/admin/testimoni/{id}', 'deleteById');
        Route::get('/v1/admin/testimoni/statistics', 'statistics');
    });
    Route::controller(RekeningController::class)->group(function () {
        Route::post('/v1/admin/send-rekening', 'store');
        Route::get('/v1/admin/get-rekening', 'index');
        Route::put('/v1/admin/update-rekening/{id}', 'update');
        Route::delete('/v1/admin/delete-rekening/{id}', 'destroy');
    });

    // New Bank Account Management (Admin)
    Route::controller(AdminBankAccountController::class)->group(function () {
        Route::get('/v1/admin/bank-accounts', 'index');
        Route::get('/v1/admin/bank-accounts/{id}', 'show');
        Route::delete('/v1/admin/bank-accounts/{id}', 'destroy');
        Route::get('/v1/admin/bank-accounts-statistics', 'statistics');
        Route::get('/v1/admin/user-accounts', 'userAccounts');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('/v1/admin/get-users', 'index');
    });

    Route::controller(ThemaController::class)->group(function () {
        Route::get('/v1/admin/get-themas', 'index')->name('thema.index');
    });
    Route::controller(CategoryController::class)->group(function () {
        Route::get('/v1/admin/categorys', 'index');
        Route::post('/v1/admin/add-categorys', 'store');
        Route::put('/v1/admin/update-categorys', 'update');
        Route::delete('/v1/admin/delete-categorys', 'destroy');
        Route::delete('/v1/admin/delete-all-categorys', 'destroyAll');
    });
    Route::controller(JenisThemaController::class)->group(function () {
        Route::get('/v1/admin/jenis-themas', 'index')->name('jenis.index');
    });
    Route::controller(ResultThemaController::class)->group(function () {
        Route::get('/v1/admin/result-themas', 'index')->name('result.index');
    });
    Route::controller(PaketController::class)->group(function () {
        Route::get('/v1/admin/paket-nikah', 'index')->name('paket.index');
    });
    Route::controller(OrderController::class)->group(function () {
        Route::get('/v1/admin/order-nikah', 'index')->name('order.index');
    });
    Route::controller(PembayaranController::class)->group(function () {
        Route::get('/v1/admin/transaction-nikah', 'index')->name('transaction.index');
    });
    Route::controller(PernikahanController::class)->group(function () {
        Route::get('/v1/admin/pernikahan', 'index')->name('pernikahan.index');
    });
    Route::controller(ResultPernikahanController::class)->group(function () {
        Route::get('/v1/admin/result-pernikahan', 'index');
    });

    // Theme Management (Admin)
    Route::controller(\App\Http\Controllers\Admin\CategoryThemaController::class)->prefix('admin/categories')->group(function () {
        Route::get('/', 'index')->name('admin.categories.index');
        Route::post('/', 'store')->name('admin.categories.store');
        Route::get('/{id}', 'show')->name('admin.categories.show');
        Route::put('/{id}', 'update')->name('admin.categories.update');
        Route::delete('/{id}', 'destroy')->name('admin.categories.destroy');
        Route::patch('/{id}/toggle-activation', 'toggleActivation')->name('admin.categories.toggle');
        Route::patch('/sort-order', 'updateSortOrder')->name('admin.categories.sort');
        Route::patch('/bulk-toggle', 'bulkToggleActivation')->name('admin.categories.bulk-toggle');
        Route::get('/statistics/overview', 'statistics')->name('admin.categories.statistics');
    });

    Route::controller(\App\Http\Controllers\Admin\JenisThemaController::class)->prefix('admin/themes')->group(function () {
        Route::get('/', 'index')->name('admin.themes.index');
        Route::post('/', 'store')->name('admin.themes.store');
        Route::get('/{id}', 'show')->name('admin.themes.show');
        Route::put('/{id}', 'update')->name('admin.themes.update');
        Route::delete('/{id}', 'destroy')->name('admin.themes.destroy');
        Route::patch('/{id}/toggle-activation', 'toggleActivation')->name('admin.themes.toggle');
        Route::patch('/sort-order', 'updateSortOrder')->name('admin.themes.sort');
        Route::patch('/bulk-toggle', 'bulkToggleActivation')->name('admin.themes.bulk-toggle');
        Route::get('/categories/available', 'getCategories')->name('admin.themes.categories');
    });

    // New Synchronized Website & Video Invitation Category Management
    Route::controller(\App\Http\Controllers\Api\Admin\WebsiteInvitationCategoryController::class)->prefix('admin/website-categories')->group(function () {
        Route::get('/', 'index')->name('admin.website-categories.index');
        Route::post('/', 'store')->name('admin.website-categories.store');
        Route::get('/{id}', 'show')->name('admin.website-categories.show');
        Route::put('/{id}', 'update')->name('admin.website-categories.update');
        Route::delete('/{id}', 'destroy')->name('admin.website-categories.destroy');
        Route::patch('/{id}/toggle', 'toggleActivation')->name('admin.website-categories.toggle');
        Route::get('/statistics', 'statistics')->name('admin.website-categories.statistics');
    });

    Route::controller(\App\Http\Controllers\Api\Admin\VideoInvitationCategoryController::class)->prefix('admin/video-categories')->group(function () {
        Route::get('/', 'index')->name('admin.video-categories.index');
        Route::post('/', 'store')->name('admin.video-categories.store');
        Route::get('/{id}', 'show')->name('admin.video-categories.show');
        Route::put('/{id}', 'update')->name('admin.video-categories.update');
        Route::delete('/{id}', 'destroy')->name('admin.video-categories.destroy');
        Route::patch('/{id}/toggle', 'toggleActivation')->name('admin.video-categories.toggle');
        Route::get('/statistics', 'statistics')->name('admin.video-categories.statistics');
    });
});

Route::group(['middleware' => ['auth:sanctum', 'role:user']], function () {
    // Profile Management endpoints
    Route::controller(ProfileController::class)->prefix('profile')->group(function () {
        Route::get('/', 'show')->name('profile.show');
        Route::put('/', 'update')->name('profile.update');
        Route::post('/photo', 'uploadPhoto')->name('profile.upload-photo');
        Route::delete('/photo', 'deletePhoto')->name('profile.delete-photo');
        Route::post('/change-password', 'changePassword')->name('profile.change-password');
    });

    Route::controller(PaketController::class)->group(function () {
        Route::get('/v1/user/paket-nikah', 'index');
    });
    Route::controller(UserController::class)->group(function () {
        Route::get('/v1/user-profile', 'userProfile');
        Route::put('/v1/submission-update/user-profile', 'update');
    });
    Route::controller(RekeningController::class)->group(function () {
        Route::post('/v1/user/send-rekening', 'store');
        Route::get('/v1/user/get-rekening', 'index');
        Route::put('/v1/user/update-rekening/{id}', 'update');
        Route::delete('/v1/user/delete-rekening/{id}', 'destroy');
    });

    // New Bank Account Management (User)
    Route::controller(BankAccountController::class)->group(function () {
        Route::get('/v1/user/bank-accounts', 'index');
        Route::post('/v1/user/bank-accounts', 'store');
        Route::get('/v1/user/bank-accounts/{id}', 'show');
        Route::put('/v1/user/bank-accounts/{id}', 'update');
        Route::delete('/v1/user/bank-accounts/{id}', 'destroy');
        Route::get('/v1/user/bank-accounts-statistics', 'statistics');
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
        Route::get('/v1/user/list-cerita', 'index');
        Route::put('/v1/user/update-cerita', 'update');
        Route::delete('/v1/user/delete-cerita', 'destroy');
    });
    Route::controller(QouteController::class)->group(function () {
        Route::post('/v1/user/send-qoute', 'store');
        Route::get('/v1/user/list-qoute', 'index');
        Route::put('/v1/user/update-qoute', 'update');
        Route::delete('/v1/user/delete-qoute', 'destroy');
    });
    Route::controller(TestimoniController::class)->group(function () {
        Route::post('/v1/user/testimoni', 'store')->name('testimoni.store');
    });
    Route::controller(GaleryController::class)->group(function () {
        Route::post('/v1/user/submission-galery', 'store');
        Route::get('/v1/user/list-galery', 'index');
        Route::delete('/v1/user/delete-galery', 'destroy');
    });
    Route::controller(AcaraController::class)->group(function () {
        Route::get('/v1/user/acara', 'index');
        Route::post('/v1/user/submission-countdown', 'storeCountDown');
        Route::post('/v1/user/submission-acara', 'store');
        Route::put('/v1/user/update-countdown/{id}', 'updateCountDown');
        Route::put('/v1/user/update-acara', 'updateAcara');
        Route::delete('/v1/user/delete-countdown', 'destroy');
    });

    Route::controller(MempelaiController::class)->group(function () {
        Route::get('/v1/user/get-mempelai', 'index');
        Route::post('/v1/user/update-mempelai', 'update');

    });

    // Music Management endpoints (Enhanced streaming with Range header support)
    Route::controller(MusicController::class)->prefix('music')->group(function () {
        Route::post('/upload', 'store');
        Route::get('/stream', 'stream');
        Route::get('/download', 'download');
        Route::delete('/delete', 'destroy');
        Route::get('/info', 'info');
    });

    Route::controller(SettingController::class)->group(function () {
        Route::post('/v1/user/settings/domain', 'storeDomainToken');
        Route::post('/v1/user/settings/music', 'storeMusic'); // Kept for backward compatibility
        Route::post('/v1/user/settings/salam', 'storeSalam');
        Route::get('/v1/user/music/download', 'downloadMusic'); // Kept for backward compatibility
        Route::get('/v1/user/music/stream', 'getMusic'); // Kept for backward compatibility
        Route::post('/v1/user/submission-filter', 'create');
        Route::put('/v1/user/submission-filter-update', 'update');
        Route::get('/v1/user/list-data-setting', 'index');
        Route::delete('/v1/user/music/delete', 'deleteMusic'); // Kept for backward compatibility
    });
    Route::controller(ThemaController::class)->group(function () {
        Route::get('/v1/user/get-themas', 'index')->name('user.thema.index');
    });
    Route::controller(CategoryController::class)->group(function () {
        Route::get('/v1/user/categorys', 'index')->name('user.category.index');
    });
    Route::controller(JenisThemaController::class)->group(function () {
        Route::get('/v1/user/jenis-themas', 'index')->name('user.jenis.index');
    });
    Route::controller(ResultThemaController::class)->group(function () {
        Route::get('/v1/user/result-themas', 'index')->name('user.result.index');
    });

    // Wedding Profile endpoints (Core wedding data aggregation)
    Route::controller(WeddingProfileController::class)->group(function () {
        Route::get('/v1/user/wedding-profile', 'show');
        Route::get('/v1/user/wedding-profile/statistics', 'statistics');
    });

    // Dashboard Analytics endpoints (Wedding statistics & metrics)
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/v1/dashboard/overview/{user_id}', 'overview')
            ->where('user_id', '[0-9]+')
            ->name('dashboard.overview');
        Route::get('/v1/dashboard/trends/{user_id}', 'trends')
            ->where('user_id', '[0-9]+')
            ->name('dashboard.trends');
        Route::get('/v1/dashboard/messages/{user_id}', 'messages')
            ->where('user_id', '[0-9]+')
            ->name('dashboard.messages');
    });

    // Theme Selection for Users
    Route::controller(\App\Http\Controllers\Api\ThemeController::class)->prefix('themes')->group(function () {
        Route::post('/select', 'selectTheme')->name('themes.select');
        Route::get('/selected', 'getSelectedTheme')->name('themes.selected');
    });
});

// Public Theme Browsing Routes (no authentication required)
Route::controller(\App\Http\Controllers\Api\ThemeController::class)->prefix('themes')->group(function () {
    Route::get('/categories', 'getCategories')->name('themes.categories');
    Route::get('/categories/{categoryId}', 'getThemesByCategory')->name('themes.by-category');
    Route::get('/theme/{themeId}', 'getTheme')->name('themes.show');
    Route::get('/popular', 'getPopularThemes')->name('themes.popular');
    Route::get('/layout', 'getThemesByLayout')->name('themes.by-layout');
    Route::get('/search', 'searchThemes')->name('themes.search');
    Route::get('/demo/{themeId}', 'getDemoUrl')->name('themes.demo');
});
