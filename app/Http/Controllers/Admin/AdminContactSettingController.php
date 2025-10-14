<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminContactSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminContactSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $setting = AdminContactSetting::first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'No contact settings found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact settings retrieved successfully',
            'data' => $setting
        ], 200);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'host_email' => 'nullable|email|max:255',
            'email' => 'nullable|email|max:255',
            'whatsapp' => 'nullable|string|max:255',
            'email_password' => 'nullable|string',
            'whatsapp_token' => 'nullable|string',
            'whatsapp_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $setting = AdminContactSetting::first();

        if (!$setting) {
            $setting = AdminContactSetting::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Contact settings created successfully',
                'data' => $setting
            ], 201);
        }

        $setting->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contact settings updated successfully',
            'data' => $setting
        ], 200);
    }

    public function destroy(): JsonResponse
    {
        $setting = AdminContactSetting::first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'No contact settings found',
            ], 404);
        }

        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact settings deleted successfully'
        ], 200);
    }
}
