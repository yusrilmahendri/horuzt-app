<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ResultThemas;
use App\Http\Resources\ResultThema\ResultThemaCollection;

class ResultThemaController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = ResultThemas::paginate(5);
        return new ResultThemaCollection($data);
    }
}
