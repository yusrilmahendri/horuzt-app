<?php

namespace App\Http\Controllers;

use App\Models\AdminContactSetting;
use Illuminate\Http\JsonResponse;

class ContactSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $setting = AdminContactSetting::first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Contact settings not available',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact settings retrieved successfully',
            'data' => $setting->toUserArray()
        ], 200);
    }
}
