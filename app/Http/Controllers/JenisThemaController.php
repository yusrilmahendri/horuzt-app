<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JenisThemas;
use App\Http\Resources\JenisThemas\JenisThemasCollection;
use App\Services\PackageThemeAccessService;
use Illuminate\Support\Facades\Auth;

class JenisThemaController extends Controller
{
    public function __construct(private PackageThemeAccessService $themeAccess){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $data = JenisThemas::active()
            ->withActiveCategory()
            ->whereIn('category_id', $this->themeAccess->accessibleCategoryIds(Auth::user()))
            ->ordered()
            ->paginate(5);
        return new JenisThemasCollection($data);
    }
}
