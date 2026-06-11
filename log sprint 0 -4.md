Bisa, Pak. Saya buatkan log dan catatan development backend agar nanti Bapak mudah lanjut develop, debugging, atau masuk ke frontend.

Berikut versi yang bisa Bapak simpan sebagai DEVELOPMENT_LOG.md atau BACKEND_NOTES.md.

Development Log & Catatan Teknis Backend — Sena Digital Invitation Website

1. Ringkasan Project

Project ini adalah pengembangan website undangan digital Sena Digital. Backend menggunakan Laravel 10 dan frontend menggunakan Angular. Project sudah memiliki sistem lama/legacy, sehingga pengembangan backend dilakukan secara bertahap agar tidak merusak fitur existing.

Fokus backend saat ini adalah menyiapkan API MVP untuk integrasi frontend Angular, meliputi:

1. Public wedding profile.
2. Tema undangan.
3. Paket dan status akun.
4. Gallery/manajemen foto.
5. Musik undangan.
6. Katalog lagu dan musik default.

⸻

2. Status Sprint Backend

BE-0 — Public Endpoint Hotfix

Status: LULUS

Tujuan:

* Membuka endpoint public yang sebelumnya terblokir middleware auth.

Endpoint terkait:

* GET /api/v1/wedding-profile/public
* GET /api/v1/wedding-profile/couple/{domain}
* GET /api/v1/galery/public?user_id={id}
* GET /api/v1/music/stream/public?id={setting_id}

Perubahan utama:

* WeddingProfileController public method tidak lagi terkena auth.
* GaleryController::publicIndex tidak lagi terkena auth.
* Public endpoint wedding dan gallery bisa diakses tanpa token.

Catatan:

* Endpoint user/dashboard tetap harus protected.
* Public endpoint boleh return 200, 403, atau 404, tetapi tidak boleh 401.

⸻

BE-1 — Tema Undangan + Lock Paket

Status: LULUS

Tujuan:

* Menggunakan sistem tema baru.
* Mencegah paket Ruby/Trial memilih tema berbayar.
* Menambahkan display nama paket baru.

Mapping paket:

* Paket Silver → Ruby
* Paket Gold → Sapphire
* Paket Platinum → Diamond
* Paket Trial → Trial
* Standart/Standard → Ruby

File penting:

* app/Models/PaketUndangan.php
* app/Http/Controllers/Api/ThemeController.php
* app/Http/Resources/WeddingProfile/WeddingProfileResource.php

Endpoint tema:

* GET /api/themes/categories
* GET /api/themes/categories/{categoryId}
* GET /api/themes/theme/{themeId}
* GET /api/themes/popular
* GET /api/themes/search
* GET /api/themes/layout
* GET /api/themes/demo/{themeId}
* POST /api/themes/select
* GET /api/themes/selected

Aturan lock:

* Paket dengan bebas_pilih_tema = true boleh memilih semua tema aktif.
* Paket dengan bebas_pilih_tema = false hanya boleh memilih tema gratis/basic.
* Tema berbayar ditentukan dari price > 0.

Catatan frontend:

* Untuk tampilan paket, gunakan name_paket_display.
* Untuk logic UI paket, gunakan package_tier.
* Untuk akses tema premium, gunakan bebas_pilih_tema.

⸻

BE-2 — Paket & Status Akun

Status: LULUS

Tujuan:

* Menyiapkan status akun untuk dashboard.
* Menambahkan informasi payment, domain expiry, trial, dan pending upgrade ke wedding profile dashboard.

File penting:

* app/Http/Resources/WeddingProfile/WeddingProfileResource.php
* app/Http/Controllers/PackageUpgradeController.php
* app/Models/PaketUndangan.php

Endpoint utama:

* GET /api/v1/paket-undangan
* GET /api/v1/user/eligible-packages
* POST /api/v1/user/upgrade-package
* GET /api/v1/user/wedding-profile
* GET /api/v1/wedding-profile/couple/{domain}

Field penting dashboard:

* payment_status
* is_trial
* domain_expires_at
* payment_confirmed_at
* kode_pemesanan
* is_domain_active
* days_until_expiry
* has_pending_upgrade
* package_features_snapshot

Public wedding view:

* Field internal seperti payment_status, kode_pemesanan, dan package_features_snapshot disembunyikan dari public view.

Technical debt:

* initiateUpgrade masih mengubah paket_undangan_id ke paket tujuan sebelum pembayaran selesai.
* Perlu sprint payment hardening jika ingin memisahkan active package dan pending package.

⸻

BE-3 — Gallery / Manajemen Foto

Status: LULUS

Tujuan:

* Menyiapkan upload/list/delete gallery.
* Mengamankan delete dengan ownership check.
* Membuat public gallery default hanya menampilkan status aktif.

File penting:

* app/Http/Controllers/GaleryController.php
* app/Models/Galery.php
* app/Http/Resources/WeddingProfile/WeddingProfileResource.php

Endpoint:

* POST /api/v1/user/submission-galery
* GET /api/v1/user/list-galery
* DELETE /api/v1/user/delete-galery?id={id}
* GET /api/v1/galery/public?user_id={id}

Perubahan penting:

* destroy() sekarang cek id + user_id = Auth::id().
* User A tidak bisa menghapus gallery User B.
* publicIndex() default filter status = 1.
* url_video divalidasi sebagai URL.

Catatan:

* Belum ada endpoint update/edit gallery.
* Belum ada sort order/position.
* Belum ada kompresi gambar 85%.
* Untuk public wedding, sebaiknya frontend mengambil gallery dari data.gallery[] di wedding profile, bukan endpoint /galery/public.

⸻

BE-4 — Musik Undangan Custom

Status: LULUS

Tujuan:

* Menyiapkan upload/stream/delete musik custom.
* Support format mp3, wav, ogg, dan m4a.
* Memastikan URL musik benar.
* Membatasi upload custom hanya untuk paket Diamond.

File penting:

* app/Http/Controllers/MusicController.php
* app/Services/MusicStreamService.php
* app/Http/Requests/StoreMusicRequest.php
* app/Http/Requests/StreamMusicRequest.php

Endpoint:

* POST /api/music/upload
* GET /api/music/info
* GET /api/music/stream?id={setting_id}
* GET /api/v1/music/stream/public?id={setting_id}
* DELETE /api/music/delete
* GET /api/music/download?id={setting_id}

Fix yang sudah dilakukan:

* MusicStreamService::validateAudioFile() menerima:
    * audio/mpeg
    * audio/mp3
    * audio/wav
    * audio/x-wav
    * audio/ogg
    * audio/mp4
    * audio/x-m4a
    * audio/m4a
* music_info.url dinormalisasi agar menjadi /storage/music/..., bukan /storage/public/music/....
* MusicController::store() hanya mengizinkan upload custom untuk paket Diamond/Platinum.
* Ruby/Sapphire/Trial akan mendapat 403.

Catatan:

* Endpoint stream public tetap terbuka untuk kebutuhan audio player undangan.
* Frontend harus menggunakan settings.music_stream_url untuk audio player.

⸻

BE-4B — Hardening Upload Custom Diamond

Status: LULUS

Tujuan:

* Memastikan endpoint baru dan legacy sama-sama tidak bisa bypass aturan Diamond.

File penting:

* app/Http/Controllers/MusicController.php
* app/Http/Controllers/SettingController.php

Perubahan:

* POST /api/music/upload hanya Diamond.
* Legacy POST /api/v1/user/settings/music juga hanya Diamond.

Response non-Diamond:

{
  "message": "Custom music upload is only available for Diamond package."
}

Catatan:

* DELETE /api/music/delete tetap boleh untuk semua paket agar user lama bisa menghapus musik existing.
* Endpoint legacy tetap ada, tetapi frontend baru tidak boleh menggunakannya.

⸻

BE-4C — Musik Default + Katalog Lagu Sena Digital

Status: Siap Testing Final / Belum Dinyatakan Lulus Sampai Postman Test Selesai

Tujuan:

* Menambahkan katalog lagu Sena Digital.
* Menambahkan musik default.
* Menambahkan pilihan lagu user.
* Menambahkan resolver musik:
    1. Custom upload.
    2. Selected catalog track.
    3. Default track.
    4. No music.

File baru:

* database/migrations/2026_06_11_000001_create_music_tracks_table.php
* database/migrations/2026_06_11_000002_add_music_track_id_to_settings_table.php
* app/Models/MusicTrack.php
* app/Services/MusicResolverService.php
* app/Http/Controllers/MusicTrackController.php
* app/Http/Controllers/Admin/AdminMusicTrackController.php
* database/seeders/MusicTrackSeeder.php

File diubah:

* app/Models/Setting.php
* app/Services/MusicStreamService.php
* app/Http/Controllers/MusicController.php
* app/Http/Resources/WeddingProfile/WeddingProfileResource.php
* routes/api.php
* app/Http/Controllers/SettingController.php
* app/Http/Controllers/Admin/AdminMusicTrackController.php

Migration:

* Tabel baru: music_tracks
* Kolom baru: settings.music_track_id
* FK: settings.music_track_id → music_tracks.id dengan nullOnDelete

Endpoint baru:

* GET /api/music/tracks
* POST /api/music/select-track
* POST /api/music/clear-selection
* GET /api/v1/admin/music-tracks
* POST /api/v1/admin/music-tracks
* PUT/PATCH /api/v1/admin/music-tracks/{id}
* PATCH /api/v1/admin/music-tracks/{id}/set-default
* PATCH /api/v1/admin/music-tracks/{id}/toggle-active
* DELETE /api/v1/admin/music-tracks/{id}

Resolver musik:

1. Jika settings.musik ada dan file valid → source = custom.
2. Jika settings.music_track_id ada dan track aktif → source = catalog.
3. Jika ada track default aktif → source = default.
4. Jika tidak ada sumber valid → has_music = false.

Field baru music_info:

* has_music
* source: custom, catalog, default, atau null
* track_id
* title
* artist
* mime_type
* file_size
* url
* supports_streaming
* supports_range_requests
* format_support

Catatan penting:

* GET /api/music/info sekarang menampilkan musik efektif, bukan hanya custom upload.
* Ini perubahan semantik. FE harus melihat music_info.source, bukan hanya status 404.
* GET /api/music/download masih custom-only.
* Admin tidak bisa menonaktifkan track default aktif. Harus set default lain terlebih dahulu.
* MusicTrackSeeder belum otomatis dipanggil di DatabaseSeeder.

⸻

3. Endpoint Canonical untuk Frontend

Auth

* POST /api/v1/register
* POST /api/v1/login
* POST /api/v1/logout

Paket

* GET /api/v1/paket-undangan
* GET /api/v1/user/eligible-packages
* POST /api/v1/user/upgrade-package

Wedding Profile

Dashboard:

* GET /api/v1/user/wedding-profile

Public:

* GET /api/v1/wedding-profile/couple/{domain}

Tema

Public:

* GET /api/themes/categories
* GET /api/themes/categories/{categoryId}
* GET /api/themes/theme/{themeId}
* GET /api/themes/popular
* GET /api/themes/search
* GET /api/themes/layout
* GET /api/themes/demo/{themeId}

Auth:

* POST /api/themes/select
* GET /api/themes/selected

Gallery

* POST /api/v1/user/submission-galery
* GET /api/v1/user/list-galery
* DELETE /api/v1/user/delete-galery?id={id}

Public fallback:

* GET /api/v1/galery/public?user_id={id}

Musik

Custom:

* POST /api/music/upload
* GET /api/music/info
* GET /api/music/stream?id={setting_id}
* GET /api/v1/music/stream/public?id={setting_id}
* DELETE /api/music/delete

Katalog:

* GET /api/music/tracks
* POST /api/music/select-track
* POST /api/music/clear-selection

Admin:

* GET /api/v1/admin/music-tracks
* POST /api/v1/admin/music-tracks
* PATCH /api/v1/admin/music-tracks/{id}/set-default
* PATCH /api/v1/admin/music-tracks/{id}/toggle-active

⸻

4. Endpoint Legacy yang Jangan Dipakai FE Baru

Jangan gunakan endpoint berikut untuk frontend baru:

* POST /api/v1/user/settings/music
* GET /api/v1/user/music/stream
* GET /api/v1/user/music/download
* DELETE /api/v1/user/music/delete
* GET /api/v1/user/get-themas
* GET /api/v1/user/categorys
* GET /api/v1/user/jenis-themas
* GET /api/v1/user/result-themas
* GET /api/v1/user/paket-nikah
* GET /api/v1/list-paket-undangan
* GET /api/v1/wedding-profile/public kecuali fallback
* GET /api/v1/debug/user/{userId}

Canonical public wedding endpoint:

* GET /api/v1/wedding-profile/couple/{domain}

⸻

5. Rule Integrasi Frontend

Paket

Untuk UI:

* Gunakan name_paket_display
* Gunakan display_label

Untuk logic:

* Gunakan package_tier
* Untuk tema premium, gunakan bebas_pilih_tema

Jangan gunakan name_paket untuk UI baru, karena masih berisi nama DB lama seperti Paket Silver/Paket Gold/Paket Platinum.

Tema

* Browse tema dari /api/themes/*
* Pilih tema dari POST /api/themes/select
* Jika response 403, tampilkan pesan upgrade paket.
* Gunakan data.themes.selected_theme dari wedding profile untuk render public.

Gallery

* Dashboard pakai /api/v1/user/list-galery
* Upload pakai /api/v1/user/submission-galery
* Public wedding render dari data.gallery[]
* Hindari /api/v1/galery/public?user_id= kecuali butuh pagination khusus.

Musik

Audio player public harus memakai:

<audio src="{settings.music_stream_url}"></audio>

Jangan pakai endpoint legacy.

Gunakan music_info.source:

* custom: upload Diamond.
* catalog: lagu pilihan user.
* default: lagu default sistem.
* null / has_music=false: tidak ada musik.

Status Akun

Dashboard baca dari:

data.invitation_package

Field penting:

* payment_status
* is_trial
* domain_expires_at
* is_domain_active
* days_until_expiry
* has_pending_upgrade
* paket_undangan.name_paket_display
* paket_undangan.package_tier

⸻

6. Technical Debt / Catatan Tunda

Payment / Upgrade

POST /api/v1/user/upgrade-package masih mengubah paket_undangan_id ke paket tujuan sebelum pembayaran selesai.

Risiko:

* UI bisa terlihat sudah upgrade padahal pembayaran pending.
* Perlu sprint hardening payment.

Rekomendasi:

* Tambah konsep pending_package_id atau tabel upgrade transaction.
* Jangan ubah sekarang jika payment flow belum diaudit total.

Gallery

Belum ada:

* Endpoint update/edit gallery.
* Toggle status gallery.
* Sort order/position.
* Kompresi 85%.

Musik

Masih ada:

* GET /api/music/download masih custom-only.
* MusicTrackSeeder belum otomatis dipanggil.
* Tidak ada integrasi API Sena Digital.
* Tidak ada analytics lagu.
* Legacy music endpoints masih ada, walaupun upload legacy sudah dilock Diamond.

Public Security

Masih ada risiko enumeration:

* Public music stream by setting_id.
* Public gallery by user_id.

Untuk MVP masih acceptable, tetapi bisa di-hardening setelah frontend stabil.

⸻

7. Checklist Testing Penting

Setelah BE-4C

Jalankan:

php artisan migrate
php artisan optimize:clear
php artisan storage:link

Cek route:

php artisan route:list | grep -i music

Test Musik Katalog

1. Admin upload track default.
2. Public GET /api/music/tracks.
3. User Ruby pilih track.
4. Wedding profile user Ruby menampilkan music_info.source = catalog.
5. User clear selection.
6. Wedding profile fallback ke source = default.
7. User Diamond upload custom.
8. Wedding profile Diamond menampilkan source = custom.
9. Diamond delete custom.
10. Wedding profile fallback ke catalog/default.
11. Public stream jalan.
12. Range request menghasilkan 206.
13. Admin tidak bisa inactive default track.
14. Ruby tidak bisa upload custom via:
    * /api/music/upload
    * /api/v1/user/settings/music

Test Endpoint Protected

Tanpa token harus 401:

* GET /api/v1/user/wedding-profile
* GET /api/v1/user/list-galery
* POST /api/music/upload
* POST /api/music/select-track

Public tidak boleh 401:

* GET /api/v1/wedding-profile/couple/{domain}
* GET /api/themes/categories
* GET /api/v1/paket-undangan
* GET /api/music/tracks
* GET /api/v1/music/stream/public?id={setting_id}

⸻

8. Command Development

Clear cache

php artisan optimize:clear

Migration

php artisan migrate

Route list

php artisan route:list | grep -i music
php artisan route:list | grep -i wedding
php artisan route:list | grep -i theme
php artisan route:list | grep -i galery

Storage

php artisan storage:link

Git status

git status
git diff --stat

⸻

9. Catatan Cursor

Jika menggunakan Cursor:

Untuk audit

Gunakan Ask mode.

Contoh:

* Audit controller.
* Audit route.
* Audit model.
* Buat dokumentasi.
* Cari endpoint legacy.

Untuk implementasi kecil

Gunakan Agent mode.

Tetapi instruksi wajib:

* Jangan refactor besar.
* Jangan mengubah route jika tidak diminta.
* Jangan migration kecuali diminta.
* Jangan menyentuh controller lain.

Untuk testing

Gunakan Agent mode, tetapi tulis:

MODE: TESTING ONLY. DO NOT EDIT FILES.

Jika command Artisan menggantung:

* Klik Stop.
* Jalankan command manual di terminal biasa.
* Jangan langsung ulang prompt.
* Minta Cursor melaporkan command terakhir dan output error.

⸻

10. Status Akhir Saat Ini

Backend MVP status:

* BE-0: Lulus.
* BE-1: Lulus.
* BE-2: Lulus.
* BE-3: Lulus.
* BE-4: Lulus.
* BE-4B: Lulus.
* BE-4C: Implementasi selesai, hardening selesai, menunggu testing final Postman/migration.
* BE-5: Dokumentasi API final sudah dibuat, perlu update setelah BE-4C lulus.

Setelah BE-4C testing lulus:

1. Update dokumentasi BE-5.
2. Masuk FE-0 audit frontend Angular.
3. Lanjut integrasi frontend bertahap.

Pak, ini saya susun sebagai log/catatan development utama. Simpan saja dengan nama:

DEVELOPMENT_LOG.md

atau:

BACKEND_NOTES.md

Kalau nanti Bapak bingung posisi terakhir project, cukup buka file ini. Ini sudah berisi status sprint, endpoint canonical, endpoint legacy, rule FE, technical debt, dan checklist testing.