<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ResultPernikahan;
use App\Http\Resources\ResultPernikahan\ResultPernikahanResource;

class ResultPernikahanController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){    
        $resultPernikahans = ResultPernikahan::with(['pernikahan', 'mempelai', 'acara', 'pengunjung', 'qoute'])->get();

    return ResultPernikahanResource::collection($resultPernikahans);
    }
}
