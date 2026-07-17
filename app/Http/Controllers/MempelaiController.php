<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Galery;
use App\Models\Invitation;
use App\Models\Mempelai;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\Mempelai\MempelaiCollection;
use App\Services\AccountStatusService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MempelaiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }


    private function transformPhotoUrls($mempelai)
    {
        $photoPriaUrl = $this->publicStorageUrl($mempelai->photo_pria);
        $photoWanitaUrl = $this->publicStorageUrl($mempelai->photo_wanita);
        $coverPhotoUrl = $this->publicStorageUrl($mempelai->cover_photo);

        $mempelai->photo_pria = $photoPriaUrl;
        $mempelai->photo_wanita = $photoWanitaUrl;
        $mempelai->cover_photo = $coverPhotoUrl;
        $mempelai->photo_pria_url = $photoPriaUrl;
        $mempelai->photo_wanita_url = $photoWanitaUrl;
        $mempelai->cover_photo_url = $coverPhotoUrl;

        return $mempelai;
    }

    private function syncGalleryPhotoPath(int $userId, string $namaFoto, string $photoPath): void
    {
        Galery::updateOrCreate(
            [
                'user_id' => $userId,
                'nama_foto' => $namaFoto,
            ],
            [
                'photo' => $photoPath,
                'status' => 1,
            ]
        );
    }

    public function index()
    {
        $userId = Auth::id();
        $mempelai = Mempelai::where('user_id', $userId)->get();


        $mempelai = $mempelai->map(function ($item) {
            return $this->transformPhotoUrls($item);
        });

        return new MempelaiCollection($mempelai);
    }

    public function update(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Pengguna tidak terautentikasi.',
                ], 401);
            }

            $validated = $request->validate([
                'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'urutan_mempelai' => 'nullable|string|in:pria,wanita',
                'photo_pria' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'photo_wanita' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'name_lengkap_pria' => 'nullable|string|max:255',
                'name_lengkap_wanita' => 'nullable|string|max:255',
                'name_panggilan_pria' => 'nullable|string|max:255',
                'name_panggilan_wanita' => 'nullable|string|max:255',
                'ayah_pria' => 'nullable|string|max:255',
                'ayah_wanita' => 'nullable|string|max:255',
                'ibu_pria' => 'nullable|string|max:255',
                'ibu_wanita' => 'nullable|string|max:255',
            ]);

            $mempelai = Mempelai::firstOrCreate(
                ['user_id' => $userId],
                [
                    'status' => 'Belum Bayar',
                    'kd_status' => 'BB',
                ]
            );

            $updateData = [];

            if ($request->hasFile('cover_photo')) {
                if (
                    $mempelai->cover_photo &&
                    Storage::disk('public')->exists($mempelai->cover_photo)
                ) {
                    Storage::disk('public')->delete($mempelai->cover_photo);
                }

                $updateData['cover_photo'] = $request
                    ->file('cover_photo')
                    ->store('photos', 'public');
            }

            if ($request->hasFile('photo_pria')) {
                if (
                    $mempelai->photo_pria &&
                    Storage::disk('public')->exists($mempelai->photo_pria)
                ) {
                    Storage::disk('public')->delete($mempelai->photo_pria);
                }

                $updateData['photo_pria'] = $request
                    ->file('photo_pria')
                    ->store('photos', 'public');
            }

            if ($request->hasFile('photo_wanita')) {
                if (
                    $mempelai->photo_wanita &&
                    Storage::disk('public')->exists($mempelai->photo_wanita)
                ) {
                    Storage::disk('public')->delete($mempelai->photo_wanita);
                }

                $updateData['photo_wanita'] = $request
                    ->file('photo_wanita')
                    ->store('photos', 'public');
            }

            $textFields = [
                'urutan_mempelai',
                'name_lengkap_pria',
                'name_lengkap_wanita',
                'name_panggilan_pria',
                'name_panggilan_wanita',
                'ayah_pria',
                'ayah_wanita',
                'ibu_pria',
                'ibu_wanita',
            ];

            foreach ($textFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $validated[$field];
                }
            }

            if (!empty($updateData)) {
                $mempelai->update($updateData);
            }

            if (isset($updateData['photo_pria'])) {
                $this->syncGalleryPhotoPath(
                    $userId,
                    'Photo Pria',
                    $updateData['photo_pria']
                );
            }

            if (isset($updateData['photo_wanita'])) {
                $this->syncGalleryPhotoPath(
                    $userId,
                    'Photo Wanita',
                    $updateData['photo_wanita']
                );
            }

            if (isset($updateData['cover_photo'])) {
                $this->syncGalleryPhotoPath(
                    $userId,
                    'Cover Photo',
                    $updateData['cover_photo']
                );
            }

            $mempelai->refresh();
            $mempelai = $this->transformPhotoUrls($mempelai);

            return response()->json([
                'message' => 'Data mempelai berhasil diperbarui',
                'data' => $mempelai,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Gagal memperbarui data mempelai', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan server',
            ], 500);
        }
    }


    public function updateStatusBayar(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'kode_pemesanan' => 'nullable|string|required_without_all:kode_invoice,no_invoice,invoice_number,order_id,transaksi_id',
                'kode_invoice' => 'nullable|string',
                'no_invoice' => 'nullable|string',
                'invoice_number' => 'nullable|string',
                'order_id' => 'nullable|string',
                'transaksi_id' => 'nullable|string',
            ]);

            $user = User::find($validated['user_id']);
            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan',
                ], 404);
            }

            $invoiceCode = $this->extractInvoiceCode($validated);
            if ($invoiceCode === '') {
                return response()->json([
                    'message' => 'Invoice/tagihan pengguna belum tersedia.',
                ], 422);
            }

            if (! Invitation::where('user_id', $user->id)->exists()) {
                return response()->json([
                    'message' => 'Invoice/tagihan pengguna belum tersedia.',
                ], 404);
            }

            if ($this->invoiceCodeExistsForAnotherUser((int) $user->id, $invoiceCode)) {
                return response()->json([
                    'message' => 'Kode pemesanan tidak sesuai dengan pengguna.',
                ], 422);
            }

            $invoice = $this->resolveInvoiceForUser((int) $user->id, $invoiceCode);

            if (!$invoice) {
                return response()->json([
                    'message' => 'Kode pemesanan tidak ditemukan untuk pengguna ini.',
                ], 404);
            }

            if (! $this->isConfirmablePaymentStatus($invoice->payment_status, $invoice->status)) {
                return response()->json([
                    'message' => 'Invoice/tagihan pengguna sudah selesai.',
                ], 422);
            }

            // Use database transaction for data consistency
            return DB::transaction(function () use ($invoice, $user) {
                $paymentConfirmedAt = now();

                // Calculate domain expiry in days (masa_aktif is in days)
                // Use snapshot data if available, otherwise try to get from relation
                $masaAktif = $invoice->package_duration_snapshot
                    ?? ($invoice->paketUndangan->masa_aktif ?? 30);
                $activeDays = (int) $masaAktif;
                $domainExpiresAt = ($invoice->domain_expires_at && $invoice->domain_expires_at->isFuture())
                    ? $invoice->domain_expires_at
                    : $paymentConfirmedAt->copy()->addDays($activeDays);

                // Update Mempelai payment status
                $mempelai = Mempelai::where('user_id', $user->id)->first();
                if ($mempelai) {
                    $mempelai->update([
                        'status'    => 'Sudah Bayar',
                        'kd_status' => 'SB',
                    ]);
                }

                // Update Invitation with payment confirmation and domain expiry
                $invoiceUpdate = [
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'payment_confirmed_at' => $invoice->payment_confirmed_at ?: $paymentConfirmedAt,
                    'domain_expires_at' => $domainExpiresAt,
                ];

                if (Schema::hasColumn('invitations', 'tanggal_mulai')) {
                    $invoiceUpdate['tanggal_mulai'] = $invoice->tanggal_mulai ?: $paymentConfirmedAt;
                }

                if (Schema::hasColumn('invitations', 'tanggal_expired')) {
                    $invoiceUpdate['tanggal_expired'] = $invoice->tanggal_expired ?: $domainExpiresAt;
                }

                $invoice->update($invoiceUpdate);

                $invoice->refresh()->load('paketUndangan');
                $accountStatus = app(AccountStatusService::class)->summary($user->fresh());

                return response()->json([
                    'message' => 'Status pembayaran berhasil dikonfirmasi dan invoice selesai.',
                    'mempelai' => $mempelai,
                    'invitation' => [
                        'id' => $invoice->id,
                        'status' => $invoice->status,
                        'kode_pemesanan' => $invoice->kode_pemesanan,
                        'order_id' => $invoice->order_id,
                        'midtrans_transaction_id' => $invoice->midtrans_transaction_id,
                        'payment_status' => $invoice->payment_status,
                        'payment_confirmed_at' => $invoice->payment_confirmed_at,
                        'domain_expires_at' => $invoice->domain_expires_at,
                        'domain_active_days' => $activeDays,
                        'package_used' => $invoice->package_features_snapshot['name_paket'] ?? 'Unknown',
                        'original_price' => $invoice->package_price_snapshot,
                    ],
                    'account_status' => $accountStatus['account_status'],
                    'active_until' => $accountStatus['active_until'],
                ], 200);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error di updateStatusBayar: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan server',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function extractInvoiceCode(array $validated): string
    {
        foreach (['kode_pemesanan', 'kode_invoice', 'no_invoice', 'invoice_number', 'order_id', 'transaksi_id'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            if ($value !== '') {
                return ltrim($value, '#');
            }
        }

        return '';
    }

    private function invoiceCodeVariants(string $code): array
    {
        $raw = trim($code);
        $withoutHash = ltrim($raw, '#');

        return collect([$raw, $withoutHash, '#'.$withoutHash])
            ->filter(fn ($value) => $value !== '' && $value !== '#')
            ->unique()
            ->values()
            ->all();
    }

    private function isConfirmablePaymentStatus(?string ...$statuses): bool
    {
        $confirmable = ['pending', 'belum selesai', 'unpaid', 'menunggu pembayaran'];

        foreach ($statuses as $status) {
            $normalized = strtolower(trim((string) $status));
            if (in_array($normalized, $confirmable, true)) {
                return true;
            }
        }

        return false;
    }

    private function invoiceCodeExistsForAnotherUser(int $userId, string $code): bool
    {
        $variants = $this->invoiceCodeVariants($code);
        $normalized = ltrim(trim($code), '#');

        return Invitation::query()
            ->where('user_id', '!=', $userId)
            ->where(function ($invoiceQuery) use ($variants, $normalized) {
                $hasCondition = false;

                foreach (['kode_pemesanan', 'order_id', 'midtrans_transaction_id'] as $column) {
                    if (Schema::hasColumn('invitations', $column)) {
                        $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                        $invoiceQuery->{$method}($column, $variants);
                        $hasCondition = true;
                    }
                }

                if (is_numeric($normalized)) {
                    $id = (int) ltrim($normalized, '0');
                    if ($id > 0) {
                        $method = $hasCondition ? 'orWhere' : 'where';
                        $invoiceQuery->{$method}('id', $id);
                    }
                }
            })
            ->exists();
    }

    private function resolveInvoiceForUser(int $userId, string $code): ?Invitation
    {
        $variants = $this->invoiceCodeVariants($code);
        $normalized = ltrim(trim($code), '#');

        $query = Invitation::with('paketUndangan')
            ->where('user_id', $userId)
            ->where(function ($invoiceQuery) use ($variants, $normalized, $userId) {
                $hasCondition = false;

                foreach (['kode_pemesanan', 'order_id', 'midtrans_transaction_id'] as $column) {
                    if (Schema::hasColumn('invitations', $column)) {
                        $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                        $invoiceQuery->{$method}($column, $variants);
                        $hasCondition = true;
                    }
                }

                if (is_numeric($normalized)) {
                    $id = (int) ltrim($normalized, '0');
                    if ($id > 0) {
                        $method = $hasCondition ? 'orWhere' : 'where';
                        $invoiceQuery->{$method}('id', $id);
                        $hasCondition = true;
                    }
                }

                if (Schema::hasColumn('users', 'kode_pemesanan')) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                    $invoiceQuery->{$method}('user', function ($userQuery) use ($variants, $userId) {
                        $userQuery->where('id', $userId)
                            ->whereIn('kode_pemesanan', $variants);
                    });
                }
            });

        return $query
            ->orderByRaw("CASE WHEN payment_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);

        $path = preg_replace('#^https?://[^/]+/storage/#', '', $path);
        $path = preg_replace('#^/storage/#', '', $path);
        $path = preg_replace('#^storage/#', '', $path);
        $path = ltrim($path, '/');

        return $path ?: null;
    }

    private function publicStorageUrl(?string $path): ?string
    {
        $cleanPath = $this->normalizeStoragePath($path);

        if (! $cleanPath) {
            return null;
        }

        if (! Storage::disk('public')->exists($cleanPath)) {
            Log::warning('[MissingImageFile]', [
                'original_path' => $path,
                'clean_path' => $cleanPath,
            ]);

            return null;
        }

        return Storage::disk('public')->url($cleanPath);
    }


}
