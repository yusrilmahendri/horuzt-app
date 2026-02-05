<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeddingProfile\WeddingProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WeddingProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
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
                'invitationOne.paketUndangan',
                'invitationOne.komentars' => function ($query) {
                    $query->latest()->limit(50);
                },

                // Collection data
                'acara.countdownAcara',
                'countdownAcara',
                'cerita',
                'qoute',
                'gallery',
                'rekening.bank',
                'testimoni',
                'bukuTamu',
                'ucapan',

                // Theme relationships
                'thema',
                'selectedTheme.jenisThema.category',
            ])->findOrFail($userId);

            return response()->json([
                'data' => new WeddingProfileResource($user),
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
            $userId = null;

            // Check if accessing by user_id, domain, or couple nicknames
            if ($request->has('user_id')) {
                $userId = $request->get('user_id');
            } elseif ($request->has('domain')) {
                $domain = $request->get('domain');
                $setting = \App\Models\Setting::where('domain', $domain)->first();
                $userId = $setting?->user_id;
            } elseif ($request->has('couple')) {
                // Handle couple nicknames format: "anton-keok"
                $coupleNames = $request->get('couple');
                $names = explode('-', $coupleNames);

                if (count($names) === 2) {
                    $namaPria = trim($names[0]);
                    $namaWanita = trim($names[1]);

                    // Find user by matching both bride and groom nicknames
                    $mempelai = \App\Models\Mempelai::where('name_panggilan_pria', $namaPria)
                        ->where('name_panggilan_wanita', $namaWanita)
                        ->first();

                    $userId = $mempelai?->user_id;
                }
            }

            if (! $userId) {
                return response()->json([
                    'message' => 'Wedding profile not found. Please check the URL parameters.',
                ], 404);
            }

            // Only show completed invitations for public view
            $user = User::with([
                'mempelaiOne',
                'settingOne',
                'filterUndanganOne',
                'invitationOne.paketUndangan',
                'acara',
                'cerita',
                'qoute',
                'gallery' => function ($query) {
                    $query->where('status', true); // Only show active gallery items
                },
                'rekening.bank',
                'testimoni', // Added missing testimoni relationship
                'bukuTamu', // Added missing bukuTamu relationship
                'thema', // Added missing thema relationship
                'ucapan' => function ($query) {
                    $query->whereNotNull('user_id'); // Only user's own ucapan
                },
            ])->findOrFail($userId);

            // Check if invitation is completed
            if ($user->invitationOne?->status !== 'step3') {
                return response()->json([
                    'message' => 'Wedding invitation is not yet available for public viewing.',
                ], 403);
            }

            return response()->json([
                'data' => new WeddingProfileResource($user, true), // Pass true for public view
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding profile not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve wedding profile.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
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
            if (empty(trim($domain))) {
                return response()->json([
                    'message' => 'Domain parameter is required.',
                ], 400);
            }

            // Find setting by domain (case-insensitive)
            $setting = \App\Models\Setting::whereRaw('LOWER(domain) = ?', [strtolower(trim($domain))])
                ->first();

            if (! $setting || ! $setting->user_id) {
                return response()->json([
                    'message' => 'Wedding profile not found for this domain.',
                ], 404);
            }

            // Load user with all relationships
            $user = User::with([
                'mempelaiOne',
                'settingOne',
                'filterUndanganOne',
                'invitationOne.paketUndangan',
                'acara',
                'cerita',
                'qoute',
                'gallery' => function ($query) {
                    $query->where('status', true); // Only show active gallery items
                },
                'rekening.bank',
                'testimoni',
                'bukuTamu',
                'thema',
                'selectedTheme.jenisThema.category', // Add user's selected theme with category
                'ucapan' => function ($query) {
                    $query->whereNotNull('user_id'); // Only user's own ucapan
                },
            ])->findOrFail($setting->user_id);

            // Check if invitation is completed
            if ($user->invitationOne?->status !== 'step3') {
                return response()->json([
                    'message' => 'Wedding invitation is not yet available for public viewing.',
                ], 403);
            }

            // Check payment status from mempelai
            $paymentStatus = $user->mempelaiOne?->kd_status;

            // If status is "MK" (Menunggu Konfirmasi), return empty data
            if ($paymentStatus === 'MK') {
                return response()->json([
                    'data' => [],
                    'message' => 'Payment is still pending confirmation.',
                ], 200);
            }

            // If status is "SB" (Sudah Bayar), return full data
            if ($paymentStatus === 'SB') {
                return response()->json([
                    'data' => new WeddingProfileResource($user, true), // Pass true for public view
                ], 200);
            }

            // If no payment status or unknown status, return error
            return response()->json([
                'message' => 'Wedding profile payment status is not valid.',
            ], 403);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wedding profile not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve wedding profile.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
