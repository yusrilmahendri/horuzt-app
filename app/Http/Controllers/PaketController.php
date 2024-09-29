<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaketNikah;
use App\Http\Resources\PaketNikah\PaketCollection;

class PaketController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = PaketNikah::paginate(5);
        return new PaketCollection($data);
    }
}
