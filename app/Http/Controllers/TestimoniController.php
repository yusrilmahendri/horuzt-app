<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Testimoni\TestimoniCollection;
use App\Models\Testimoni;

class TestimoniController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = Testimoni::paginate(5);
        return new TestimoniCollection($data);
    }
}
