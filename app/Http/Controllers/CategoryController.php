<?php
 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\CategoryThemas\CategoryCollection;
use App\Models\CategoryThemas;

class CategoryController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = CategoryThemas::paginate(5);
        return new CategoryCollection($data);
    }
}
