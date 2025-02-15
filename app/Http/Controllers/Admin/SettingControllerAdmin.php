<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MetodeTransaction;
use App\Http\Resources\TagihanTransaction\TagihanTransactionCollection;

class SettingControllerAdmin extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function masterTagihan(){
        $data = MetodeTransaction::get();
        return new TagihanTransactionCollection($data);
    }


    public function storeTransaction(){

    }
}
