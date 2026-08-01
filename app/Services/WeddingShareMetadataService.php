<?php

namespace App\Services;

use App\Models\Galery;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WeddingShareMetadataService
{
    private const FRONTEND_BASE_URL = 'https://www.sena-digital.com';

    private const BACKEND_BASE_URL = 'https://cloud-api.sena-digital.com';

    private const FALLBACK_DESCRIPTION = 'Dengan penuh rasa syukur, kami mengundang Bapak/Ibu/Saudara/i untuk hadir dalam acara pernikahan kami.';

    private const FALLBACK_IMAGE_URL = self::FRONTEND_BASE_URL.'/assets/images/default-og-cover.jpg';

    public function __construct(
        private DomainService $domainService,
        private ReligionContentResolver $religionContentResolver
    ) {}

    public function resolve(string $domain): ?array
    {
        $domain = $this->domainService->normalizeToSlug($domain);

        if ($domain === '') {
            return null;
        }

        $ownerUserId = $this->domainService->resolveOwnerUserIdByDomain($domain);

        if (! $ownerUserId) {
            return null;
        }

        $user = $this->loadPublicWeddingUser((int) $ownerUserId);

        if (! $user || ! $this->canShare($user)) {
            return null;
        }

        $canonicalUrl = self::FRONTEND_BASE_URL.'/wedding/'.rawurlencode($domain);
        $cover = $this->resolveCover($user);

        return [
            'title' => $this->resolveTitle($user),
            'description' => $this->resolveDescription($user),
            'coverUrl' => $cover['url'],
            'imageType' => $cover['mime_type'],
            'frontendUrl' => $canonicalUrl,
        ];
    }

    private function loadPublicWeddingUser(int $ownerUserId): ?User
    {
        return User::with([
            'mempelaiOne',
            'settingOne',
            'invitationOne',
            'acara' => fn ($query) => $query->orderBy('tanggal_acara'),
            'qoute',
            'gallery' => function ($query) {
                $query->where('status', true)
                    ->where(function ($query) {
                        $query->whereNull('photo_type')
                            ->orWhere('photo_type', 'gallery')
                            ->orWhere('photo_type', 'photo');
                    })
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ])->find($ownerUserId);
    }

    private function canShare(User $user): bool
    {
        $invitation = $user->invitationOne;
        $paymentStatus = strtolower(trim((string) ($invitation?->payment_status ?? '')));
        $mempelaiPaymentStatus = strtoupper(trim((string) ($user->mempelaiOne?->kd_status ?? '')));

        $isPaymentConfirmed = in_array($paymentStatus, ['paid', 'confirmed'], true)
            || $invitation?->payment_confirmed_at !== null
            || $mempelaiPaymentStatus === 'SB';

        $isExpired = $invitation?->domain_expires_at
            ? now()->greaterThan($invitation->domain_expires_at)
            : false;

        return $invitation !== null && $isPaymentConfirmed && ! $isExpired;
    }

    private function resolveTitle(User $user): string
    {
        $mempelai = $user->mempelaiOne;
        $groomName = $this->firstFilled([
            $mempelai?->name_panggilan_pria,
            $mempelai?->name_lengkap_pria,
        ]);
        $brideName = $this->firstFilled([
            $mempelai?->name_panggilan_wanita,
            $mempelai?->name_lengkap_wanita,
        ]);

        if ($groomName && $brideName) {
            return "The Wedding of {$groomName} & {$brideName}";
        }

        return 'Undangan Pernikahan | Sena Digital';
    }

    private function resolveDescription(User $user): string
    {
        $content = $this->religionContentResolver->resolveForUser($user, [
            'guest_name' => '',
            'bride_name' => $user->mempelaiOne?->name_panggilan_wanita ?? $user->mempelaiOne?->name_lengkap_wanita ?? '',
            'groom_name' => $user->mempelaiOne?->name_panggilan_pria ?? $user->mempelaiOne?->name_lengkap_pria ?? '',
            'invitation_url' => '',
            'event_date' => '',
            'event_location' => '',
        ]);

        $description = $this->firstFilled([
            $content['resolved']['invitation_intro'] ?? null,
            $content['resolved']['opening_greeting'] ?? null,
            $content['resolved_content']['opening_text'] ?? null,
        ]);

        $description = $this->cleanDescription($description);

        return $description !== '' ? $description : self::FALLBACK_DESCRIPTION;
    }

    private function resolveCover(User $user): array
    {
        $mempelai = $user->mempelaiOne;
        $setting = $user->settingOne;

        $directCandidates = [
            data_get($setting, 'cover_photo_url'),
            data_get($mempelai, 'cover_photo_url'),
            data_get($mempelai, 'cover_photo'),
            data_get($setting, 'cover_photo'),
        ];

        foreach ($directCandidates as $candidate) {
            $resolved = $this->resolveImageCandidate($candidate);
            if ($resolved) {
                return $resolved;
            }
        }

        $galleries = $user->relationLoaded('gallery') ? $user->gallery : collect();
        $galleryBuckets = [
            $galleries->where('is_featured', true),
            $galleries->filter(fn (Galery $gallery): bool => $this->looksLikeCoverGallery($gallery)),
            $galleries,
        ];

        foreach ($galleryBuckets as $bucket) {
            foreach ($bucket as $gallery) {
                $resolved = $this->resolveGalleryImage($gallery);
                if ($resolved) {
                    return $resolved;
                }
            }
        }

        return [
            'url' => self::FALLBACK_IMAGE_URL,
            'mime_type' => 'image/jpeg',
        ];
    }

    private function resolveGalleryImage(Galery $gallery): ?array
    {
        if ($gallery->url_video && ! $gallery->photo && ! $gallery->file_path && ! $gallery->file_url) {
            return null;
        }

        $candidate = $this->firstFilled([
            $gallery->photo_url,
            $gallery->file_url,
            $gallery->file_path,
            $gallery->photo,
        ]);

        return $this->resolveImageCandidate($candidate, $gallery->mime_type);
    }

    private function resolveImageCandidate(?string $candidate, ?string $knownMimeType = null): ?array
    {
        $url = $this->absolutePublicImageUrl($candidate);

        if (! $url) {
            return null;
        }

        return [
            'url' => $url,
            'mime_type' => $this->resolveMimeType($candidate, $knownMimeType),
        ];
    }

    private function absolutePublicImageUrl(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (Str::startsWith($value, ['http://', 'https://'])) {
            if ($this->isUnsafePublicUrl($value)) {
                return null;
            }

            return Str::startsWith($value, 'http://')
                ? 'https://'.Str::after($value, 'http://')
                : $value;
        }

        $cleanPath = $this->normalizeStoragePath($value);

        if (! $cleanPath || ! Storage::disk('public')->exists($cleanPath)) {
            Log::warning('[WeddingShareMissingImageFile]', [
                'original_path' => $value,
                'clean_path' => $cleanPath,
            ]);

            return null;
        }

        return self::BACKEND_BASE_URL.'/storage/'.str_replace('%2F', '/', rawurlencode($cleanPath));
    }

    private function isUnsafePublicUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || Str::endsWith($host, '.test')
            || Str::endsWith($host, '.local');
    }

    private function resolveMimeType(?string $candidate, ?string $knownMimeType = null): ?string
    {
        if ($knownMimeType && Str::startsWith($knownMimeType, 'image/')) {
            return $knownMimeType;
        }

        $cleanPath = $this->normalizeStoragePath($candidate);

        if ($cleanPath && Storage::disk('public')->exists($cleanPath)) {
            $mimeType = Storage::disk('public')->mimeType($cleanPath);
            if ($mimeType && Str::startsWith($mimeType, 'image/')) {
                return $mimeType;
            }
        }

        $extension = strtolower(pathinfo((string) parse_url((string) $candidate, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }

    private function looksLikeCoverGallery(Galery $gallery): bool
    {
        $haystack = strtolower(trim(implode(' ', array_filter([
            $gallery->nama_foto,
            $gallery->description,
            $gallery->photo_type,
            $gallery->original_name,
            $gallery->photo,
            $gallery->file_path,
        ]))));

        return str_contains($haystack, 'cover') || str_contains($haystack, 'sampul');
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
        $path = preg_replace('#^public/#', '', $path);
        $path = ltrim($path, '/');

        return $path ?: null;
    }

    private function cleanDescription(?string $value): string
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/\{\{[^}]+\}\}/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value);

        return Str::limit($value, 180, '');
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
