<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;
use App\Http\Resources\Bank\BankCollection;

class BankController extends Controller
{
    public function __construct(){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = Bank::get();
        return new BankCollection($data);
    }
}
