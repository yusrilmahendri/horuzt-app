<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Galery;
use Illuminate\Support\Facades\Auth;

class GaleryController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request){

        $validateData = $request->validate([
            'photo' => 'required|file|mimes:jpg,png,jpeg|max:2048',
            'url_video' => 'required|url',
        ]);

        $userId = Auth::id();
        $photoPath = $request->file('photo')->store('photos', 'public');

        $galery = new Galery();
        $galery->photo = $photoPath;
        $galery->url_video = $validateData['url_video'];
        $galery->user_id = $userId;
        $galery->status = 1;

        if ($galery->save()) {
            return response()->json([
                'message' => 'Galery berhasil disimpan!',
                'data' => $galery,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data galery.',
            ], 500);
        }
    }
}
