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
            'selected_theme' => $this->getSelectedThemeSummary(),
            'events' => $this->getEventsInfo(),
            'stories' => $this->getStoriesInfo(),
            'quotes' => $this->getQuotesInfo(),
            'gallery' => $this->getGalleryInfo(),
            'bank_accounts' => $this->getBankAccountsInfo(),
            'settings' => $this->getSettingsInfo(),
            'filter_undangan' => $this->getFilterUndanganInfo(),
            'guest_wishes' => $this->getGuestWishesInfo(),
            'guest_book' => $this->getGuestBookInfo(),
            'komentars' => $this->getKomentarsInfo(),
            'testimonials' => $this->getTestimonialsInfo(),
            'themes' => $this->getThemesInfo(),
            'metadata' => $this->getMetadata(),
        ];
    }

    private function getSelectedThemeSummary(): ?array
    {
        if (! $this->relationLoaded('selectedTheme') || ! $this->selectedTheme) {
            return null;
        }

        $jenisThema = $this->selectedTheme->jenisThema;

        if (! $jenisThema || ! $jenisThema->slug) {
            return null;
        }

        return [
            'id' => $jenisThema->id,
            'slug' => $jenisThema->slug,
            'name' => $jenisThema->name,
            'category_slug' => $jenisThema->category->slug ?? null,
        ];
    }

    private function getUserInfo(): array
    {
        $info = [
            'id' => $this->id,
            'name' => $this->name,
        ];

        // Hide sensitive contact data on public wedding view.
        // Keep them available for the authenticated dashboard response.
        if (! $this->isPublicView) {
            $info['email'] = $this->email;
            $info['phone'] = $this->phone;
            $info['kode_pemesanan'] = $this->kode_pemesanan;
        }

        return $info;
    }

    private function getMempelaiInfo(): ?array
    {
        if (! $this->mempelaiOne) {
            return null;
        }

        return [
            'id' => $this->mempelaiOne->id,
            'cover_photo' => $this->mempelaiOne->cover_photo ? asset('storage/'.$this->mempelaiOne->cover_photo) : null,
            'urutan_mempelai' => $this->mempelaiOne->urutan_mempelai,
            'pria' => [
                'photo' => $this->mempelaiOne->photo_pria ? asset('storage/'.$this->mempelaiOne->photo_pria) : null,
                'nama_lengkap' => $this->mempelaiOne->name_lengkap_pria,
                'nama_panggilan' => $this->mempelaiOne->name_panggilan_pria,
                'ayah' => $this->mempelaiOne->ayah_pria,
                'ibu' => $this->mempelaiOne->ibu_pria,
            ],
            'wanita' => [
                'photo' => $this->mempelaiOne->photo_wanita ? asset('storage/'.$this->mempelaiOne->photo_wanita) : null,
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
        if (! $this->invitationOne) {
            return null;
        }

        $invitation = $this->invitationOne;

        // Guard snapshot so it never breaks when null.
        $snapshot = is_array($invitation->package_features_snapshot)
            ? $invitation->package_features_snapshot
            : [];

        // Pending upgrade = an upgrade was initiated but payment is still pending.
        $hasPendingUpgrade = isset($snapshot['upgrade_initiated_at'])
            && $invitation->payment_status === 'pending';

        // Use model helpers when available, otherwise fall back gracefully.
        $isDomainActive = method_exists($invitation, 'isDomainActive')
            ? $invitation->isDomainActive()
            : ($invitation->domain_expires_at ? $invitation->domain_expires_at->isFuture() : false);

        $daysUntilExpiry = method_exists($invitation, 'getDaysUntilExpiry')
            ? $invitation->getDaysUntilExpiry()
            : null;

        $paketUndangan = $invitation->paketUndangan ? [
            'id' => $invitation->paketUndangan->id,
            'code' => $invitation->paketUndangan->code,
            'jenis_paket' => \App\Models\PaketUndangan::jenisPaketFromCode(
                $invitation->paketUndangan->code,
                $invitation->paketUndangan->jenis_paket
            ),
            // Keep original value for backward compatibility, expose rebranded fields alongside.
            'name_paket' => $invitation->paketUndangan->name_paket,
            'name_paket_original' => $invitation->paketUndangan->name_paket_original,
            'name_paket_display' => \App\Models\PaketUndangan::shortNameFromCode(
                $invitation->paketUndangan->code,
                $invitation->paketUndangan->name_paket
            ),
            'name_display' => \App\Models\PaketUndangan::displayLabelFromCode(
                $invitation->paketUndangan->code,
                $invitation->paketUndangan->name_paket
            ),
            'package_tier' => \App\Models\PaketUndangan::tierCode(
                $invitation->paketUndangan->name_paket,
                $invitation->paketUndangan->code
            ),
            'display_label' => \App\Models\PaketUndangan::displayLabelFromCode(
                $invitation->paketUndangan->code,
                $invitation->paketUndangan->name_paket
            ),
            'price' => $invitation->paketUndangan->price,
            'masa_aktif' => $invitation->paketUndangan->masa_aktif,
            'features' => [
                'halaman_buku' => $invitation->paketUndangan->halaman_buku,
                'kirim_wa' => $invitation->paketUndangan->kirim_wa,
                'bebas_pilih_tema' => $invitation->paketUndangan->bebas_pilih_tema,
                'kirim_hadiah' => $invitation->paketUndangan->kirim_hadiah,
                'import_data' => $invitation->paketUndangan->import_data,
            ],
            'accessible_categories' => $invitation->paketUndangan->accessibleCategories
                ->where('type', 'website')
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ]),
        ] : null;

        // Public wedding view (tamu undangan): only expose safe, non-internal fields.
        if ($this->isPublicView) {
            return [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'is_domain_active' => $isDomainActive,
                'days_until_expiry' => $daysUntilExpiry,
                'paket_undangan' => $paketUndangan,
            ];
        }

        // Authenticated dashboard: full account / domain status fields.
        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'payment_status' => $invitation->payment_status,
            'is_trial' => (bool) $invitation->is_trial,
            'domain_expires_at' => $invitation->domain_expires_at
                ? $invitation->domain_expires_at->format('Y-m-d H:i:s')
                : null,
            'payment_confirmed_at' => $invitation->payment_confirmed_at
                ? $invitation->payment_confirmed_at->format('Y-m-d H:i:s')
                : null,
            'kode_pemesanan' => $invitation->kode_pemesanan,
            'is_domain_active' => $isDomainActive,
            'days_until_expiry' => $daysUntilExpiry,
            'has_pending_upgrade' => $hasPendingUpgrade,
            'package_features_snapshot' => $snapshot,
            'paket_undangan' => $paketUndangan,
        ];
    }

    private function getEventsInfo(): array
    {
        if (! $this->relationLoaded('acara') || ! $this->acara) {
            return [];
        }

        return $this->acara->map(function ($acara) {
            return [
                'id' => $acara->id,
                'jenis_acara' => $acara->jenis_acara ?? null,
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
        if (! $this->relationLoaded('cerita') || ! $this->cerita) {
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
        if (! $this->relationLoaded('qoute') || ! $this->qoute) {
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
        if (! $this->relationLoaded('gallery') || ! $this->gallery) {
            return [];
        }

        return $this->gallery->map(function ($gallery) {
            return [
                'id' => $gallery->id,
                'photo' => $gallery->photo,
                'photo_url' => $gallery->photo_url,
                'url_video' => $gallery->url_video,
                'nama_foto' => $gallery->nama_foto,
                'status' => $gallery->status,
                'created_at' => $gallery->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getBankAccountsInfo(): array
    {
        if (! $this->relationLoaded('rekening') || ! $this->rekening) {
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
                'photo_rek' => $rekening->photo_rek ? asset('storage/photos/'.$rekening->photo_rek) : null,
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
        if (! $this->settingOne) {
            return null;
        }

        // Normalize music path so it never produces /storage/public/...
        // DB may store "public/music/file.mp3"; public URL must be /storage/music/file.mp3
        $musikPath = $this->settingOne->musik
            ? preg_replace('#^public/#', '', $this->settingOne->musik)
            : null;

        $settings = [
            'id' => $this->settingOne->id,
            'domain' => $this->settingOne->domain,
            // Keep existing field: the user's custom uploaded file (null if none).
            'musik' => $musikPath ? asset('storage/'.$musikPath) : null,
            'salam_pembuka' => $this->settingOne->salam_pembuka,
            'salam_atas' => $this->settingOne->salam_atas,
            'salam_bawah' => $this->settingOne->salam_bawah,
        ];

        // Resolve effective music (custom upload > selected catalog > default).
        // The public stream endpoint resolves the same priority server-side,
        // so a single URL works for all three sources.
        $effective = app(\App\Services\MusicResolverService::class)
            ->resolveInfo($this->settingOne);

        if ($effective) {
            $settings['music_stream_url'] = url('/api/v1/music/stream/public?id='.$this->settingOne->id);
            $settings['music_info'] = $effective;
        } else {
            $settings['music_stream_url'] = null;
            $settings['music_info'] = [
                'has_music' => false,
                'supports_streaming' => false,
            ];
        }

        return $settings;
    }

    private function getFilterUndanganInfo(): ?array
    {
        if (! $this->filterUndanganOne) {
            return null;
        }

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
        if (! $this->relationLoaded('ucapan') || ! $this->ucapan) {
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
        if (! $this->relationLoaded('bukuTamu') || ! $this->bukuTamu) {
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

    private function getKomentarsInfo(): array
    {
        if (! $this->relationLoaded('invitationOne') || ! $this->invitationOne) {
            return [];
        }

        // Use eager loaded komentars - no extra query!
        if (! $this->invitationOne->relationLoaded('komentars')) {
            return [];
        }

        return $this->invitationOne->komentars->map(function ($komentar) {
            return [
                'id' => $komentar->id,
                'nama' => $komentar->nama,
                'komentar' => $komentar->komentar,
                'created_at' => $komentar->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function getTestimonialsInfo(): array
    {
        if (! $this->relationLoaded('testimoni') || ! $this->testimoni) {
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
        $result = [
            'selected_theme' => null,
            'legacy_themes' => [],
        ];

        // Get current selected theme (from jenis_themas)
        if ($this->relationLoaded('selectedTheme') && $this->selectedTheme) {
            $selectedTheme = $this->selectedTheme;
            $jenisThema = $selectedTheme->jenisThema;

            if ($jenisThema) {
                $result['selected_theme'] = [
                    'id' => $jenisThema->id,
                    'slug' => $jenisThema->slug,
                    'name' => $jenisThema->name,
                    'price' => $jenisThema->price,
                    'preview' => $jenisThema->preview,
                    'url_thema' => $jenisThema->url_thema,
                    'demo_url' => $jenisThema->demo_url,
                    'features' => $jenisThema->features,
                    'description' => $jenisThema->description,
                    'category' => [
                        'id' => $jenisThema->category->id ?? null,
                        'name' => $jenisThema->category->name ?? null,
                        'slug' => $jenisThema->category->slug ?? null,
                        'type' => $jenisThema->category->type ?? null,
                    ],
                    'selected_at' => $selectedTheme->selected_at?->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Get legacy themes (from old themas table for backward compatibility)
        if ($this->relationLoaded('thema') && $this->thema) {
            $result['legacy_themes'] = $this->thema->map(function ($theme) {
                return [
                    'id' => $theme->id,
                    'name' => $theme->name ?? null,
                    // Add other legacy theme fields as needed
                ];
            })->toArray();
        }

        return $result;
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
            'total_komentars' => ($this->invitationOne && $this->invitationOne->relationLoaded('komentars'))
                ? $this->invitationOne->komentars->count()
                : 0,
            'is_public_view' => $this->isPublicView,
        ];
    }
}
