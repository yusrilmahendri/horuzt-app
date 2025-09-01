<?php
namespace App\Http\Controllers;

use App\Http\Resources\User\UserCollection;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function userProfile()
    {
        $user = auth()->user();
        if ($user->hasRole('user')) {
            $user->makeHidden(['roles']);
            $dataUser = auth()->user()->load('invitation.paketUndangan');
            if ($dataUser) {
                return response()->json(['data' => $dataUser], 200);
            } else {
                return new UserCollection(collect([$dataUser]));
            }
        }
        if ($user->hasRole('admin')) {
            $usersQuery = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            });

            $totalUsers = $usersQuery->count();
            $users      = $usersQuery->paginate(5);

            return response()->json([
                'admin'       => new UserCollection(collect([$user])),
                'users'       => new UserCollection($users),
                'total_users' => $totalUsers,
            ]);
        }
        return response()->json(['message' => 'User not found'], 404);
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('user')) {
            $user->makeHidden(['roles']);
            $dataUser = auth()->user()->load([
                'invitation.paketUndangan',
                'settingOne',
                'mempelaiOne',
                'invitationOne',
            ]);

            if ($dataUser) {
                return response()->json(['data' => new UserResource($dataUser)], 200);
            } else {
                return new UserCollection(collect([$dataUser]));
            }
        }

        if ($user->hasRole('admin')) {

            $usersQuery = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })->with([
                'settingOne',
                'mempelaiOne',
                'invitationOne.paketUndangan',
            ]);

            $totalUsers = $usersQuery->count();

            $users = $usersQuery->paginate(5);

            // Enhanced analytics for admin dashboard
            $allUsers = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })->with([
                'mempelaiOne',
                'invitationOne.paketUndangan',
            ])->get();

            // Basic user status counts
            $jumlahBL = $allUsers->filter(fn($user) =>
                $user->mempelaiOne && $user->mempelaiOne->kd_status === 'BL'
            )->count();

            $jumlahMK = $allUsers->filter(fn($user) =>
                $user->mempelaiOne && $user->mempelaiOne->kd_status === 'MK'
            )->count();

            $jumlahSB = $allUsers->filter(fn($user) =>
                $user->mempelaiOne && $user->mempelaiOne->kd_status === 'SB'
            )->count();

            // Payment status analytics
            $pendingPayments = $allUsers->filter(fn($user) =>
                $user->invitationOne && $user->invitationOne->payment_status === 'pending'
            )->count();

            $paidPayments = $allUsers->filter(fn($user) =>
                $user->invitationOne && $user->invitationOne->payment_status === 'paid'
            )->count();

            // Revenue calculations by package (using snapshot prices for accuracy)
            $revenueByPackage = [];
            $totalRevenue = 0;

            foreach ($allUsers as $userData) {
                if ($userData->invitationOne && $userData->invitationOne->payment_status === 'paid') {
                    $price = $userData->invitationOne->package_price_snapshot ?? 0;
                    $packageName = $userData->invitationOne->package_features_snapshot['name_paket'] ?? 'Unknown';

                    if (!isset($revenueByPackage[$packageName])) {
                        $revenueByPackage[$packageName] = [
                            'count' => 0,
                            'revenue' => 0,
                            'package_name' => $packageName
                        ];
                    }

                    $revenueByPackage[$packageName]['count']++;
                    $revenueByPackage[$packageName]['revenue'] += $price;
                    $totalRevenue += $price;
                }
            }

            // Domain expiry analytics
            $activeDomains = $allUsers->filter(fn($user) =>
                $user->invitationOne &&
                $user->invitationOne->payment_status === 'paid' &&
                $user->invitationOne->domain_expires_at &&
                now()->lt($user->invitationOne->domain_expires_at)
            )->count();

            $expiredDomains = $allUsers->filter(fn($user) =>
                $user->invitationOne &&
                $user->invitationOne->payment_status === 'paid' &&
                $user->invitationOne->domain_expires_at &&
                now()->gte($user->invitationOne->domain_expires_at)
            )->count();

            // Domain expiring soon (within 7 days)
            $expiringDomains = $allUsers->filter(fn($user) =>
                $user->invitationOne &&
                $user->invitationOne->payment_status === 'paid' &&
                $user->invitationOne->domain_expires_at &&
                now()->lt($user->invitationOne->domain_expires_at) &&
                now()->addDays(7)->gte($user->invitationOne->domain_expires_at)
            )->count();

            // Step completion analytics
            $stepAnalytics = [
                'step1' => $allUsers->filter(fn($user) => $user->invitationOne && $user->invitationOne->status === 'step1')->count(),
                'step2' => $allUsers->filter(fn($user) => $user->invitationOne && $user->invitationOne->status === 'step2')->count(),
                'step3' => $allUsers->filter(fn($user) => $user->invitationOne && $user->invitationOne->status === 'step3')->count(),
                'step4' => $allUsers->filter(fn($user) => $user->invitationOne && $user->invitationOne->status === 'step4')->count(),
                'completed' => $allUsers->filter(fn($user) => $user->invitationOne && $user->invitationOne->status === 'completed')->count(),
            ];

            return response()->json([
                'admin' => new UserCollection(collect([$user])),
                'users' => new UserCollection($users),
                'total_users' => $totalUsers,

                // Legacy compatibility
                'jumlah_belum_lunas_dan_pending' => [
                    'BL' => $jumlahBL,
                    'MK' => $jumlahMK,
                ],

                // Enhanced dashboard analytics
                'dashboard_analytics' => [
                    'user_status' => [
                        'belum_lunas' => $jumlahBL,
                        'menunggu_konfirmasi' => $jumlahMK,
                        'sudah_bayar' => $jumlahSB,
                        'total' => $totalUsers
                    ],
                    'payment_status' => [
                        'pending' => $pendingPayments,
                        'paid' => $paidPayments
                    ],
                    'revenue' => [
                        'total' => $totalRevenue,
                        'by_package' => array_values($revenueByPackage),
                        'currency' => 'IDR'
                    ],
                    'domain_status' => [
                        'active' => $activeDomains,
                        'expired' => $expiredDomains,
                        'expiring_soon' => $expiringDomains
                    ],
                    'step_completion' => $stepAnalytics
                ]
            ]);
        }

        return response()->json(['message' => 'User not found'], 404);
    }

    public function update(Request $request)
    {


        $user = auth()->user();
        if ($user->hasRole('user')) {

            $validatedData = $request->validate([
                'name'     => 'sometimes|string|max:255',
                'email'    => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'min:6',
                'phone'    => 'min:11',
            ]);

            if (isset($validatedData['name'])) {
                $user->name = $validatedData['name'];
            }

            if (isset($validatedData['email'])) {
                $user->email = $validatedData['email'];
            }

            if (isset($validatedData['password'])) {
                $user->password = bcrypt($validatedData['password']);
            }

            if (isset($validatedData['phone'])) {
                $user->phone = $validatedData['phone'];
            }

            $user->save();

            return response()->json([
                'message' => 'User updated successfully',
                'user'    => new UserResource($user),
            ], 200);
        }
        if ($user->hasRole('admin')) {
            return new UserCollection(collect([$user]));
        }
    }

}
