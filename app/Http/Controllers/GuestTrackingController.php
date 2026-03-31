<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WeddingGuest;
use App\Models\Setting;
use App\Models\BukuTamu;
use App\Models\AttendanceScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GuestTrackingController extends Controller
{
    /**
     * Track guest visit when opening invitation
     * POST /v1/wedding-guests/track
     */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_name' => 'required|string|max:255',
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find user by domain
            $setting = Setting::where('domain', $request->domain)->first();

            if (!$setting) {
                return response()->json([
                    'message' => 'Wedding not found for this domain',
                ], 404);
            }

            $userId = $setting->user_id;
            $guestName = $request->guest_name;

            // Check if guest already exists
            $guest = WeddingGuest::where('user_id', $userId)
                ->where('guest_name', $guestName)
                ->first();

            if (!$guest) {
                // Create new guest record
                $guest = WeddingGuest::create([
                    'user_id' => $userId,
                    'guest_name' => $guestName,
                    'guest_token' => WeddingGuest::generateUniqueToken($guestName, $request->domain),
                    'domain' => $request->domain,
                    'first_visit_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } else {
                // Update visit info if already exists
                $guest->update([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return response()->json([
                'message' => 'Guest tracked successfully',
                'data' => [
                    'guest_token' => $guest->guest_token,
                    'guest_name' => $guest->guest_name,
                    'first_visit_at' => $guest->first_visit_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to track guest',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify guest QR token
     * GET /v1/wedding-guests/verify/{token}
     */
    public function verify(string $token): JsonResponse
    {
        try {
            $guest = WeddingGuest::where('guest_token', $token)->first();

            if (!$guest) {
                return response()->json([
                    'message' => 'Invalid guest token',
                ], 404);
            }

            return response()->json([
                'message' => 'Token verified successfully',
                'data' => [
                    'guest_id' => $guest->id,
                    'guest_name' => $guest->guest_name,
                    'domain' => $guest->domain,
                    'user_id' => $guest->user_id,
                    'attended' => $guest->attended,
                    'attended_at' => $guest->attended_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to verify token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Confirm attendance from QR scan
     * POST /v1/wedding-guests/confirm-attendance
     * Requires authentication
     */
    public function confirmAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_token' => 'required|string',
            'acara_id' => 'required|integer',
            'scan_type' => 'required|in:qr_code,manual',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find guest by token
            $guest = WeddingGuest::where('guest_token', $request->guest_token)->first();

            if (!$guest) {
                return response()->json([
                    'message' => 'Invalid guest token',
                ], 404);
            }

            // Check if already attended this event
            if ($guest->attended && $guest->attended_acara_id == $request->acara_id) {
                return response()->json([
                    'message' => 'Guest already marked as attended for this event',
                    'data' => [
                        'guest_name' => $guest->guest_name,
                        'attended_at' => $guest->attended_at,
                    ],
                ], 409);
            }

            // Mark guest as attended
            $guest->markAsAttended((int) $request->acara_id);

            // Create attendance scan record
            AttendanceScan::create([
                'user_id' => $guest->user_id,
                'acara_id' => $request->acara_id,
                'guest_name' => $guest->guest_name,
                'guest_identifier' => $guest->guest_token,
                'scan_type' => $request->scan_type,
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Update or create buku_tamu record with 'hadir' status
            $bukuTamu = BukuTamu::where('user_id', $guest->user_id)
                ->where('nama', $guest->guest_name)
                ->first();

            if ($bukuTamu) {
                $bukuTamu->update([
                    'status_kehadiran' => 'hadir',
                    'is_approved' => true,
                ]);
            } else {
                // Create new buku_tamu record if not exists
                BukuTamu::create([
                    'user_id' => $guest->user_id,
                    'nama' => $guest->guest_name,
                    'status_kehadiran' => 'hadir',
                    'is_approved' => true,
                ]);
            }

            return response()->json([
                'message' => 'Attendance confirmed successfully',
                'data' => [
                    'guest_name' => $guest->guest_name,
                    'attended_at' => $guest->attended_at,
                    'acara_id' => $request->acara_id,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to confirm attendance',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get wedding guest list
     * GET /v1/wedding-guests
     * Requires authentication
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $guests = WeddingGuest::where('user_id', $userId)
                ->orderBy('first_visit_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Guest list retrieved successfully',
                'data' => $guests,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve guest list',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
