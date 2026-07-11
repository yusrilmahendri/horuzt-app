<?php
namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use App\Http\Resources\Acara\AcaraCollection; // Import Auth facade
use App\Http\Resources\Acara\AcaraResource;
use App\Models\Acara;
use App\Models\CountdownAcara;
use App\Services\LocationResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AcaraController extends Controller
{
    public function __construct(private LocationResolverService $locationResolver)
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

        // Build acaras array from events for backward compatibility
        $acarasArray = [];
        if ($eventsByType->has('akad')) {
            $acarasArray[] = $this->eventPayload($eventsByType->get('akad'));
        }
        if ($eventsByType->has('resepsi')) {
            $acarasArray[] = $this->eventPayload($eventsByType->get('resepsi'));
        }

        return response()->json([
            'data' => [
                'events' => [
                    'akad' => $eventsByType->get('akad') ? $this->eventPayload($eventsByType->get('akad')) : null,
                    'resepsi' => $eventsByType->get('resepsi') ? $this->eventPayload($eventsByType->get('resepsi')) : null,
                ],
                'acaras' => $acarasArray,
                'countdown' => $countdown ? [
                    'id' => $countdown->id,
                    'name_countdown' => $countdown->name_countdown,
                    'created_at' => $countdown->created_at,
                    'updated_at' => $countdown->updated_at,
                ] : null,
                'available_event_types' => $availableTypes->toArray(),
                'event_type_options' => Acara::JENIS_ACARA_OPTIONS,
            ],
            'message' => 'Lokasi acara berhasil diambil.'
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
            // Check if this is bulk submission (array of data)
            if ($request->hasAny(['nama_acara.0', 'jenis_acara.0'])) {
                return $this->storeBulk($request);
            }

            // Single event submission (existing logic, with additive location fields)
            $validated = $this->validateEventPayload($request->all());

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

            $acaraPayload = [
                'user_id' => $userId,
                'countdown_id' => $countDown->id,
                'jenis_acara' => $validated['jenis_acara'],
                'nama_acara' => $validated['nama_acara'],
                'tanggal_acara' => $validated['tanggal_acara'],
                'start_acara' => $validated['start_acara'],
                'end_acara' => $validated['end_acara'],
            ];

            $acara = Acara::create($this->withLocationPayload($acaraPayload, $validated));

            $acara->load('countDown');

            return response()->json([
                'data' => $this->eventPayload($acara),
                'message' => 'Data lokasi acara berhasil disimpan.',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $this->validationMessage($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan data lokasi acara.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'jenis_acara' => 'required|array|min:1',
            'jenis_acara.*' => 'required|in:akad,resepsi',
            'nama_acara' => 'required|array|min:1',
            'nama_acara.*' => 'required|string|max:255',
            'tanggal_acara' => 'required|array|min:1',
            'tanggal_acara.*' => 'required|date',
            'start_acara' => 'required|array|min:1',
            'start_acara.*' => 'required|string',
            'end_acara' => 'required|array|min:1',
            'end_acara.*' => 'required|string',
            'alamat' => 'nullable|array',
            'alamat.*' => 'nullable|string',
            'address' => 'nullable|array',
            'address.*' => 'nullable|string',
            'link_maps' => 'nullable|array',
            'link_maps.*' => 'nullable|url',
            'google_maps_url' => 'nullable|array',
            'google_maps_url.*' => 'nullable|url',
            'location_name' => 'nullable|array',
            'location_name.*' => 'nullable|string',
            'latitude' => 'nullable|array',
            'latitude.*' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|array',
            'longitude.*' => 'nullable|numeric|between:-180,180',
            'place_id' => 'nullable|array',
            'place_id.*' => 'nullable|string',
        ], $this->validationMessages());
        $this->validateBulkCoordinatePairs($validated);

        $userId = Auth::id();
        $countDown = CountdownAcara::where('user_id', $userId)->latest('created_at')->first();

        if (!$countDown) {
            return response()->json([
                'message' => 'No countdown is associated with the user.',
            ], 400);
        }

        $createdEvents = [];
        $errors = [];

        foreach ($validated['jenis_acara'] as $index => $jenisAcara) {
            // Check if event type already exists
            $existingEvent = Acara::where('user_id', $userId)
                                  ->where('jenis_acara', $jenisAcara)
                                  ->first();

            if ($existingEvent) {
                $errors[] = "Event type \"{$jenisAcara}\" already exists.";
                continue;
            }

            $locationData = $this->extractBulkLocationData($validated, $index);

            $acara = Acara::create($this->withLocationPayload([
                'user_id' => $userId,
                'countdown_id' => $countDown->id,
                'jenis_acara' => $jenisAcara,
                'nama_acara' => $validated['nama_acara'][$index],
                'tanggal_acara' => $validated['tanggal_acara'][$index],
                'start_acara' => $validated['start_acara'][$index],
                'end_acara' => $validated['end_acara'][$index],
            ], $locationData));

            $createdEvents[] = $this->eventPayload($acara);
        }

        return response()->json([
            'data' => [
                'created' => $createdEvents,
                'errors' => $errors,
            ],
            'message' => count($createdEvents) > 0
                ? 'Data lokasi acara berhasil disimpan.'
                : 'Tidak ada data lokasi acara yang disimpan.',
        ], count($createdEvents) > 0 ? 201 : 422);
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
            // Check if this is bulk update (array of data)
            if ($request->has('data') && is_array($request->input('data'))) {
                return $this->updateBulk($request);
            }

            // Single event update (existing logic, with additive location fields)
            $validated = $this->validateEventPayload($request->all(), true);

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

            $acara->update($this->withLocationPayload([
                'jenis_acara' => $validated['jenis_acara'],
                'nama_acara' => $validated['nama_acara'],
                'tanggal_acara' => $validated['tanggal_acara'],
                'start_acara' => $validated['start_acara'],
                'end_acara' => $validated['end_acara'],
            ], $validated));

            $acara->load('countDown');

            return response()->json([
                'data' => $this->eventPayload($acara),
                'message' => 'Data lokasi acara berhasil diperbarui.',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $this->validationMessage($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui data lokasi acara.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function updateBulk(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1',
            'data.*.id' => 'required|integer|exists:acaras,id',
            'data.*.jenis_acara' => 'required|in:akad,resepsi',
            'data.*.nama_acara' => 'required|string|max:255',
            'data.*.tanggal_acara' => 'required|date',
            'data.*.start_acara' => 'required|string',
            'data.*.end_acara' => 'required|string',
            'data.*.alamat' => 'nullable|string',
            'data.*.address' => 'nullable|string',
            'data.*.link_maps' => 'nullable|url',
            'data.*.google_maps_url' => 'nullable|url',
            'data.*.location_name' => 'nullable|string',
            'data.*.latitude' => 'nullable|numeric|between:-90,90',
            'data.*.longitude' => 'nullable|numeric|between:-180,180',
            'data.*.place_id' => 'nullable|string',
        ], $this->validationMessages());
        $this->validateNestedBulkCoordinatePairs($validated['data'] ?? []);

        $userId = Auth::id();
        $updatedEvents = [];
        $errors = [];

        foreach ($validated['data'] as $eventData) {
            $acara = Acara::where('id', $eventData['id'])
                          ->where('user_id', $userId)
                          ->first();

            if (!$acara) {
                $errors[] = "Event with ID {$eventData['id']} not found or access denied.";
                continue;
            }

            // If jenis_acara is being changed, check for conflicts
            if ($acara->jenis_acara !== $eventData['jenis_acara']) {
                $existingEvent = Acara::where('user_id', $userId)
                                      ->where('jenis_acara', $eventData['jenis_acara'])
                                      ->where('id', '!=', $eventData['id'])
                                      ->first();

                if ($existingEvent) {
                    $errors[] = "Cannot change event ID {$eventData['id']} to type \"{$eventData['jenis_acara']}\", already exists.";
                    continue;
                }
            }

            $acara->update($this->withLocationPayload([
                'jenis_acara' => $eventData['jenis_acara'],
                'nama_acara' => $eventData['nama_acara'],
                'tanggal_acara' => $eventData['tanggal_acara'],
                'start_acara' => $eventData['start_acara'],
                'end_acara' => $eventData['end_acara'],
            ], $eventData));

            $acara->load('countDown');
            $updatedEvents[] = $this->eventPayload($acara);
        }

        return response()->json([
            'data' => [
                'updated' => $updatedEvents,
                'errors' => $errors,
            ],
            'message' => count($updatedEvents) > 0
                ? 'Data lokasi acara berhasil diperbarui.'
                : 'Tidak ada data lokasi acara yang diperbarui.',
        ], count($updatedEvents) > 0 ? 200 : 422);
    }

    /**
     * @return array<string,mixed>
     *
     * @throws ValidationException
     */
    private function validateEventPayload(array $payload, bool $isUpdate = false): array
    {
        $rules = [
            'jenis_acara' => 'required|in:akad,resepsi',
            'nama_acara' => 'required|string|max:255',
            'tanggal_acara' => 'required|date',
            'start_acara' => 'required|string',
            'end_acara' => 'required|string',
            'alamat' => 'nullable|string',
            'address' => 'nullable|string',
            'link_maps' => 'nullable|url',
            'google_maps_url' => 'nullable|url',
            'location_name' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'place_id' => 'nullable|string',
        ];

        if ($isUpdate) {
            $rules = ['id' => 'required|integer|exists:acaras,id'] + $rules;
        }

        $validator = Validator::make($payload, $rules, $this->validationMessages());

        $validator->after(function ($validator) use ($payload) {
            $hasLatitude = array_key_exists('latitude', $payload) && $this->hasValue($payload['latitude']);
            $hasLongitude = array_key_exists('longitude', $payload) && $this->hasValue($payload['longitude']);

            if ($hasLatitude xor $hasLongitude) {
                $validator->errors()->add('latitude', 'Latitude dan longitude harus diisi berpasangan.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @return array<string,string>
     */
    private function validationMessages(): array
    {
        return [
            'link_maps.url' => 'Format URL Google Maps tidak valid.',
            'google_maps_url.url' => 'Format URL Google Maps tidak valid.',
            'data.*.link_maps.url' => 'Format URL Google Maps tidak valid.',
            'data.*.google_maps_url.url' => 'Format URL Google Maps tidak valid.',
            'link_maps.*.url' => 'Format URL Google Maps tidak valid.',
            'google_maps_url.*.url' => 'Format URL Google Maps tidak valid.',
            'latitude.between' => 'Latitude harus berada di antara -90 dan 90.',
            'longitude.between' => 'Longitude harus berada di antara -180 dan 180.',
            'data.*.latitude.between' => 'Latitude harus berada di antara -90 dan 90.',
            'data.*.longitude.between' => 'Longitude harus berada di antara -180 dan 180.',
            'latitude.*.between' => 'Latitude harus berada di antara -90 dan 90.',
            'longitude.*.between' => 'Longitude harus berada di antara -180 dan 180.',
        ];
    }

    private function validationMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten();

        if ($messages->contains('Latitude dan longitude harus diisi berpasangan.')) {
            return 'Latitude dan longitude harus diisi berpasangan.';
        }

        if ($messages->contains('Format URL Google Maps tidak valid.')) {
            return 'Format URL Google Maps tidak valid.';
        }

        return 'Data lokasi acara tidak valid.';
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $locationData
     * @return array<string,mixed>
     */
    private function withLocationPayload(array $payload, array $locationData): array
    {
        $address = $this->firstValue($locationData['address'] ?? null, $locationData['alamat'] ?? null);
        $latitude = $this->hasValue($locationData['latitude'] ?? null) ? $locationData['latitude'] : null;
        $longitude = $this->hasValue($locationData['longitude'] ?? null) ? $locationData['longitude'] : null;
        $googleMapsUrl = $this->firstValue($locationData['google_maps_url'] ?? null, $locationData['link_maps'] ?? null);
        $finalMapsUrl = $this->locationResolver->resolveMapsUrl($googleMapsUrl, $latitude, $longitude, $locationData['link_maps'] ?? null);

        // Legacy columns are not nullable in older schemas, so empty strings keep clear-location requests safe.
        $payload['alamat'] = $address ?? '';
        $payload['link_maps'] = $finalMapsUrl ?? '';

        if ($this->locationResolver->hasModernLocationSchema()) {
            $payload['address'] = $address;
            $payload['location_name'] = $this->firstValue($locationData['location_name'] ?? null);
            $payload['latitude'] = $latitude;
            $payload['longitude'] = $longitude;
            $payload['google_maps_url'] = $finalMapsUrl;
            $payload['place_id'] = $this->firstValue($locationData['place_id'] ?? null);
        }

        return $payload;
    }

    private function eventPayload(Acara $acara): array
    {
        $location = $this->locationResolver->resolveAcara($acara);

        return [
            'id' => $acara->id,
            'jenis_acara' => $acara->jenis_acara,
            'nama_acara' => $acara->nama_acara,
            'tanggal_acara' => $acara->tanggal_acara,
            'start_acara' => $acara->start_acara,
            'end_acara' => $acara->end_acara,
            'alamat' => $location['alamat'],
            'link_maps' => $location['link_maps'],
            'address' => $location['address'],
            'location_name' => $location['location_name'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'google_maps_url' => $location['google_maps_url'],
            'place_id' => $location['place_id'],
            'countdown_id' => $acara->countdown_id,
            'countdown' => $acara->countDown ? [
                'id' => $acara->countDown->id,
                'name_countdown' => $acara->countDown->name_countdown,
            ] : null,
            'created_at' => $acara->created_at,
            'updated_at' => $acara->updated_at,
        ];
    }

    /**
     * @param array<string,mixed> $validated
     * @return array<string,mixed>
     */
    private function extractBulkLocationData(array $validated, int $index): array
    {
        $fields = ['alamat', 'address', 'link_maps', 'google_maps_url', 'location_name', 'latitude', 'longitude', 'place_id'];
        $location = [];

        foreach ($fields as $field) {
            $location[$field] = $validated[$field][$index] ?? null;
        }

        return $location;
    }

    /**
     * @param array<string,mixed> $validated
     *
     * @throws ValidationException
     */
    private function validateBulkCoordinatePairs(array $validated): void
    {
        $latitudes = $validated['latitude'] ?? [];
        $longitudes = $validated['longitude'] ?? [];
        $total = max(count($latitudes), count($longitudes));

        for ($index = 0; $index < $total; $index++) {
            $hasLatitude = array_key_exists($index, $latitudes) && $this->hasValue($latitudes[$index]);
            $hasLongitude = array_key_exists($index, $longitudes) && $this->hasValue($longitudes[$index]);

            if ($hasLatitude xor $hasLongitude) {
                throw ValidationException::withMessages([
                    "latitude.{$index}" => ['Latitude dan longitude harus diisi berpasangan.'],
                ]);
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $events
     *
     * @throws ValidationException
     */
    private function validateNestedBulkCoordinatePairs(array $events): void
    {
        foreach ($events as $index => $event) {
            $hasLatitude = array_key_exists('latitude', $event) && $this->hasValue($event['latitude']);
            $hasLongitude = array_key_exists('longitude', $event) && $this->hasValue($event['longitude']);

            if ($hasLatitude xor $hasLongitude) {
                throw ValidationException::withMessages([
                    "data.{$index}.latitude" => ['Latitude dan longitude harus diisi berpasangan.'],
                ]);
            }
        }
    }

    private function firstValue(...$values): ?string
    {
        foreach ($values as $value) {
            if ($this->hasValue($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

}
