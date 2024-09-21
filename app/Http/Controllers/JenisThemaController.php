<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JenisThemas;
use App\Http\Resources\JenisThemas\JenisThemasCollection;

class JenisThemaController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = JenisThemas::paginate(5);
        return new JenisThemasCollection($data);
    }
}
