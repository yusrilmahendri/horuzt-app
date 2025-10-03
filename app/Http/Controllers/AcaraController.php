<?php
namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use App\Http\Resources\Acara\AcaraCollection; // Import Auth facade
use App\Http\Resources\Acara\AcaraResource;
use App\Models\Acara;
use App\Models\CountdownAcara;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcaraController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $userId = Auth::id();

        $acaras = Acara::with(['countDown'])
                       ->where('user_id', $userId)
                       ->get();

        $countdown = CountdownAcara::where('user_id', $userId)->latest('created_at')->first();

        // Group events by type
        $eventsByType = $acaras->keyBy('jenis_acara');

        // Available event types that can still be created
        $availableTypes = collect(Acara::JENIS_ACARA_OPTIONS)
            ->filter(function ($label, $key) use ($eventsByType) {
                return !$eventsByType->has($key);
            });

        return response()->json([
            'data' => [
                'events' => [
                    'akad' => $eventsByType->get('akad') ? [
                        'id' => $eventsByType->get('akad')->id,
                        'jenis_acara' => $eventsByType->get('akad')->jenis_acara,
                        'nama_acara' => $eventsByType->get('akad')->nama_acara,
                        'tanggal_acara' => $eventsByType->get('akad')->tanggal_acara,
                        'start_acara' => $eventsByType->get('akad')->start_acara,
                        'end_acara' => $eventsByType->get('akad')->end_acara,
                        'alamat' => $eventsByType->get('akad')->alamat,
                        'link_maps' => $eventsByType->get('akad')->link_maps,
                        'created_at' => $eventsByType->get('akad')->created_at,
                        'updated_at' => $eventsByType->get('akad')->updated_at,
                    ] : null,
                    'resepsi' => $eventsByType->get('resepsi') ? [
                        'id' => $eventsByType->get('resepsi')->id,
                        'jenis_acara' => $eventsByType->get('resepsi')->jenis_acara,
                        'nama_acara' => $eventsByType->get('resepsi')->nama_acara,
                        'tanggal_acara' => $eventsByType->get('resepsi')->tanggal_acara,
                        'start_acara' => $eventsByType->get('resepsi')->start_acara,
                        'end_acara' => $eventsByType->get('resepsi')->end_acara,
                        'alamat' => $eventsByType->get('resepsi')->alamat,
                        'link_maps' => $eventsByType->get('resepsi')->link_maps,
                        'created_at' => $eventsByType->get('resepsi')->created_at,
                        'updated_at' => $eventsByType->get('resepsi')->updated_at,
                    ] : null,
                ],
                'countdown' => $countdown ? [
                    'id' => $countdown->id,
                    'name_countdown' => $countdown->name_countdown,
                    'created_at' => $countdown->created_at,
                    'updated_at' => $countdown->updated_at,
                ] : null,
                'available_event_types' => $availableTypes->toArray(),
                'event_type_options' => Acara::JENIS_ACARA_OPTIONS,
            ],
            'message' => 'Events data retrieved successfully'
        ]);
    }

    public function storeCountDown(Request $request)
    {
        $validateData = $request->validate([
            'name_countdown' => 'required',
        ]);

        $userId                    = Auth::id();
        $countDown                 = new CountdownAcara();
        $countDown->user_id        = $userId;
        $countDown->name_countdown = $validateData['name_countdown'];
        $countDown->save();

        if ($countDown) {
            return response()->json([
                'name_countdown' => $countDown,
                'message'        => 'Countdown has been successfully added!',
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to add countdown!',
            ], 400);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'jenis_acara' => 'required|in:akad,resepsi',
                'nama_acara' => 'required|string|max:255',
                'tanggal_acara' => 'required|date',
                'start_acara' => 'required|string',
                'end_acara' => 'required|string',
                'alamat' => 'required|string',
                'link_maps' => 'required|url',
            ]);

            $userId = Auth::id();

            // Get or create countdown
            $countDown = CountdownAcara::where('user_id', $userId)->latest('created_at')->first();
            if (!$countDown) {
                return response()->json([
                    'message' => 'No countdown is associated with the user. Please create a countdown first.',
                ], 400);
            }

            // Check if event type already exists for this user
            $existingEvent = Acara::where('user_id', $userId)
                                  ->where('jenis_acara', $validated['jenis_acara'])
                                  ->first();

            if ($existingEvent) {
                return response()->json([
                    'message' => 'Event type "' . $validated['jenis_acara'] . '" already exists. Please update existing event or choose different type.',
                ], 422);
            }

            // Create new acara with jenis_acara
            $acara = Acara::create([
                'user_id' => $userId,
                'countdown_id' => $countDown->id,
                'jenis_acara' => $validated['jenis_acara'],
                'nama_acara' => $validated['nama_acara'],
                'tanggal_acara' => $validated['tanggal_acara'],
                'start_acara' => $validated['start_acara'],
                'end_acara' => $validated['end_acara'],
                'alamat' => $validated['alamat'],
                'link_maps' => $validated['link_maps'],
            ]);

            $acara->load('countDown');

            return response()->json([
                'data' => [
                    'id' => $acara->id,
                    'jenis_acara' => $acara->jenis_acara,
                    'nama_acara' => $acara->nama_acara,
                    'tanggal_acara' => $acara->tanggal_acara,
                    'start_acara' => $acara->start_acara,
                    'end_acara' => $acara->end_acara,
                    'alamat' => $acara->alamat,
                    'link_maps' => $acara->link_maps,
                    'countdown_id' => $acara->countdown_id,
                    'countdown' => $acara->countDown ? [
                        'id' => $acara->countDown->id,
                        'name_countdown' => $acara->countDown->name_countdown,
                    ] : null,
                ],
                'message' => 'Event "' . $validated['jenis_acara'] . '" has been successfully created!',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create event',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer',
            ]);

            $userId = Auth::id();
            $acara = Acara::where('id', $validated['id'])
                          ->where('user_id', $userId)
                          ->first();

            if (!$acara) {
                return response()->json([
                    'message' => 'Event not found or access denied.',
                ], 404);
            }

            $jenisAcara = $acara->jenis_acara;
            $acara->delete();

            return response()->json([
                'message' => 'Event "' . $jenisAcara . '" deleted successfully!',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete event.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateCountDown(Request $request, $id)
    {
        $countDown = CountdownAcara::find($id);
        if (! $countDown) {
            return response()->json(['message' => 'Countdown not found!'], 404);
        }

        $validateData = $request->validate([
            'name_countdown' => 'required|string|min:1',
        ]);

        $countDown->name_countdown = $validateData['name_countdown'];

        if ($countDown->save()) {
            return response()->json(['data' => $countDown, 'message' => 'Countdown updated successfully!'], 200);
        }
        return response()->json(['message' => 'Failed to update countdown!'], 400);
    }

    public function updateAcara(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:acaras,id',
                'jenis_acara' => 'required|in:akad,resepsi',
                'nama_acara' => 'required|string|max:255',
                'tanggal_acara' => 'required|date',
                'start_acara' => 'required|string',
                'end_acara' => 'required|string',
                'alamat' => 'required|string',
                'link_maps' => 'required|url',
            ]);

            $userId = Auth::id();
            $acara = Acara::where('id', $validated['id'])
                          ->where('user_id', $userId)
                          ->first();

            if (!$acara) {
                return response()->json([
                    'message' => 'Event not found or access denied.',
                ], 404);
            }

            // If jenis_acara is being changed, check for conflicts
            if ($acara->jenis_acara !== $validated['jenis_acara']) {
                $existingEvent = Acara::where('user_id', $userId)
                                      ->where('jenis_acara', $validated['jenis_acara'])
                                      ->where('id', '!=', $validated['id'])
                                      ->first();

                if ($existingEvent) {
                    return response()->json([
                        'message' => 'Another event of type "' . $validated['jenis_acara'] . '" already exists.',
                    ], 422);
                }
            }

            $acara->update([
                'jenis_acara' => $validated['jenis_acara'],
                'nama_acara' => $validated['nama_acara'],
                'tanggal_acara' => $validated['tanggal_acara'],
                'start_acara' => $validated['start_acara'],
                'end_acara' => $validated['end_acara'],
                'alamat' => $validated['alamat'],
                'link_maps' => $validated['link_maps'],
            ]);

            $acara->load('countDown');

            return response()->json([
                'data' => [
                    'id' => $acara->id,
                    'jenis_acara' => $acara->jenis_acara,
                    'nama_acara' => $acara->nama_acara,
                    'tanggal_acara' => $acara->tanggal_acara,
                    'start_acara' => $acara->start_acara,
                    'end_acara' => $acara->end_acara,
                    'alamat' => $acara->alamat,
                    'link_maps' => $acara->link_maps,
                    'countdown_id' => $acara->countdown_id,
                    'countdown' => $acara->countDown ? [
                        'id' => $acara->countDown->id,
                        'name_countdown' => $acara->countDown->name_countdown,
                    ] : null,
                ],
                'message' => 'Event has been successfully updated!',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the event.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
