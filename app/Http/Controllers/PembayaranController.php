<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pembayaran;
use App\Http\Resources\Pembayaran\PembayaranCollection;

class PembayaranController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = Pembayaran::paginate(5);
        return new PembayaranCollection($data);
    }
}
