<?php
namespace App\Http\Resources\User;

use App\Services\AccountStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $domainEndDate = $this->invitationOne?->domain_expires_at;

        // For domain_create_date, use invitation created_at or mempelai created_at as fallback
        $domainCreateDate = $this->invitationOne?->created_at
            ?? ($this->mempelaiOne ? $this->mempelaiOne->created_at : null);

        $accountSummary = app(AccountStatusService::class)->summary($this->resource);
        $selectedTheme = $this->selectedThemeSummary();
        $userAktif = ($accountSummary['account_status'] ?? null) === AccountStatusService::STATUS_ACTIVE ? 1 : 0;

        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'kode_pemesanan'     => $this->kode_pemesanan,
            'user_aktif'         => $userAktif,
            'domain'             => $this->settingOne ? $this->settingOne->domain : null,
            'status'             => $this->mempelaiOne ? $this->mempelaiOne->status : null,
            'kd_status'          => $this->mempelaiOne ? $this->mempelaiOne->kd_status : null,
            'domain_create_date' => $domainCreateDate,
            'domain_end_date'    => $accountSummary['active_until'] ?? null,
            'paket_undangan_id'  => ($accountSummary['current_package']['id'] ?? null),
            'package_code'        => $accountSummary['package_code'] ?? null,
            'nama_paket'          => $accountSummary['package_name'] ?? null,
            'selected_theme_id'   => $selectedTheme['id'] ?? null,
            'selected_theme_slug' => $selectedTheme['slug'] ?? null,
            'selected_theme'      => $selectedTheme,
        ] + $accountSummary + [

            'invitations'        => $this->invitationPayload(),
        ];
    }

    private function selectedThemeSummary(): ?array
    {
        if (! Schema::hasTable('result_themas') || ! Schema::hasTable('jenis_themas')) {
            return null;
        }

        $selection = $this->resource->relationLoaded('selectedTheme')
            ? $this->resource->getRelation('selectedTheme')
            : $this->resource->selectedTheme()->first();

        $theme = $selection?->jenisThema;

        if (! $theme) {
            return null;
        }

        if (! $theme->relationLoaded('category')) {
            $theme->load('category');
        }

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'category_slug' => $theme->category?->slug,
            'selected_at' => $selection->selected_at,
        ];
    }

    private function invitationPayload()
    {
        $invitation = $this->resource->relationLoaded('invitation')
            ? $this->resource->getRelation('invitation')
            : null;

        return collect($invitation ? [$invitation] : [])->map(function ($invitation) {
            return [
                'id'             => $invitation->id ?? null,
                'status'         => $invitation->status ?? null,
                'created_at'     => $invitation->created_at ?? null,
                'updated_at'     => $invitation->updated_at ?? null,

                'paket_undangan' => $invitation->paketUndangan ? [
                    'id'         => $invitation->paketUndangan->id ?? null,
                    'nama_paket' => $invitation->paketUndangan->nama_paket ?? null,
                    'harga'      => $invitation->paketUndangan->harga ?? null,
                ] : null,
            ];
        })->values();
    }

}
