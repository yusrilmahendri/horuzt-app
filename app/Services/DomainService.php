<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DomainService
{
    public function normalizeToSlug(?string $inputDomain): string
    {
        $raw = Str::lower(trim((string) $inputDomain));

        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('#^https?://#', '', $raw) ?? $raw;
        $raw = ltrim($raw, '/');

        $raw = preg_replace('#^www\.sena-digital\.com/#', '', $raw) ?? $raw;
        $raw = preg_replace('#^sena-digital\.com/#', '', $raw) ?? $raw;
        $raw = preg_replace('#^wedding/#', '', $raw) ?? $raw;

        $parsed = parse_url('http://' . ltrim($raw, '/'));
        if (is_array($parsed)) {
            $host = trim((string) ($parsed['host'] ?? ''));
            $path = trim((string) ($parsed['path'] ?? ''), '/');

            if (in_array($host, ['www.sena-digital.com', 'sena-digital.com'], true)) {
                $raw = $path;
            } elseif ($path !== '') {
                $raw = $path;
            } else {
                $raw = $host;
            }
        }

        $raw = preg_replace('#^wedding/#', '', $raw) ?? $raw;
        $segments = array_values(array_filter(explode('/', trim($raw, '/'))));
        $candidate = $segments !== [] ? end($segments) : trim($raw, '/');

        $slug = Str::slug((string) $candidate, '-');
        $slug = trim($slug, '-');

        return Str::lower($slug);
    }

    /**
     * @return array{
     *   exists_in_settings: bool,
     *   exists_in_invitations: bool,
     *   is_used: bool
     * }
     */
    public function checkDomainUsage(string $normalizedDomain, ?int $excludeUserId = null): array
    {
        if ($normalizedDomain === '') {
            return [
                'exists_in_settings' => false,
                'exists_in_invitations' => false,
                'is_used' => false,
            ];
        }

        $settingsQuery = Setting::query()->whereRaw('LOWER(domain) = ?', [$normalizedDomain]);
        if ($excludeUserId !== null) {
            $settingsQuery->where('user_id', '!=', $excludeUserId);
        }
        $existsInSettings = $settingsQuery->exists();

        $existsInInvitations = false;
        if (Schema::hasColumn('invitations', 'domain')) {
            $invitationQuery = Invitation::query()->whereRaw('LOWER(domain) = ?', [$normalizedDomain]);
            if ($excludeUserId !== null) {
                $invitationQuery->where('user_id', '!=', $excludeUserId);
            }
            $existsInInvitations = $invitationQuery->exists();
        }

        return [
            'exists_in_settings' => $existsInSettings,
            'exists_in_invitations' => $existsInInvitations,
            'is_used' => ($existsInSettings || $existsInInvitations),
        ];
    }

    public function logValidation(
        ?int $userId,
        ?string $inputDomain,
        string $normalizedDomain,
        bool $existsInSettings,
        bool $existsInInvitations,
        string $result
    ): void {
        Log::info('[DomainValidation]', [
            'user_id' => $userId,
            'input_domain' => $inputDomain,
            'normalized_domain' => $normalizedDomain,
            'exists_in_settings' => $existsInSettings,
            'exists_in_invitations' => $existsInInvitations,
            'result' => $result,
        ]);
    }

    public function resolveOwnerUserIdByDomain(string $normalizedDomain): ?int
    {
        if ($normalizedDomain === '') {
            return null;
        }

        $ownerUserId = Setting::query()
            ->whereRaw('LOWER(domain) = ?', [$normalizedDomain])
            ->value('user_id');

        if ($ownerUserId) {
            return (int) $ownerUserId;
        }

        if (Schema::hasColumn('invitations', 'domain')) {
            $ownerUserId = Invitation::query()
                ->whereRaw('LOWER(domain) = ?', [$normalizedDomain])
                ->value('user_id');

            if ($ownerUserId) {
                return (int) $ownerUserId;
            }
        }

        return null;
    }
}
