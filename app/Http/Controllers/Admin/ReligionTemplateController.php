<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReligionTemplate;
use App\Services\ReligionContentResolver;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReligionTemplateController extends Controller
{
    public function __construct(
        private ReligionContentResolver $resolver,
        private WhatsAppTemplateService $whatsAppTemplateService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    public function index(Request $request): JsonResponse
    {
        $templates = ReligionTemplate::query()
            ->when($request->filled('active'), fn ($query) => $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = strtolower((string) $request->query('search'));

                $query->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(religion_key) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(religion_name) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->orderByRaw("CASE WHEN religion_key = 'universal' THEN 0 ELSE 1 END")
            ->orderBy('religion_name')
            ->get()
            ->map(fn (ReligionTemplate $template): array => $this->payload($template));

        return response()->json([
            'status' => true,
            'message' => 'Template agama berhasil diambil.',
            'data' => $templates,
            'total' => $templates->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $template = ReligionTemplate::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Template agama berhasil diambil.',
            'data' => $this->payload($template),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $normalized = $this->resolver->normalize($validated['religion_key']);

        if ($normalized === null || $normalized === 'custom') {
            return $this->invalidReligionKey();
        }

        $validated['religion_key'] = $normalized;
        $uniqueError = $this->ensureUniqueReligionKey($normalized);
        if ($uniqueError) {
            return $uniqueError;
        }

        $validated['created_by'] = $request->user()?->id;
        $validated['updated_by'] = $request->user()?->id;
        $validated['active'] = $validated['active'] ?? true;
        $validated['version'] = $validated['version'] ?? 1;

        $template = ReligionTemplate::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Template agama berhasil dibuat.',
            'data' => $this->payload($template),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = ReligionTemplate::findOrFail($id);
        $validated = $this->validated($request, $template);

        if (array_key_exists('religion_key', $validated)) {
            $normalized = $this->resolver->normalize($validated['religion_key']);

            if ($normalized === null || $normalized === 'custom') {
                return $this->invalidReligionKey();
            }

            $validated['religion_key'] = $normalized;
            $uniqueError = $this->ensureUniqueReligionKey($normalized, $template);
            if ($uniqueError) {
                return $uniqueError;
            }
        }

        $validated['updated_by'] = $request->user()?->id;
        $validated['version'] = ((int) $template->version) + 1;

        $template->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Template agama berhasil diperbarui.',
            'data' => $this->payload($template->refresh()),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $template = ReligionTemplate::findOrFail($id);
        $template->update([
            'active' => $validated['active'],
            'updated_by' => $request->user()?->id,
            'version' => ((int) $template->version) + 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status template agama berhasil diperbarui.',
            'data' => $this->payload($template->refresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = ReligionTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'status' => true,
            'message' => 'Template agama berhasil dihapus.',
            'data' => [
                'id' => $id,
            ],
        ]);
    }

    private function validated(Request $request, ?ReligionTemplate $template = null): array|JsonResponse
    {
        $rules = [
            'religion_key' => [
                $template ? 'sometimes' : 'required',
                'string',
                'max:50',
            ],
            'religion_name' => [$template ? 'sometimes' : 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];

        foreach ($this->resolver->fields() as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'string'];
        }

        foreach ($this->aliasMap() as $alias => $field) {
            $rules[$alias] = ['sometimes', 'nullable', 'string'];
        }

        $validated = $request->validate($rules);
        $validated = $this->normalizeContentAliases($validated);
        $placeholderErrors = $this->placeholderErrors($validated);

        if ($placeholderErrors !== []) {
            abort(response()->json([
                'message' => 'Placeholder template agama tidak valid.',
                'errors' => $placeholderErrors,
            ], 422));
        }

        return $validated;
    }

    private function ensureUniqueReligionKey(string $religionKey, ?ReligionTemplate $template = null): ?JsonResponse
    {
        $exists = ReligionTemplate::query()
            ->where('religion_key', $religionKey)
            ->when($template, fn ($query) => $query->where('id', '!=', $template->id))
            ->exists();

        if (! $exists) {
            return null;
        }

        return response()->json([
            'message' => 'Religion key sudah digunakan.',
            'errors' => [
                'religion_key' => ['Religion key sudah digunakan.'],
            ],
        ], 422);
    }

    private function normalizeContentAliases(array $validated): array
    {
        foreach ($this->aliasMap() as $alias => $field) {
            if (array_key_exists($alias, $validated)) {
                $validated[$field] = $validated[$alias];
                unset($validated[$alias]);
            }
        }

        return $validated;
    }

    private function placeholderErrors(array $validated): array
    {
        $errors = [];

        foreach ($this->resolver->fields() as $field) {
            $invalid = $this->whatsAppTemplateService->invalidPlaceholders($validated[$field] ?? null);

            if ($invalid !== []) {
                $errors[$field] = [
                    'Placeholder tidak didukung: '.implode(', ', $invalid).'. Placeholder yang tersedia: '.implode(', ', $this->whatsAppTemplateService->allowedPlaceholders()).'.',
                ];
            }
        }

        return $errors;
    }

    private function payload(ReligionTemplate $template): array
    {
        $content = collect($this->resolver->fields())
            ->mapWithKeys(fn (string $field) => [$field => $template->{$field}])
            ->all();

        return array_merge([
            'id' => $template->id,
            'religion_key' => $template->religion_key,
            'religion_name' => $template->religion_name,
            'active' => (bool) $template->active,
            'version' => (int) $template->version,
            'created_by' => $template->created_by,
            'updated_by' => $template->updated_by,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ], $content, [
            'content' => array_merge($content, [
                'opening_heading' => $content['opening_greeting'] ?? null,
                'opening_text' => $content['invitation_intro'] ?? null,
                'closing_text' => $content['closing_greeting'] ?? null,
                'invitation_greeting' => $content['opening_greeting'] ?? null,
            ]),
            'allowed_placeholders' => $this->whatsAppTemplateService->allowedPlaceholders(),
        ]);
    }

    private function aliasMap(): array
    {
        return [
            'opening_heading' => 'opening_greeting',
            'opening_text' => 'invitation_intro',
            'closing_text' => 'closing_greeting',
            'invitation_greeting' => 'opening_greeting',
        ];
    }

    private function invalidReligionKey(): JsonResponse
    {
        return response()->json([
            'message' => 'Kode agama tidak valid.',
            'errors' => [
                'religion_key' => ['Kode agama tidak valid.'],
            ],
        ], 422);
    }
}
