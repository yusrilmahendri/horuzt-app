<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BukuTamu;
use App\Http\Resources\Bukutamu\BukuTamuCollection;

class BukuTamuController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = BukuTamu::paginate(5);
        return new BukuTamuCollection($data);
    }
}
