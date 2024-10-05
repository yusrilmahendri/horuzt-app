<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pernikahan;
use App\Http\Resources\Pernikahan\PernikahanCollection;

class PernikahanController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = Pernikahan::paginate(5);
        return new PernikahanCollection($data);
    }
}
