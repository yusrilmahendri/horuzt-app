<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ReligionContentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReligionContentController extends Controller
{
    public function __construct(private ReligionContentResolver $resolver)
    {
        $this->middleware('auth:sanctum');
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'message' => 'Konten agama berhasil diambil.',
            'data' => $this->resolver->resolveForUser(Auth::user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $rules = [
            'religion_code' => ['nullable', 'string', 'max:50'],
        ];

        foreach ($this->resolver->fields() as $field) {
            $rules[$field] = ['nullable', 'string'];
        }

        $validated = $request->validate($rules);

        if (array_key_exists('religion_code', $validated) && $validated['religion_code'] !== null) {
            $normalized = $this->resolver->normalize($validated['religion_code']);

            if ($normalized === null) {
                return response()->json([
                    'message' => 'Kode agama tidak valid.',
                    'errors' => [
                        'religion_code' => ['Kode agama tidak valid.'],
                    ],
                ], 422);
            }

            $validated['religion_code'] = $normalized;
        }

        $payload = [];
        if (array_key_exists('religion_code', $validated)) {
            $payload['religion_code'] = $validated['religion_code'];
        }

        foreach ($this->resolver->fields() as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$this->resolver->customColumn($field)] = $this->inputPreservingEmptyString($request, $field, $validated[$field]);
            }
        }

        Setting::updateOrCreate(['user_id' => Auth::id()], $payload);

        return response()->json([
            'message' => 'Konten agama berhasil diperbarui.',
            'data' => $this->resolver->resolveForUser(Auth::user()->fresh(['settingOne', 'pernikahan', 'qoute'])),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*' => ['required', 'string'],
        ]);

        $fields = $validated['fields'] ?? $this->resolver->fields();
        $invalidFields = array_values(array_diff($fields, $this->resolver->fields()));

        if ($invalidFields !== []) {
            return response()->json([
                'message' => 'Field konten agama tidak valid.',
                'errors' => [
                    'fields' => ['Field tidak valid: '.implode(', ', $invalidFields)],
                ],
            ], 422);
        }

        $payload = [];
        foreach ($fields as $field) {
            $payload[$this->resolver->customColumn($field)] = null;
        }

        Setting::updateOrCreate(['user_id' => Auth::id()], $payload);

        return response()->json([
            'message' => 'Konten agama berhasil direset.',
            'data' => $this->resolver->resolveForUser(Auth::user()->fresh(['settingOne', 'pernikahan', 'qoute'])),
        ]);
    }

    private function inputPreservingEmptyString(Request $request, string $key, mixed $fallback): mixed
    {
        $raw = json_decode($request->getContent(), true);

        if (is_array($raw) && array_key_exists($key, $raw) && $raw[$key] === '') {
            return '';
        }

        return $fallback;
    }
}
