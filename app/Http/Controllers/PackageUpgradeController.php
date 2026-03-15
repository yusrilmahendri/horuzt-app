<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageUpgradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
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
            'extend_from_now' => 'nullable|boolean', // true = extend from now, false = from expiry
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = User::findOrFail($validated['user_id']);
            $newPackage = PaketUndangan::findOrFail($validated['paket_undangan_id']);
            $invitation = Invitation::where('user_id', $user->id)->firstOrFail();
            $mempelai = Mempelai::where('user_id', $user->id)->first();

            // Determine base date for expiry calculation
            $extendFromNow = $request->input('extend_from_now', false);
            $baseDate = $extendFromNow ? now() : ($invitation->domain_expires_at ?? now());

            // Calculate new expiry
            $newExpiryAt = $baseDate->copy()->addDays($newPackage->masa_aktif);

            // Get previous package info for snapshot
            $previousPackageId = $invitation->paket_undangan_id;
            $previousFeatures = $invitation->package_features_snapshot ?? [];

            // Update invitation with new package
            $invitation->update([
                'paket_undangan_id' => $newPackage->id,
                'payment_status' => 'paid',
                'is_trial' => false,
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
                    'previous_package_name' => $previousFeatures['name_paket'] ?? null
                ]
            ]);

            // Update mempelai status
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
        $invitation = Invitation::where('user_id', $user->id)->first();

        $currentPackageId = $invitation?->paket_undangan_id;

        // If no current package (trial), return all packages
        // Otherwise, exclude current package
        $query = PaketUndangan::query();

        if ($currentPackageId) {
            $query->where('id', '!=', $currentPackageId);
        }

        $packages = $query->orderBy('masa_aktif', 'asc')->get();

        return response()->json([
            'data' => $packages,
            'current_package_id' => $currentPackageId,
            'is_trial' => $invitation?->is_trial ?? true
        ], 200);
    }

    /**
     * User initiates package upgrade
     * POST /v1/user/upgrade-package
     */
    public function initiateUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paket_undangan_id' => 'required|exists:paket_undangans,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = auth()->user();
            $newPackage = PaketUndangan::findOrFail($validated['paket_undangan_id']);
            $currentInvitation = Invitation::where('user_id', $user->id)->firstOrFail();

            // Generate new invoice number for upgrade
            $newInvoiceNumber = '#UPG-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) . '-' . time();

            // Create upgrade invitation record
            $upgradeInvitation = Invitation::create([
                'user_id' => $user->id,
                'paket_undangan_id' => $newPackage->id,
                'kode_pemesanan' => $newInvoiceNumber,
                'status' => 'upgrade_pending',
                'payment_status' => 'pending',
                'is_trial' => false,
                'domain_expires_at' => null, // Set after payment
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
                    'upgrade_from_invitation_id' => $currentInvitation->id,
                    'previous_package_id' => $currentInvitation->paket_undangan_id,
                    'previous_package_name' => $currentInvitation->package_features_snapshot['name_paket'] ?? null,
                    'initiated_at' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'message' => 'Upgrade initiated',
                'data' => [
                    'invitation_id' => $upgradeInvitation->id,
                    'kode_pemesanan' => $newInvoiceNumber,
                    'package' => $newPackage->name_paket,
                    'amount' => $newPackage->price,
                    'duration_days' => $newPackage->masa_aktif
                ]
            ], 201);
        });
    }
}
