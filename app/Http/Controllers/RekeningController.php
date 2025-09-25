<?php
namespace App\Http\Controllers;

use App\Http\Resources\Rekening\RekeningCollection;
use App\Http\Resources\Rekening\RekeningResource;
use App\Models\Bank;
use App\Models\MetodeTransaction;
use App\Models\Rekening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RekeningController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $userId    = Auth::id();
        $rekenings = Rekening::where('user_id', $userId)->with('bank')->get();

        return new RekeningCollection($rekenings);
    }

    public function store(Request $request)
    {
        try {
            // Determine target user - admin routes can specify user_id, user routes use authenticated user ID
            $targetUserId = $request->filled('user_id') ? $request->input('user_id') : Auth::id();

            // Check user account limit before adding
            $existingCount = Rekening::where('user_id', $targetUserId)->count();
            if ($existingCount >= 2) {
                return response()->json([
                    'message' => 'User tidak boleh memiliki lebih dari 2 rekening.',
                ], 422);
            }

            $validated = $request->validate([
                'user_id'        => 'nullable|integer|exists:users,id', // Only for admin
                'kode_bank'      => 'required|string',
                'nomor_rekening' => 'required|string',
                'nama_pemilik'   => 'required|string',
                'photo_rek'      => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            ]);

            // Normalize and validate bank code
            $kodeBankInput = $validated['kode_bank'];
            if (is_numeric($kodeBankInput)) {
                $normalizedKodeBank = str_pad($kodeBankInput, 3, '0', STR_PAD_LEFT);
            } else {
                $normalizedKodeBank = $kodeBankInput;
            }

            $bank = Bank::where('kode_bank', $normalizedKodeBank)->first();
            if (!$bank) {
                return response()->json([
                    'message' => "Bank dengan kode '{$kodeBankInput}' tidak ditemukan. Kode bank yang valid: " . Bank::pluck('kode_bank')->implode(', '),
                    'errors' => [
                        'kode_bank' => ["Bank code '{$kodeBankInput}' does not exist."]
                    ]
                ], 422);
            }

            // Get user and payment method data
            $targetUser = $targetUserId === Auth::id() ? Auth::user() : \App\Models\User::find($targetUserId);
            $idMethodePembayaran = MetodeTransaction::pluck('id')->first();
            $methodePembayaran = MetodeTransaction::pluck('name')->first();

            // Create rekening record
            $rekening = new Rekening();
            $rekening->user_id = $targetUserId;
            $rekening->email = $targetUser->email;
            $rekening->methode_pembayaran = $methodePembayaran;
            $rekening->id_methode_pembayaran = $idMethodePembayaran;
            $rekening->nama_bank = $bank->name;
            $rekening->kode_bank = $normalizedKodeBank;
            $rekening->nomor_rekening = $validated['nomor_rekening'];
            $rekening->nama_pemilik = $validated['nama_pemilik'];

            // Handle photo upload
            if (isset($validated['photo_rek']) && $validated['photo_rek']->isValid()) {
                $photoPath = $validated['photo_rek']->store('photos', 'public');
                $rekening->photo_rek = $photoPath;
            }

            $rekening->save();
            $rekening->load('bank');

            return response()->json([
                'data' => new RekeningResource($rekening),
                'message' => 'Rekening berhasil ditambahkan!',
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed!',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menambahkan rekening.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'kode_bank'      => 'required|string',
                'nomor_rekening' => 'required|string',
                'nama_pemilik'   => 'required|string',
                'photo_rek'      => 'nullable|file|mimes:jpeg,png,jpg|max:2048',
            ]);

            $userId = Auth::id();

            // Find rekening and verify ownership
            $rekening = Rekening::where('id', $id)->where('user_id', $userId)->first();
            if (!$rekening) {
                return response()->json([
                    'message' => 'Rekening not found or does not belong to the user.',
                ], 404);
            }

            // Normalize and validate bank code
            $kodeBankInput = $validated['kode_bank'];
            if (is_numeric($kodeBankInput)) {
                $normalizedKodeBank = str_pad($kodeBankInput, 3, '0', STR_PAD_LEFT);
            } else {
                $normalizedKodeBank = $kodeBankInput;
            }

            $bank = Bank::where('kode_bank', $normalizedKodeBank)->first();
            if (!$bank) {
                return response()->json([
                    'message' => "Bank dengan kode '{$kodeBankInput}' tidak ditemukan. Kode bank yang valid: " . Bank::pluck('kode_bank')->implode(', '),
                    'errors' => [
                        'kode_bank' => ["Bank code '{$kodeBankInput}' does not exist."]
                    ]
                ], 422);
            }

            // Update rekening data
            $rekening->kode_bank = $normalizedKodeBank;
            $rekening->nomor_rekening = $validated['nomor_rekening'];
            $rekening->nama_pemilik = $validated['nama_pemilik'];
            $rekening->nama_bank = $bank->name;

            // Handle photo upload
            if (isset($validated['photo_rek']) && $validated['photo_rek']->isValid()) {
                $photoPath = $validated['photo_rek']->store('photos', 'public');
                $rekening->photo_rek = $photoPath;
            }

            $rekening->save();
            $rekening->load('bank');

            return response()->json([
                'data' => new RekeningResource($rekening),
                'message' => 'Rekening berhasil diperbarui!',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed. Please check the form inputs.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui rekening.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $userId = Auth::id();
        $rekening = Rekening::where('id', $id)->where('user_id', $userId)->first();

        if (!$rekening) {
            return response()->json([
                'message' => 'Rekening not found or does not belong to the user.',
            ], 404);
        }

        $rekening->delete();

        return response()->json([
            'message' => 'Rekening deleted successfully.',
        ], 200);
    }
}
