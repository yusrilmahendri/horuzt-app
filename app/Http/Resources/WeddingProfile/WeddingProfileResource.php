<?php

namespace App\Http\Resources\WeddingProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingProfileResource extends JsonResource
{
    protected $isPublicView;

    public function __construct($resource, $isPublicView = false)
    {
        parent::__construct($resource);
        $this->isPublicView = $isPublicView;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_info' => $this->getUserInfo(),
            'mempelai' => $this->getMempelaiInfo(),
            'invitation_package' => $this->getInvitationPackageInfo(),
            'events' => $this->getEventsInfo(),
            'stories' => $this->getStoriesInfo(),
            'quotes' => $this->getQuotesInfo(),
            'gallery' => $this->getGalleryInfo(),
            'bank_accounts' => $this->getBankAccountsInfo(),
            'settings' => $this->getSettingsInfo(),
            'filter_undangan' => $this->getFilterUndanganInfo(),
            'guest_wishes' => $this->getGuestWishesInfo(),
            'guest_book' => $this->getGuestBookInfo(),
            'testimonials' => $this->getTestimonialsInfo(),
            'themes' => $this->getThemesInfo(),
            'metadata' => $this->getMetadata(),
        ];
    }

    private function getUserInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'kode_pemesanan' => $this->kode_pemesanan,
        ];
    }

    private function getMempelaiInfo(): ?array
    {
        if (!$this->mempelaiOne) return null;

        return [
            'id' => $this->mempelaiOne->id,
            'cover_photo' => $this->mempelaiOne->cover_photo ? asset('storage/' . $this->mempelaiOne->cover_photo) : null,
            'urutan_mempelai' => $this->mempelaiOne->urutan_mempelai,
            'pria' => [
                'photo' => $this->mempelaiOne->photo_pria ? asset('storage/' . $this->mempelaiOne->photo_pria) : null,
                'nama_lengkap' => $this->mempelaiOne->name_lengkap_pria,
                'nama_panggilan' => $this->mempelaiOne->name_panggilan_pria,
                'ayah' => $this->mempelaiOne->ayah_pria,
                'ibu' => $this->mempelaiOne->ibu_pria,
            ],
            'wanita' => [
                'photo' => $this->mempelaiOne->photo_wanita ? asset('storage/' . $this->mempelaiOne->photo_wanita) : null,
                'nama_lengkap' => $this->mempelaiOne->name_lengkap_wanita,
                'nama_panggilan' => $this->mempelaiOne->name_panggilan_wanita,
                'ayah' => $this->mempelaiOne->ayah_wanita,
                'ibu' => $this->mempelaiOne->ibu_wanita,
            ],
            'status' => $this->mempelaiOne->status,
            'kd_status' => $this->mempelaiOne->kd_status,
        ];
    }

    private function getInvitationPackageInfo(): ?array
    {
        if (!$this->invitationOne) return null;

        return [
            'id' => $this->invitationOne->id,
            'status' => $this->invitationOne->status,
            'paket_undangan' => $this->invitationOne->paketUndangan ? [
                'id' => $this->invitationOne->paketUndangan->id,
                'jenis_paket' => $this->invitationOne->paketUndangan->jenis_paket,
                'name_paket' => $this->invitationOne->paketUndangan->name_paket,
                'price' => $this->invitationOne->paketUndangan->price,
                'masa_aktif' => $this->invitationOne->paketUndangan->masa_aktif,
                'features' => [
                    'halaman_buku' => $this->invitationOne->paketUndangan->halaman_buku,
                    'kirim_wa' => $this->invitationOne->paketUndangan->kirim_wa,
                    'bebas_pilih_tema' => $this->invitationOne->paketUndangan->bebas_pilih_tema,
                    'kirim_hadiah' => $this->invitationOne->paketUndangan->kirim_hadiah,
                    'import_data' => $this->invitationOne->paketUndangan->import_data,
                ]
            ] : null,
        ];
    }

    private function getEventsInfo(): array
    {
        if (!$this->relationLoaded('acara') || !$this->acara) {
            return [];
        }

        return $this->acara->map(function ($acara) {
            return [
                'id' => $acara->id,
                'nama_acara' => $acara->nama_acara,
                'tanggal_acara' => $acara->tanggal_acara,
                'start_acara' => $acara->start_acara,
                'end_acara' => $acara->end_acara,
                'alamat' => $acara->alamat,
                'link_maps' => $acara->link_maps,
                'countdown' => $acara->countdownAcara ? [
                    'id' => $acara->countdownAcara->id,
                    'name_countdown' => $acara->countdownAcara->name_countdown,
                ] : null,
            ];
        })->toArray();
    }

    private function getStoriesInfo(): array
    {
        if (!$this->relationLoaded('cerita') || !$this->cerita) {
            return [];
        }

        return $this->cerita->map(function ($cerita) {
            return [
                'id' => $cerita->id,
                'title' => $cerita->title,
                'lead_cerita' => $cerita->lead_cerita,
                'tanggal_cerita' => $cerita->tanggal_cerita,
                'created_at' => $cerita->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getQuotesInfo(): array
    {
        if (!$this->relationLoaded('qoute') || !$this->qoute) {
            return [];
        }

        return $this->qoute->map(function ($quote) {
            return [
                'id' => $quote->id,
                'name' => $quote->name,
                'qoute' => $quote->qoute,
                'created_at' => $quote->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getGalleryInfo(): array
    {
        if (!$this->relationLoaded('gallery') || !$this->gallery) {
            return [];
        }

        return $this->gallery->map(function ($gallery) {
            return [
                'id' => $gallery->id,
                'photo' => $gallery->photo ? asset('storage/' . $gallery->photo) : null,
                'url_video' => $gallery->url_video,
                'nama_foto' => $gallery->nama_foto,
                'status' => $gallery->status,
                'created_at' => $gallery->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getBankAccountsInfo(): array
    {
        if (!$this->relationLoaded('rekening') || !$this->rekening) {
            return [];
        }

        return $this->rekening->map(function ($rekening) {
            return [
                'id' => $rekening->id,
                'kode_bank' => $rekening->kode_bank,
                'nomor_rekening' => $rekening->nomor_rekening,
                'nama_bank' => $rekening->nama_bank,
                'nama_pemilik' => $rekening->nama_pemilik,
                'methode_pembayaran' => $rekening->methode_pembayaran,
                'photo_rek' => $rekening->photo_rek ? asset('storage/photos/' . $rekening->photo_rek) : null,
                'bank' => $rekening->bank ? [
                    'id' => $rekening->bank->id,
                    'name' => $rekening->bank->name,
                    'kode_bank' => $rekening->bank->kode_bank,
                ] : null,
            ];
        })->toArray();
    }

    private function getSettingsInfo(): ?array
    {
        if (!$this->settingOne) return null;

        return [
            'id' => $this->settingOne->id,
            'domain' => $this->settingOne->domain,
            'musik' => $this->settingOne->musik ? asset('storage/' . $this->settingOne->musik) : null,
            'salam_pembuka' => $this->settingOne->salam_pembuka,
            'salam_atas' => $this->settingOne->salam_atas,
            'salam_bawah' => $this->settingOne->salam_bawah,
        ];
    }

    private function getFilterUndanganInfo(): ?array
    {
        if (!$this->filterUndanganOne) return null;

        return [
            'id' => $this->filterUndanganOne->id,
            'halaman_sampul' => $this->filterUndanganOne->halaman_sampul,
            'halaman_mempelai' => $this->filterUndanganOne->halaman_mempelai,
            'halaman_acara' => $this->filterUndanganOne->halaman_acara,
            'halaman_ucapan' => $this->filterUndanganOne->halaman_ucapan,
            'halaman_galery' => $this->filterUndanganOne->halaman_galery,
            'halaman_cerita' => $this->filterUndanganOne->halaman_cerita,
            'halaman_lokasi' => $this->filterUndanganOne->halaman_lokasi,
            'halaman_prokes' => $this->filterUndanganOne->halaman_prokes,
            'halaman_send_gift' => $this->filterUndanganOne->halaman_send_gift,
            'halaman_qoute' => $this->filterUndanganOne->halaman_qoute,
        ];
    }

    private function getGuestWishesInfo(): array
    {
        if (!$this->relationLoaded('ucapan') || !$this->ucapan) {
            return [];
        }

        return $this->ucapan->map(function ($ucapan) {
            return [
                'id' => $ucapan->id,
                'nama' => $ucapan->nama,
                'kehadiran' => $ucapan->kehadiran ?? null,
                'pesan' => $ucapan->pesan,
                'created_at' => $ucapan->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getGuestBookInfo(): array
    {
        if (!$this->relationLoaded('bukuTamu') || !$this->bukuTamu) {
            return [];
        }

        return $this->bukuTamu->map(function ($bukuTamu) {
            return [
                'id' => $bukuTamu->id,
                'nama' => $bukuTamu->nama,
                'created_at' => $bukuTamu->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getTestimonialsInfo(): array
    {
        if (!$this->relationLoaded('testimoni') || !$this->testimoni) {
            return [];
        }

        return $this->testimoni->map(function ($testimoni) {
            return [
                'id' => $testimoni->id,
                'kota' => $testimoni->kota,
                'provinsi' => $testimoni->provinsi,
                'ulasan' => $testimoni->ulasan,
                'status' => $testimoni->status,
                'created_at' => $testimoni->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getThemesInfo(): array
    {
        if (!$this->relationLoaded('thema') || !$this->thema) {
            return [];
        }

        return $this->thema->map(function ($theme) {
            return [
                'id' => $theme->id,
                'name' => $theme->name ?? null,
                // Add other theme fields as needed
            ];
        })->toArray();
    }

    private function getMetadata(): array
    {
        return [
            'profile_created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'profile_updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'total_events' => $this->relationLoaded('acara') ? $this->acara->count() : 0,
            'total_stories' => $this->relationLoaded('cerita') ? $this->cerita->count() : 0,
            'total_quotes' => $this->relationLoaded('qoute') ? $this->qoute->count() : 0,
            'total_gallery_items' => $this->relationLoaded('gallery') ? $this->gallery->count() : 0,
            'total_guest_wishes' => $this->relationLoaded('ucapan') ? $this->ucapan->count() : 0,
            'is_public_view' => $this->isPublicView,
        ];
    }
}
