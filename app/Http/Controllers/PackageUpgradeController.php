<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PackageUpgradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function hasIsTrialColumn(): bool
    {
        return Schema::hasColumn('invitations', 'is_trial');
    }

    /**
     * Admin changes user package
     * POST /v1/admin/change-package
     */
    public function changePackage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'paket_undangan_id' => 'required|exists:paket_undangans,id',
            'extend_from_now' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = User::findOrFail($validated['user_id']);
            $newPackage = PaketUndangan::findOrFail($validated['paket_undangan_id']);
            $invitation = Invitation::where('user_id', $user->id)->firstOrFail();
            $mempelai = Mempelai::where('user_id', $user->id)->first();

            $extendFromNow = $request->input('extend_from_now', false);
            $baseDate = $extendFromNow ? now() : ($invitation->domain_expires_at ?? now());
            $newExpiryAt = $baseDate->copy()->addDays($newPackage->masa_aktif);

            $previousPackageId = $invitation->paket_undangan_id;
            $previousFeatures = $invitation->package_features_snapshot ?? [];

            $updateData = [
                'paket_undangan_id' => $newPackage->id,
                'payment_status' => 'paid',
                'domain_expires_at' => $newExpiryAt,
                'package_price_snapshot' => $newPackage->price,
                'package_duration_snapshot' => $newPackage->masa_aktif,
                'package_features_snapshot' => [
                    'jenis_paket' => $newPackage->jenis_paket,
                    'name_paket' => $newPackage->name_paket,
                    'halaman_buku' => $newPackage->halaman_buku,
                    'kirim_wa' => $newPackage->kirim_wa,
                    'bebas_pilih_tema' => $newPackage->bebas_pilih_tema,
                    'kirim_hadiah' => $newPackage->kirim_hadiah,
                    'import_data' => $newPackage->import_data,
                    'upgraded_at' => now()->toISOString(),
                    'upgraded_by' => 'admin',
                    'previous_package_id' => $previousPackageId,
                    'previous_package_name' => $previousFeatures['name_paket'] ?? $previousFeatures['previous_package_name'] ?? $previousFeatures['name_paket'] ?? null
                ]
            ];

            if ($this->hasIsTrialColumn()) {
                $updateData['is_trial'] = false;
            }

            $invitation->update($updateData);

            if ($mempelai) {
                $mempelai->update([
                    'status' => 'Sudah Bayar',
                    'kd_status' => 'SB'
                ]);
            }

            return response()->json([
                'message' => 'Package berhasil diubah',
                'data' => [
                    'user_id' => $user->id,
                    'new_package' => $newPackage->name_paket,
                    'new_package_id' => $newPackage->id,
                    'domain_expires_at' => $newExpiryAt->format('Y-m-d H:i:s'),
                    'active_days' => $newPackage->masa_aktif
                ]
            ], 200);
        });
    }

    /**
     * Get eligible packages for upgrade (excludes current)
     * GET /v1/user/eligible-packages
     */
    public function getEligiblePackages(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Prioritize paid invitation, fallback to latest invitation
        // This handles cases where there might be multiple invitation records
        $invitation = Invitation::where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                      ->orWhere('payment_status', 'pending');
            })
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->first();

        $currentPackageId = $invitation?->paket_undangan_id;

        $query = PaketUndangan::query();

        if ($currentPackageId) {
            $query->where('id', '!=', $currentPackageId);
        }

        $packages = $query->orderBy('masa_aktif', 'asc')->get();

        $isTrial = true;
        $hasPendingUpgrade = false;

        if ($invitation && $this->hasIsTrialColumn()) {
            $isTrial = $invitation->is_trial ?? true;
        } elseif ($invitation) {
            $isTrial = $invitation->payment_status === 'pending';
        }

        // Check for pending upgrade
        $snapshot = $invitation?->package_features_snapshot ?? [];
        if (isset($snapshot['upgrade_initiated_at'])) {
            $initiatedAt = Carbon::parse($snapshot['upgrade_initiated_at']);
            if ($initiatedAt->diffInMinutes(now()) < 30 && $invitation->payment_status === 'pending') {
                $hasPendingUpgrade = true;
            }
        }

        return response()->json([
            'data' => $packages,
            'current_package_id' => $currentPackageId,
            'current_package_name' => $snapshot['name_paket'] ?? null,
            'invitation_id' => $invitation?->id,
            'payment_status' => $invitation?->payment_status,
            'is_trial' => $isTrial,
            'has_pending_upgrade' => $hasPendingUpgrade
        ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * User initiates package upgrade
     * POST /v1/user/upgrade-package
     *
     * FIXED: Now updates existing invitation instead of creating new one
     * to prevent duplicate records and dashboard 403 errors
     */
    public function initiateUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paket_undangan_id' => 'required|exists:paket_undangans,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = auth()->user();
            $newPackage = PaketUndangan::findOrFail($validated['paket_undangan_id']);

            // Get user's current invitation (there should only be one)
            $invitation = Invitation::where('user_id', $user->id)->first();

            if (!$invitation) {
                return response()->json([
                    'message' => 'Invitation tidak ditemukan. Silakan hubungi support.'
                ], 404);
            }

            // DUPLICATE PREVENTION: Check if there's already a pending upgrade
            $currentSnapshot = $invitation->package_features_snapshot ?? [];
            if (isset($currentSnapshot['upgrade_initiated_at'])) {
                $initiatedAt = Carbon::parse($currentSnapshot['upgrade_initiated_at']);
                // If upgrade was initiated less than 30 minutes ago, prevent duplicate
                if ($initiatedAt->diffInMinutes(now()) < 30) {
                    return response()->json([
                        'message' => 'Anda memiliki pengajuan upgrade yang sedang diproses. Tunggu pembayaran selesai atau hubungi support.',
                        'data' => [
                            'invitation_id' => $invitation->id,
                            'pending_upgrade' => true
                        ]
                    ], 409); // 409 Conflict
                }
            }

            $newInvoiceNumber = '#UPG-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . time();

            // Store current package info for history before updating
            $previousPackageId = $invitation->paket_undangan_id;
            $previousFeatures = $invitation->package_features_snapshot ?? [];

            // Update existing invitation (not create new)
            $updateData = [
                'paket_undangan_id' => $newPackage->id,
                'kode_pemesanan' => $newInvoiceNumber,
                'payment_status' => 'pending', // Reset to pending for upgrade payment
                'package_price_snapshot' => $newPackage->price,
                'package_duration_snapshot' => $newPackage->masa_aktif,
                'package_features_snapshot' => [
                    'jenis_paket' => $newPackage->jenis_paket,
                    'name_paket' => $newPackage->name_paket,
                    'halaman_buku' => $newPackage->halaman_buku,
                    'kirim_wa' => $newPackage->kirim_wa,
                    'bebas_pilih_tema' => $newPackage->bebas_pilih_tema,
                    'kirim_hadiah' => $newPackage->kirim_hadiah,
                    'import_data' => $newPackage->import_data,
                    // UPGRADE HISTORY - preserve previous package info
                    'upgrade_initiated_at' => now()->toISOString(),
                    'previous_package_id' => $previousPackageId,
                    'previous_package_name' => $previousFeatures['name_paket'] ?? null,
                    'original_status' => $invitation->status, // Remember original status
                    'original_payment_status' => $previousFeatures['original_payment_status'] ?? $invitation->payment_status,
                ]
            ];

            if ($this->hasIsTrialColumn()) {
                $updateData['is_trial'] = false;
            }

            $invitation->update($updateData);

            return response()->json([
                'message' => 'Upgrade initiated',
                'data' => [
                    'invitation_id' => $invitation->id,
                    'kode_pemesanan' => $newInvoiceNumber,
                    'package' => $newPackage->name_paket,
                    'amount' => $newPackage->price,
                    'duration_days' => $newPackage->masa_aktif
                ]
            ], 201);
        });
    }
}
