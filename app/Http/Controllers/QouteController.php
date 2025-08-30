<?php
namespace App\Http\Controllers;

use App\Models\Qoute;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QouteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'  => 'required|string|max:255',
            'qoute' => 'required|string|max:500',
        ]);

        $userId = Auth::id();

        $qoute          = new Qoute();
        $qoute->user_id = $userId;
        $qoute->name    = $validatedData['name'];
        $qoute->qoute   = $validatedData['qoute'];

        if ($qoute->save()) {
            return response()->json([
                'message' => 'Qoute berhasil disimpan!',
                'data'    => $qoute,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan qoute.',
            ], 500);
        }
    }

    /**
     * Get all qoute for the authenticated user
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $qoutes = Qoute::where('user_id', $userId)->orderByDesc('id')->get();
        return response()->json([
            'data'    => $qoutes,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update qoute milik user yang sedang login
     */
    public function update(Request $request)
    {
        $userId = Auth::id();
        $id     = $request->input('id');
        if (! $id) {
            return response()->json([
                'message' => 'Parameter id wajib diisi.',
            ], 400);
        }
        $qoute = Qoute::where('user_id', $userId)->where('id', $id)->first();
        if (! $qoute) {
            return response()->json([
                'message' => 'Qoute tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'qoute' => 'required|string|max:500',
        ]);

        $qoute->name  = $validated['name'];
        $qoute->qoute = $validated['qoute'];
        $qoute->save();

        return response()->json([
            'message' => 'Qoute berhasil diupdate.',
            'data'    => $qoute,
        ]);

    }

    /**
     * Delete qoute milik user yang sedang login
     */
    public function destroy(Request $request)
    {
        $userId = Auth::id();
        $id = $request->input('id');
        if (! $id) {
            return response()->json([
                'message' => 'Parameter id wajib diisi.',
            ], 400);
        }
        $qoute = Qoute::where('user_id', $userId)->where('id', $id)->first();
        if (! $qoute) {
            return response()->json([
                'message' => 'Qoute tidak ditemukan.',
            ], 404);
        }
        $qoute->delete();
        return response()->json([
            'message' => 'Qoute berhasil dihapus.',
        ]);
    }

}
