<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\User\UserResource;
use App\Models\PaketUndangan;
use App\Models\User;
use App\Services\AccountStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(private AccountStatusService $accountStatusService)
    {
        // Authentication middleware only - role middleware handled at route level
        $this->middleware('auth:sanctum');
    }

    /**
     * Get user profile data
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user()->fresh()->load([
                'invitationOne.paketUndangan.accessibleCategories',
                'settingOne',
                'mempelaiOne'
            ]);

            $profileData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo_url' => $user->profile_photo
                    ? url(Storage::url($user->profile_photo))
                    : null,
                'kode_pemesanan' => $user->kode_pemesanan,
                'package_info' => $this->getPackageInfo($user),
                'domain_info' => $this->getDomainInfo($user),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ] + $this->accountStatusService->summary($user);

            return response()->json([
                'success' => true,
                'message' => 'Profile data berhasil diambil',
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function status(): JsonResponse
    {
        try {
            $user = Auth::user()->fresh()->load([
                'invitationOne.paketUndangan',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status akun berhasil diambil',
                'data' => $this->accountStatusService->summary($user),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting profile status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status akun',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            DB::transaction(function () use ($request, $user) {
                $user->update([
                    'name' => $request->validated('name'),
                    'email' => $request->validated('email'),
                    'phone' => $request->validated('phone'),
                ]);
            });

            // Reload user with relationships
            $user->load([
                'invitationOne.paketUndangan.accessibleCategories',
                'settingOne',
                'mempelaiOne'
            ]);

            $profileData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo_url' => $user->profile_photo
                    ? Storage::url($user->profile_photo)
                    : null,
                'package_info' => $this->getPackageInfo($user),
                'updated_at' => $user->updated_at,
            ] + $this->accountStatusService->summary($user);

            return response()->json([
                'success' => true,
                'message' => 'Profile berhasil diperbarui',
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Upload profile photo
     */
    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            DB::transaction(function () use ($request, $user) {
                // Delete old photo if exists
                if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                    Storage::disk('public')->delete($user->profile_photo);
                }

                // Store new photo
                $photoPath = $request->file('profile_photo')->store('profile-photos', 'public');

                $user->update([
                    'profile_photo' => $photoPath
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Foto profile berhasil diperbarui',
                'data' => [
                    'profile_photo_url' => url(Storage::url($user->profile_photo)),
                    'updated_at' => $user->fresh()->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error uploading profile photo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupload foto profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete profile photo
     */
    public function deletePhoto(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->profile_photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada foto profile untuk dihapus'
                ], 404);
            }

            DB::transaction(function () use ($user) {
                // Delete photo file
                if (Storage::disk('public')->exists($user->profile_photo)) {
                    Storage::disk('public')->delete($user->profile_photo);
                }

                // Update user record
                $user->update([
                    'profile_photo' => null
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Foto profile berhasil dihapus',
                'data' => [
                    'profile_photo_url' => null,
                    'updated_at' => $user->fresh()->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting profile photo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus foto profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            DB::transaction(function () use ($request, $user) {
                $user->update([
                    'password' => Hash::make($request->validated('new_password'))
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Kata sandi berhasil diperbarui',
                'data' => [
                    'updated_at' => $user->fresh()->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error changing password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah kata sandi',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get package information for user
     */
    private function getPackageInfo(User $user): ?array
    {
        if (!$user->invitationOne || !$user->invitationOne->paketUndangan) {
            return null;
        }

        $invitation = $user->invitationOne;
        $package = $invitation->paketUndangan;

        // Use code as source of truth for package naming.
        $snapshot = is_array($invitation->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];
        $packageCode = $package->code
            ?? $snapshot['code']
            ?? PaketUndangan::tierCode($snapshot['name_paket'] ?? $snapshot['jenis_paket'] ?? null);
        $packageName = PaketUndangan::displayLabelFromCode(
            $packageCode,
            $snapshot['name_paket'] ?? $package->name_paket
        );
        $packagePrice = $invitation->package_price_snapshot ?? $package->price;

        return [
            'id' => $package->id,
            'name' => $packageName,
            'code' => $packageCode,
            'name_display' => PaketUndangan::displayLabelFromCode($packageCode, $package->name_paket),
            'jenis_paket' => PaketUndangan::jenisPaketFromCode(
                $packageCode,
                $snapshot['jenis_paket'] ?? $package->jenis_paket
            ),
            'price' => $packagePrice,
            'currency' => 'IDR',
            'payment_status' => $invitation->payment_status,
            'is_active' => $invitation->isDomainActive(),
            'accessible_categories' => $package->accessibleCategories
                ->where('type', 'website')
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ]),
        ];
    }

    /**
     * Get domain information for user
     */
    private function getDomainInfo(User $user): ?array
    {
        if (!$user->settingOne || !$user->invitationOne) {
            return null;
        }

        $setting = $user->settingOne;
        $invitation = $user->invitationOne;

        return [
            'domain' => $setting->domain,
            'is_active' => $invitation->isDomainActive(),
            'expires_at' => $invitation->domain_expires_at,
            'days_until_expiry' => $invitation->getDaysUntilExpiry(),
            'payment_confirmed_at' => $invitation->payment_confirmed_at,
        ];
    }
}
