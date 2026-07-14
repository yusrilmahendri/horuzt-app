<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdminUserCleanupService
{
    /**
     * Bersihkan data undangan/media user tanpa menghapus akun utama.
     */
    public function softDeleteUserData(User $user): array
    {
        return $this->executeCleanup($user, false);
    }

    /**
     * Hapus akun user beserta seluruh relasi data/media.
     */
    public function hardDeleteUser(User $user): array
    {
        return $this->executeCleanup($user, true);
    }

    private function executeCleanup(User $user, bool $deleteUserAccount): array
    {
        $userId = (int) $user->id;
        $storagePaths = $this->collectStoragePaths($userId);
        $storagePaths = array_values(array_unique(array_filter($storagePaths)));

        $summary = DB::transaction(function () use ($user, $userId, $deleteUserAccount) {
            $pernikahanIds = DB::table('pernikahans')->where('user_id', $userId)->pluck('id')->all();
            $mempelaiIds = DB::table('mempelais')->where('user_id', $userId)->pluck('id')->all();
            $acaraIds = DB::table('acaras')->where('user_id', $userId)->pluck('id')->all();
            $qouteIds = DB::table('qoutes')->where('user_id', $userId)->pluck('id')->all();
            $resultPernikahanQuery = DB::table('result_pernikahans')
                ->whereIn('pernikahan_id', $pernikahanIds)
                ->orWhereIn('mempelai_id', $mempelaiIds)
                ->orWhereIn('acara_id', $acaraIds)
                ->orWhereIn('qoute_id', $qouteIds);
            $pengunjungIds = (clone $resultPernikahanQuery)->pluck('pengunjung_id')->all();

            $summary = [
                'deleted_relations' => [
                    'attendance_scans' => DB::table('attendance_scans')->where('user_id', $userId)->delete(),
                    'wedding_guests' => DB::table('wedding_guests')->where('user_id', $userId)->delete(),
                    'result_pernikahans' => $resultPernikahanQuery->delete(),
                    'pengunjungs' => empty($pengunjungIds)
                        ? 0
                        : DB::table('pengunjungs')->whereIn('id', $pengunjungIds)->delete(),
                    'acaras' => DB::table('acaras')->where('user_id', $userId)->delete(),
                    'countdown_acaras' => DB::table('countdown_acaras')->where('user_id', $userId)->delete(),
                    'galeries' => DB::table('galeries')->where('user_id', $userId)->delete(),
                    'ceritas' => DB::table('ceritas')->where('user_id', $userId)->delete(),
                    'qoutes' => DB::table('qoutes')->where('user_id', $userId)->delete(),
                    'buku_tamus' => DB::table('buku_tamus')->where('user_id', $userId)->delete(),
                    'ucapans' => DB::table('ucapans')->where('user_id', $userId)->delete(),
                    'filter_undangans' => DB::table('filter_undangans')->where('user_id', $userId)->delete(),
                    'result_themas' => DB::table('result_themas')->where('user_id', $userId)->delete(),
                    'mempelais' => DB::table('mempelais')->where('user_id', $userId)->delete(),
                    'rekenings' => DB::table('rekenings')->where('user_id', $userId)->delete(),
                    'settings' => DB::table('settings')->where('user_id', $userId)->delete(),
                    'testimonis' => DB::table('testimonis')->where('user_id', $userId)->delete(),
                    'pernikahans' => DB::table('pernikahans')->where('user_id', $userId)->delete(),
                ],
                'deleted_user_account' => 0,
            ];

            $invitationIds = Invitation::query()
                ->where('user_id', $userId)
                ->pluck('id')
                ->all();

            $summary['deleted_relations']['komentars'] = empty($invitationIds)
                ? 0
                : DB::table('komentars')->whereIn('invitation_id', $invitationIds)->delete();

            if ($deleteUserAccount) {
                $summary['deleted_relations']['payment_logs'] = DB::table('payment_logs')
                    ->where('user_id', $userId)
                    ->orWhereIn('invitation_id', $invitationIds)
                    ->delete();

                $summary['deleted_relations']['invitations'] = DB::table('invitations')->where('user_id', $userId)->delete();
                $summary['deleted_relations']['orders'] = DB::table('orders')->where('user_id', $userId)->delete();
                $summary['deleted_relations']['midtrans_transactions'] = DB::table('midtrans_transactions')->where('user_id', $userId)->delete();
                $summary['deleted_relations']['tripay_transactions'] = DB::table('tripay_transactions')->where('user_id', $userId)->delete();
                $summary['deleted_relations']['transaction_tagihans'] = DB::table('transaction_tagihans')->where('user_id', $userId)->delete();
                $summary['deleted_relations']['personal_access_tokens'] = DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $userId)
                    ->delete();
                $summary['deleted_relations']['model_has_roles'] = DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $userId)
                    ->delete();
                $summary['deleted_relations']['model_has_permissions'] = DB::table('model_has_permissions')
                    ->where('model_type', User::class)
                    ->where('model_id', $userId)
                    ->delete();

                $summary['deleted_user_account'] = $user->delete() ? 1 : 0;
            } else {
                $user->forceFill([
                    'profile_photo' => null,
                    'kode_pemesanan' => null,
                    'updated_at' => Carbon::now(),
                ])->save();
            }

            return $summary;
        });

        $summary['storage'] = $this->cleanupStorage($storagePaths, $userId);
        $summary['total_deleted_relations'] = array_sum($summary['deleted_relations']);

        return $summary;
    }

    private function collectStoragePaths(int $userId): array
    {
        $paths = [];

        $userProfile = DB::table('users')->where('id', $userId)->value('profile_photo');
        if (is_string($userProfile) && $userProfile !== '') {
            $paths[] = $userProfile;
        }

        $mempelaiFiles = DB::table('mempelais')
            ->where('user_id', $userId)
            ->select(['cover_photo', 'photo_pria', 'photo_wanita'])
            ->get();

        foreach ($mempelaiFiles as $row) {
            foreach (['cover_photo', 'photo_pria', 'photo_wanita'] as $column) {
                $value = $row->{$column} ?? null;
                if (is_string($value) && $value !== '') {
                    $paths[] = $value;
                }
            }
        }

        $galleryFiles = DB::table('galeries')
            ->where('user_id', $userId)
            ->select(['photo', 'file_path'])
            ->get();

        foreach ($galleryFiles as $row) {
            foreach (['photo', 'file_path'] as $column) {
                $value = $row->{$column} ?? null;
                if (is_string($value) && $value !== '') {
                    $paths[] = $value;
                }
            }
        }

        $rekeningFiles = DB::table('rekenings')->where('user_id', $userId)->pluck('photo_rek')->all();
        foreach ($rekeningFiles as $value) {
            if (is_string($value) && $value !== '') {
                $paths[] = $value;
            }
        }

        $musicFiles = DB::table('settings')->where('user_id', $userId)->pluck('musik')->all();
        foreach ($musicFiles as $value) {
            if (is_string($value) && $value !== '') {
                $paths[] = $value;
            }
        }

        return array_map([$this, 'normalizeStoragePath'], $paths);
    }

    private function cleanupStorage(array $paths, int $userId): array
    {
        $deletedFiles = 0;
        $missingFiles = 0;
        $failedDeletes = [];

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            try {
                if (!Storage::disk('public')->exists($path)) {
                    $missingFiles++;
                    continue;
                }

                if (Storage::disk('public')->delete($path)) {
                    $deletedFiles++;
                } else {
                    $failedDeletes[] = $path;
                }
            } catch (Throwable $exception) {
                $failedDeletes[] = $path;
            }
        }

        $userDirectories = [
            "profiles/{$userId}",
            "music/{$userId}",
            "gallery/{$userId}",
            "users/{$userId}",
        ];

        $deletedDirectories = 0;
        foreach ($userDirectories as $directory) {
            try {
                if (Storage::disk('public')->exists($directory)) {
                    if (Storage::disk('public')->deleteDirectory($directory)) {
                        $deletedDirectories++;
                    }
                }
            } catch (Throwable $exception) {
                // Abaikan exception agar proses tetap idempotent.
            }
        }

        return [
            'deleted_files' => $deletedFiles,
            'missing_files' => $missingFiles,
            'failed_files' => $failedDeletes,
            'deleted_directories' => $deletedDirectories,
        ];
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        $normalized = trim($path);

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return null;
        }

        $normalized = preg_replace('#^/storage/#', '', $normalized);
        $normalized = preg_replace('#^storage/#', '', $normalized);
        $normalized = preg_replace('#^public/#', '', $normalized);

        return ltrim($normalized ?? '', '/');
    }
}
