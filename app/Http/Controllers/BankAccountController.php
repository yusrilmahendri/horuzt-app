<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Resources\Rekening\RekeningCollection;
use App\Http\Resources\Rekening\RekeningResource;
use App\Models\Bank;
use App\Models\MetodeTransaction;
use App\Models\Rekening;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BankAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all bank accounts for the authenticated user
     */
    public function index(): RekeningCollection
    {
        $bankAccounts = Rekening::forUser(Auth::id())
            ->with('bank')
            ->latest()
            ->get();

        return new RekeningCollection($bankAccounts);
    }

    /**
     * Store a new bank account
     */
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $userId = Auth::id();
        
        // Check account limit (max 2 per user)
        $existingCount = Rekening::forUser($userId)->count();
        if ($existingCount >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menambah lebih dari 2 rekening bank.',
                'error_code' => 'ACCOUNT_LIMIT_EXCEEDED'
            ], 422);
        }

        $validated = $request->validated();

        // Check for duplicate account number for this user
        $existingAccount = Rekening::forUser($userId)
            ->where('nomor_rekening', $validated['nomor_rekening'])
            ->where('kode_bank', $validated['kode_bank'])
            ->first();

        if ($existingAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor rekening ini sudah terdaftar untuk bank yang sama.',
                'error_code' => 'DUPLICATE_ACCOUNT'
            ], 422);
        }

        // Get bank information
        $bank = Bank::where('kode_bank', $validated['kode_bank'])->first();
        if (!$bank) {
            return response()->json([
                'success' => false,
                'message' => 'Bank tidak ditemukan.',
                'error_code' => 'BANK_NOT_FOUND'
            ], 404);
        }

        // Get default payment method
        $paymentMethod = MetodeTransaction::first();

        // Handle file upload
        $photoPath = null;
        if ($request->hasFile('photo_rek')) {
            $photoPath = $request->file('photo_rek')->store('bank-logos', 'public');
        }

        // Create bank account
        $bankAccount = Rekening::create([
            'user_id' => $userId,
            'kode_bank' => $validated['kode_bank'],
            'email' => Auth::user()->email,
            'nomor_rekening' => $validated['nomor_rekening'],
            'nama_bank' => $bank->name,
            'nama_pemilik' => $validated['nama_pemilik'],
            'methode_pembayaran' => $paymentMethod?->name ?? 'default',
            'id_methode_pembayaran' => $paymentMethod?->id ?? 1,
            'photo_rek' => $photoPath,
        ]);

        $bankAccount->load('bank');

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil ditambahkan.',
            'data' => new RekeningResource($bankAccount)
        ], 201);
    }

    /**
     * Show specific bank account
     */
    public function show(int $id): JsonResponse
    {
        $bankAccount = Rekening::forUser(Auth::id())
            ->with('bank')
            ->find($id);

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening bank tidak ditemukan.',
                'error_code' => 'ACCOUNT_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new RekeningResource($bankAccount)
        ]);
    }

    /**
     * Update specific bank account
     */
    public function update(UpdateBankAccountRequest $request, int $id): JsonResponse
    {
        $bankAccount = Rekening::forUser(Auth::id())->find($id);

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening bank tidak ditemukan.',
                'error_code' => 'ACCOUNT_NOT_FOUND'
            ], 404);
        }

        $validated = $request->validated();

        // Check for duplicate if account number or bank is being changed
        if (isset($validated['nomor_rekening']) || isset($validated['kode_bank'])) {
            $nomor = $validated['nomor_rekening'] ?? $bankAccount->nomor_rekening;
            $kode = $validated['kode_bank'] ?? $bankAccount->kode_bank;
            
            $duplicate = Rekening::forUser(Auth::id())
                ->where('nomor_rekening', $nomor)
                ->where('kode_bank', $kode)
                ->where('id', '!=', $id)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor rekening ini sudah terdaftar untuk bank yang sama.',
                    'error_code' => 'DUPLICATE_ACCOUNT'
                ], 422);
            }
        }

        // Update bank name if bank code changed
        if (isset($validated['kode_bank']) && $validated['kode_bank'] !== $bankAccount->kode_bank) {
            $bank = Bank::where('kode_bank', $validated['kode_bank'])->first();
            if ($bank) {
                $validated['nama_bank'] = $bank->name;
            }
        }

        // Handle file upload
        if ($request->hasFile('photo_rek')) {
            // Delete old photo
            if ($bankAccount->photo_rek) {
                Storage::disk('public')->delete($bankAccount->photo_rek);
            }
            
            $validated['photo_rek'] = $request->file('photo_rek')->store('bank-logos', 'public');
        }

        $bankAccount->update($validated);
        $bankAccount->load('bank');

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil diperbarui.',
            'data' => new RekeningResource($bankAccount)
        ]);
    }

    /**
     * Delete specific bank account
     */
    public function destroy(int $id): JsonResponse
    {
        $bankAccount = Rekening::forUser(Auth::id())->find($id);

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening bank tidak ditemukan.',
                'error_code' => 'ACCOUNT_NOT_FOUND'
            ], 404);
        }

        // Delete associated photo
        if ($bankAccount->photo_rek) {
            Storage::disk('public')->delete($bankAccount->photo_rek);
        }

        $bankAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil dihapus.'
        ]);
    }

    /**
     * Get bank account statistics for user
     */
    public function statistics(): JsonResponse
    {
        $userId = Auth::id();
        $total = Rekening::forUser($userId)->count();
        $hasLogo = Rekening::forUser($userId)->whereNotNull('photo_rek')->count();
        $canAddMore = $total < 2;

        return response()->json([
            'success' => true,
            'data' => [
                'total_accounts' => $total,
                'accounts_with_logo' => $hasLogo,
                'can_add_more' => $canAddMore,
                'remaining_slots' => max(0, 2 - $total)
            ]
        ]);
    }
}