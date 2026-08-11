<?php

namespace Tests\Unit\Services\Infrastructure;

use App\Services\Infrastructure\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    public function test_local_mode_preserves_legacy_disk_names(): void
    {
        Config::set('filesystems.upload_disk', 'public');
        Config::set('filesystems.media_private_disk', 'private');

        $media = app(MediaStorage::class);

        $this->assertSame('public', $media->diskNameFor('public'));
        $this->assertSame('uploads', $media->diskNameFor('uploads'));
        $this->assertSame('private', $media->diskNameFor('private'));
    }

    public function test_media_disk_env_toggle_unifies_public_and_uploads_kinds(): void
    {
        // Simulates MEDIA_DISK=arvan without touching real env/files.
        Config::set('filesystems.upload_disk', 'arvan');

        $media = app(MediaStorage::class);

        $this->assertSame('arvan', $media->diskNameFor('public'));
        $this->assertSame('arvan', $media->diskNameFor('uploads'));
    }

    public function test_private_disk_switches_independently_of_public_media_disk(): void
    {
        Config::set('filesystems.upload_disk', 'arvan');
        Config::set('filesystems.media_private_disk', 'arvan_private');

        $media = app(MediaStorage::class);

        $this->assertSame('arvan_private', $media->diskNameFor('private'));
    }

    public function test_storage_local_upload_and_delete_round_trip(): void
    {
        Storage::fake('public');
        Config::set('filesystems.upload_disk', 'public');

        $media = app(MediaStorage::class);
        $file  = UploadedFile::fake()->image('avatar.jpg');

        $path = $media->putFile($file, 'avatars', 'public');

        Storage::disk('public')->assertExists($path);

        $media->delete($path, 'public');

        Storage::disk('public')->assertMissing($path);
    }

    public function test_url_generation_follows_the_resolved_disk(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.upload_disk', 'public');

        $media = app(MediaStorage::class);
        Storage::disk('uploads')->put('avatars/x.jpg', 'binary-data');

        $url = $media->url('avatars/x.jpg', 'uploads');

        $this->assertStringEndsWith('avatars/x.jpg', $url);
    }

    public function test_avatar_url_strips_legacy_uploads_prefix_and_passes_through_absolute_urls(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.upload_disk', 'public');

        $media = app(MediaStorage::class);
        Storage::disk('uploads')->put('avatars/x.jpg', 'binary-data');

        $this->assertStringEndsWith('avatars/x.jpg', $media->avatarUrl('uploads/avatars/x.jpg'));
        $this->assertNull($media->avatarUrl(null));
        $this->assertNull($media->avatarUrl(''));
    }
}
