<?php

namespace Tests\Feature;

use App\Models\Galery;
use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhotoPresetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['photo_type', 'file_path', 'focal_point_x', 'focal_point_y', 'is_featured', 'sort_order'] as $column) {
            if (! Schema::hasColumn('galeries', $column)) {
                $this->markTestSkipped('Photo preset migration has not been applied to the testing database.');
            }
        }
    }

    public function test_user_can_upload_photo_with_focal_point(): void
    {
        Storage::fake('public');
        $user = $this->actingUser();

        $response = $this->post('/api/v1/user/photos', [
            'image' => UploadedFile::fake()->image('akad.jpg', 800, 600),
            'photo_type' => 'gallery',
            'focal_point_x' => 30,
            'focal_point_y' => 70,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.focal_point_x', 30)
            ->assertJsonPath('data.focal_point_y', 70)
            ->assertJsonPath('data.object_position', '30% 70%');

        $this->assertDatabaseHas('galeries', [
            'user_id' => $user->id,
            'photo_type' => 'gallery',
            'sort_order' => 1,
        ]);
    }

    public function test_featured_photo_is_unique_per_user_and_photo_type(): void
    {
        Storage::fake('public');
        $user = $this->actingUser();
        $galleryA = $this->photoFor($user, 'gallery', ['is_featured' => true]);
        $galleryB = $this->photoFor($user, 'gallery');
        $collage = $this->photoFor($user, 'collage', ['is_featured' => true]);

        $this->putJson("/api/v1/user/photos/{$galleryB->id}", [
            'is_featured' => true,
        ])->assertOk()
            ->assertJsonPath('data.is_featured', true);

        $this->assertFalse($galleryA->refresh()->is_featured);
        $this->assertTrue($galleryB->refresh()->is_featured);
        $this->assertTrue($collage->refresh()->is_featured);
    }

    public function test_user_cannot_feature_another_users_photo(): void
    {
        Storage::fake('public');
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner-photo@example.test',
            'password' => 'secret123',
        ]);
        $intruder = $this->actingUser('intruder-photo@example.test');
        $photo = $this->photoFor($owner, 'gallery');

        $this->assertNotSame($owner->id, $intruder->id);

        $this->putJson("/api/v1/user/photos/{$photo->id}", [
            'is_featured' => true,
        ])->assertNotFound();
    }

    public function test_update_metadata_without_image_keeps_old_file(): void
    {
        Storage::fake('public');
        $user = $this->actingUser();
        $photo = $this->photoFor($user, 'gallery', ['file_path' => 'photos/old.webp']);
        Storage::disk('public')->put('photos/old.webp', 'old-file');

        $this->putJson("/api/v1/user/photos/{$photo->id}", [
            'position' => 'top-left',
            'display_mode' => 'contain',
            'focal_point_x' => 10,
            'focal_point_y' => 20,
        ])->assertOk()
            ->assertJsonPath('data.object_position', '10% 20%');

        Storage::disk('public')->assertExists('photos/old.webp');
        $this->assertSame('photos/old.webp', $photo->refresh()->file_path);
    }

    public function test_update_image_deletes_old_file_after_database_update(): void
    {
        Storage::fake('public');
        $user = $this->actingUser();
        $photo = $this->photoFor($user, 'gallery', ['file_path' => 'photos/old.webp']);
        Storage::disk('public')->put('photos/old.webp', 'old-file');

        $this->put("/api/v1/user/photos/{$photo->id}", [
            'image' => UploadedFile::fake()->image('replacement.jpg', 800, 600),
        ])->assertOk();

        Storage::disk('public')->assertMissing('photos/old.webp');
        Storage::disk('public')->assertExists($photo->refresh()->file_path);
    }

    public function test_sort_only_updates_owned_photos(): void
    {
        Storage::fake('public');
        $user = $this->actingUser();
        $other = User::create([
            'name' => 'Other',
            'email' => 'other-sort@example.test',
            'password' => 'secret123',
        ]);
        $owned = $this->photoFor($user, 'gallery');
        $notOwned = $this->photoFor($other, 'gallery');

        $this->putJson('/api/v1/user/photos/sort', [
            'items' => [
                ['id' => $owned->id, 'sort_order' => 2],
                ['id' => $notOwned->id, 'sort_order' => 1],
            ],
        ])->assertNotFound();

        $this->assertSame(0, $owned->refresh()->sort_order);
        $this->assertSame(0, $notOwned->refresh()->sort_order);
    }

    public function test_public_wedding_profile_separates_gallery_and_collage_for_owner(): void
    {
        Storage::fake('public');
        $owner = $this->publicWeddingUser('photo-preset.test');
        $other = $this->publicWeddingUser('photo-preset-other.test');
        $gallery = $this->photoFor($owner, 'gallery', ['sort_order' => 2, 'is_featured' => false]);
        $featuredGallery = $this->photoFor($owner, 'gallery', ['sort_order' => 9, 'is_featured' => true]);
        $collage = $this->photoFor($owner, 'collage', ['sort_order' => 1]);
        $this->photoFor($other, 'gallery');

        $response = $this->getJson('/api/v1/wedding-profile/couple/photo-preset.test');

        $response->assertOk()
            ->assertJsonPath('data.gallery.0.id', $featuredGallery->id)
            ->assertJsonPath('data.gallery.1.id', $gallery->id)
            ->assertJsonPath('data.collage.0.id', $collage->id);

        $this->assertNotContains($other->id, collect($response->json('data.gallery'))->pluck('user_id'));
    }

    private function actingUser(string $email = 'photo-user@example.test'): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Photo User',
            'email' => $email,
            'password' => 'secret123',
        ]);
        $user->assignRole('user');

        Sanctum::actingAs($user);

        return $user;
    }

    private function photoFor(User $user, string $type, array $attributes = []): Galery
    {
        $photo = new Galery(array_merge([
            'photo' => "photos/{$user->id}/{$type}/photo.webp",
            'file_path' => "photos/{$user->id}/{$type}/photo.webp",
            'photo_type' => $type,
            'position' => 'center',
            'display_mode' => 'cover',
            'is_featured' => false,
            'sort_order' => 0,
            'status' => 1,
        ], $attributes));
        $photo->user_id = $user->id;
        $photo->save();

        Storage::disk('public')->put($photo->file_path, 'photo-file');

        return $photo;
    }

    private function publicWeddingUser(string $domain): User
    {
        $user = User::create([
            'name' => 'Public Photo User',
            'email' => $domain.'@example.test',
            'password' => 'secret123',
        ]);

        $package = PaketUndangan::firstOrCreate(
            ['code' => 'ruby'],
            [
                'jenis_paket' => 'Paket Ruby',
                'name_paket' => 'Ruby',
                'price' => 0,
                'masa_aktif' => 30,
            ]
        );

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step3',
            'payment_status' => 'paid',
            'is_trial' => false,
            'kode_pemesanan' => 'PHOTO-'.$user->id,
            'domain_expires_at' => now()->addDays(30),
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'name_paket' => $package->name_paket,
            ],
        ]);

        Setting::create([
            'user_id' => $user->id,
            'domain' => $domain,
        ]);

        Mempelai::create([
            'user_id' => $user->id,
            'name_lengkap_pria' => 'Anton',
            'name_lengkap_wanita' => 'Keok',
            'name_panggilan_pria' => 'anton',
            'name_panggilan_wanita' => 'keok',
            'status' => 'Sudah Bayar',
            'kd_status' => 'SB',
        ]);

        return $user;
    }
}
