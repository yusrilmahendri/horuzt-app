<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReligionContentResolver
{
    public function __construct(private WhatsAppTemplateService $whatsAppTemplateService)
    {
    }

    public function resolveForUser(User $user, array $whatsAppContext = []): array
    {
        $setting = $user->relationLoaded('settingOne')
            ? $user->settingOne
            : $user->settingOne()->first();

        $religionCode = $this->normalize($this->settingValue($setting, 'religion_code')) ?? 'universal';
        $defaults = $this->defaultsFor($religionCode);
        $universal = $this->template('universal');
        $custom = $this->customFor($setting);
        $legacy = $this->legacyFor($user, $setting);
        $resolved = [];
        $sources = [];

        foreach ($this->fields() as $field) {
            if (array_key_exists($field, $custom) && $custom[$field] !== null) {
                $resolved[$field] = $custom[$field];
                $sources[$field] = 'custom';
                continue;
            }

            if (array_key_exists($field, $defaults) && $defaults[$field] !== null) {
                $resolved[$field] = $defaults[$field];
                $sources[$field] = 'default_religion';
                continue;
            }

            if (array_key_exists($field, $universal) && $universal[$field] !== null) {
                $resolved[$field] = $universal[$field];
                $sources[$field] = 'default_universal';
                continue;
            }

            if (array_key_exists($field, $legacy) && $legacy[$field] !== null) {
                $resolved[$field] = $legacy[$field];
                $sources[$field] = 'legacy';
                continue;
            }

            $resolved[$field] = null;
            $sources[$field] = 'null';
        }

        $resolved['whatsapp_text'] = $this->whatsAppTemplateService->render(
            $resolved['whatsapp_message'] ?? null,
            $whatsAppContext
        );

        return [
            'religion_code' => $religionCode,
            'religion_label' => $this->label($religionCode),
            'defaults' => $defaults,
            'custom' => $custom,
            'resolved' => $resolved,
            'legacy' => $legacy,
            'flags' => [
                'sources' => $sources,
                'has_custom' => collect($custom)->contains(fn ($value) => $value !== null),
                'allowed_placeholders' => $this->whatsAppTemplateService->allowedPlaceholders(),
            ],
        ];
    }

    public function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $key = Str::of($value)->lower()->replace([' ', '-'], '_')->ascii()->toString();
        $aliases = config('religion_content.aliases', []);
        $normalized = $aliases[$key] ?? $key;

        return array_key_exists($normalized, $this->supported()) ? $normalized : null;
    }

    public function isSupported(string $value): bool
    {
        return $this->normalize($value) !== null;
    }

    public function supported(): array
    {
        return config('religion_content.supported', []);
    }

    public function fields(): array
    {
        return config('religion_content.fields', []);
    }

    public function customColumn(string $field): string
    {
        return 'religion_'.$field;
    }

    public function label(string $code): string
    {
        return $this->supported()[$code] ?? $this->supported()['universal'] ?? 'Universal';
    }

    public function defaultsFor(string $code): array
    {
        $normalized = $this->normalize($code) ?? 'universal';

        return $this->template($normalized);
    }

    private function template(string $code): array
    {
        $template = config("religion_content.templates.$code", []);

        return collect($this->fields())
            ->mapWithKeys(fn (string $field) => [$field => $template[$field] ?? null])
            ->all();
    }

    private function customFor(?Setting $setting): array
    {
        return collect($this->fields())
            ->mapWithKeys(function (string $field) use ($setting) {
                return [$field => $this->settingValue($setting, $this->customColumn($field))];
            })
            ->all();
    }

    private function legacyFor(User $user, ?Setting $setting): array
    {
        $pernikahan = $user->relationLoaded('pernikahan')
            ? $user->pernikahan
            : $user->pernikahan()->first();

        $quote = $user->relationLoaded('qoute')
            ? $user->qoute->sortByDesc('id')->first()
            : $user->qoute()->latest('id')->first();

        $waParts = array_filter([
            $pernikahan?->salam_wa_atas,
            $pernikahan?->salam_wa_bawah,
        ], fn ($value) => $value !== null);

        return [
            'opening_greeting' => $this->firstFilled([
                $this->settingValue($setting, 'salam_pembuka'),
                $pernikahan?->salam_pembuka,
                $this->settingValue($setting, 'salam_atas'),
            ]),
            'closing_greeting' => $this->firstFilled([
                $this->settingValue($setting, 'salam_bawah'),
            ]),
            'invitation_intro' => null,
            'whatsapp_message' => $waParts !== [] ? implode("\n", $waParts) : null,
            'quote_text' => $quote?->qoute,
            'quote_source' => $quote?->name,
            'prayer_text' => null,
            'blessing_text' => null,
        ];
    }

    private function settingValue(?Setting $setting, string $column): ?string
    {
        if (! $setting || ! Schema::hasColumn($setting->getTable(), $column)) {
            return null;
        }

        return $setting->{$column};
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
