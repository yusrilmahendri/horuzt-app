<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Themas;
use App\Http\Resources\Themas\ThemaCollection;
use App\Http\Resources\Themas\ThemaResource;

class ThemaController extends Controller
{   
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = Themas::paginate(5);
        return new ThemaCollection($data);
    }

}
