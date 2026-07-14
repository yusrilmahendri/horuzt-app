<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JenisThemas;
use App\Models\ResultThemas;
use App\Services\PackageThemeAccessService;
use Illuminate\Support\Facades\Auth;

class JenisThemaController extends Controller
{
    public function __construct(private PackageThemeAccessService $themeAccess){
        $this->middleware('auth:sanctum');
    }  

    public function index(){
        $user = Auth::user();
        $package = $this->themeAccess->packageForUser($user);
        $selectedThemeId = ResultThemas::query()
            ->where('user_id', $user->id)
            ->latest('selected_at')
            ->value('jenis_id');

        $data = JenisThemas::active()
            ->withActiveCategory()
            ->with('category')
            ->ordered()
            ->paginate(5);

        $data->setCollection($data->getCollection()->map(
            fn (JenisThemas $theme) => $this->themeAccess->themeAccessPayload(
                $theme,
                $package,
                $selectedThemeId ? (int) $selectedThemeId : null
            )
        ));

        return response()->json([
            'data' => $data->items(),
            'total jenis thema' => $data->count(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }
}
