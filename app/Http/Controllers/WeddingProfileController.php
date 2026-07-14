<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeddingProfile\WeddingProfileResource;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WeddingProfileController extends Controller
{
    public function __construct(private DomainService $domainService)
    {
        $this->middleware('auth:sanctum')->except(['publicProfile', 'publicProfileByDomain']);
    }

    /**
     * Get comprehensive wedding profile data for authenticated user
     * This is the core endpoint that aggregates all wedding-related data
     */
    public function show(): JsonResponse
    {
        try {
            $userId = Auth::id();

            // Optimized query with eager loading to prevent N+1 queries
            $user = User::with([
                // Core wedding data
                'mempelaiOne',
                'settingOne',
                'filterUndanganOne',
                'invitationOne.paketUndangan.accessibleCategories',
                'invitationOne.komentars' => function ($query) {
                    $query->latest()->limit(50);
                },

                // Collection data
                'acara.countdownAcara',
                'countdownAcara',
                'cerita',
                'qoute',
                'gallery' => function ($query) {
                    $query->where(function ($query) {
                            $query->whereNull('photo_type')
                                ->orWhere('photo_type', 'gallery');
                        })
                        ->orderByDesc('is_featured')
                        ->orderBy('sort_order');
                },
                'collage' => function ($query) {
                    $query->where('photo_type', 'collage')
                        ->orderByDesc('is_featured')
                        ->orderBy('sort_order');
                },
                'rekening.bank',
                'testimoni',
                'bukuTamu',
                'ucapan',

                // Theme relationships
                'thema',
                'selectedTheme.jenisThema.category',
            ])->findOrFail($userId);

            return response()->json([
                'data' => new WeddingProfileResource($user, false, null, (int) $userId),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User profile not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve wedding profile data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get wedding profile summary statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $stats = [
                'profile_completion' => $this->calculateProfileCompletion($userId),
                'total_events' => Auth::user()->acara()->count(),
                'total_stories' => Auth::user()->cerita()->count(),
                'total_quotes' => Auth::user()->qoute()->count(),
                'total_gallery_items' => Auth::user()->gallery()->count(),
                'total_bank_accounts' => Auth::user()->rekening()->count(),
                'total_guest_wishes' => Auth::user()->ucapan()->count(),
                'total_guest_book_entries' => Auth::user()->bukuTamu()->count(),
                'invitation_status' => Auth::user()->invitationOne?->status ?? 'not_started',
            ];

            return response()->json([
                'data' => $stats,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve wedding profile statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(int $userId): array
    {
        $user = Auth::user();
        $completionItems = [
            'basic_info' => ! empty($user->name) && ! empty($user->phone),
            'mempelai' => $user->mempelaiOne !== null,
            'acara' => $user->acara()->exists(),
            'cerita' => $user->cerita()->exists(),
            'gallery' => $user->gallery()->exists(),
            'qoute' => $user->qoute()->exists(),
            'rekening' => $user->rekening()->exists(),
            'settings' => $user->settingOne !== null,
            'filter_undangan' => $user->filterUndanganOne !== null,
            'invitation_package' => $user->invitationOne !== null,
        ];

        $completed = array_filter($completionItems);
        $totalItems = count($completionItems);
        $completedItems = count($completed);
        $percentage = round(($completedItems / $totalItems) * 100, 2);

        return [
            'percentage' => $percentage,
            'completed_items' => $completedItems,
            'total_items' => $totalItems,
            'missing_items' => array_keys(array_filter($completionItems, fn ($item) => ! $item)),
        ];
    }

    /**
     * Get wedding profile data for public display (by user ID, domain, or couple nicknames)
     * This endpoint can be used for public wedding invitation display
     * Supports: ?user_id=4, ?domain=example, ?couple=anton-keok
     */
    public function publicProfile(Request $request): JsonResponse
    {
        try {
            $domain = $this->extractDomain((string) $request->query('domain', ''));

            if ($domain === '') {
                return response()->json([
                    'message' => 'Parameter domain wajib diisi.',
                ], 422);
            }

            $ownerUserId = $this->resolveOwnerUserIdByDomain($domain);

            if (! $ownerUserId) {
                return response()->json([
                    'message' => 'Wedding profile not found for this domain.',
                ], 404);
            }

            $user = $this->loadPublicWeddingUser((int) $ownerUserId);
            $paymentGuard = $this->paymentConfirmedResponse($user);

            if ($paymentGuard) {
                return $paymentGuard;
            }

            return response()->json([
                'data' => new WeddingProfileResource($user, true, $domain, (int) $ownerUserId), // Pass true for public view
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding profile not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('[PublicWeddingProfile] Failed to retrieve wedding profile.', [
                'domain' => $domain ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 'PUBLIC_WEDDING_LOAD_FAILED',
                'message' => 'Unable to load wedding invitation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
    }

    /**
     * Get wedding profile by domain (SEO-friendly URL)
     * Route: /v1/wedding-profile/couple/{domain}
     * Example: /v1/wedding-profile/couple/domainkuasna
     */
    public function publicProfileByDomain(string $domain): JsonResponse
    {
        try {
            // Validate domain parameter
            $domain = $this->extractDomain($domain);

            if ($domain === '') {
                return response()->json([
                    'message' => 'Domain parameter is required.',
                ], 400);
            }

            $ownerUserId = $this->resolveOwnerUserIdByDomain($domain);

            if (! $ownerUserId) {
                return response()->json([
                    'message' => 'Wedding profile not found for this domain.',
                ], 404);
            }

            $user = $this->loadPublicWeddingUser((int) $ownerUserId);
            $paymentGuard = $this->paymentConfirmedResponse($user);

            if ($paymentGuard) {
                return $paymentGuard;
            }

            return response()->json([
                'data' => new WeddingProfileResource($user, true, $domain, (int) $ownerUserId),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding profile not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('[PublicWeddingProfile] Failed to retrieve wedding profile by domain.', [
                'domain' => $domain ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 'PUBLIC_WEDDING_LOAD_FAILED',
                'message' => 'Unable to load wedding invitation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
    }

    private function loadPublicWeddingUser(int $ownerUserId): User
    {
        return User::with([
            'mempelaiOne',
            'settingOne',
            'filterUndanganOne',
            'invitationOne.paketUndangan.accessibleCategories',
            'invitationOne.komentars' => function ($query) {
                $query->latest()->limit(50);
            },
            'acara.countdownAcara',
            'cerita',
            'qoute',
            'gallery' => function ($query) {
                $query->where('status', true)
                    ->where(function ($query) {
                        $query->whereNull('photo_type')
                            ->orWhere('photo_type', 'gallery');
                    })
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order');
            },
            'collage' => function ($query) {
                $query->where('status', true)
                    ->where('photo_type', 'collage')
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order');
            },
            'rekening.bank',
            'testimoni',
            'bukuTamu',
            'thema',
            'selectedTheme.jenisThema.category',
            'ucapan' => function ($query) {
                $query->whereNotNull('user_id');
            },
        ])->findOrFail($ownerUserId);
    }

    private function paymentConfirmedResponse(User $user): ?JsonResponse
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

        if (! $invitation || ! $isPaymentConfirmed || $isExpired) {
            return response()->json([
                'code' => 'PAYMENT_NOT_CONFIRMED',
                'message' => 'Pembayaran belum dikonfirmasi.',
            ], 403);
        }

        return null;
    }

    private function extractDomain(string $domain): string
    {
        return $this->domainService->normalizeToSlug($domain);
    }

    private function resolveOwnerUserIdByDomain(string $domain): ?int
    {
        return $this->domainService->resolveOwnerUserIdByDomain($domain);
    }
}
