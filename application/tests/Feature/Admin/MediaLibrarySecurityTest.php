<?php

use App\Models\User;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests are redirected from media administration', function (): void {
    $this->get('/admin/media')->assertRedirect(route('admin.login'));
    $this->post('/admin/media', [])->assertRedirect(route('admin.login'));
});

test('only administrators can update localized alt text and validation limits it', function (): void {
    Storage::fake('public');
    $owner = User::factory()->operations()->create();
    $asset = mediaAsset($owner);
    $this->actingAs(User::factory()->customer()->create())->put('/admin/media/'.$asset->id, ['alt_nb' => 'x'])->assertForbidden();
    $this->actingAs($owner)->put('/admin/media/'.$asset->id, ['alt_nb' => str_repeat('x', 256)])->assertSessionHasErrors('alt_nb');
    $this->actingAs($owner)->put('/admin/media/'.$asset->id, ['alt_nb' => 'Norsk', 'alt_ru' => 'Русский'])->assertRedirect();
    expect($asset->fresh()->alt_nb)->toBe('Norsk')->and($asset->fresh()->alt_ru)->toBe('Русский');
});

test('svg uploads are rejected', function (): void {
    $admin = User::factory()->operations()->create();
    $svg = UploadedFile::fake()->create('payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>', 'image/svg+xml');

    $this->actingAs($admin)->post('/admin/media', ['image' => $svg])->assertSessionHasErrors('image');
});

test('a valid raster image uploads to a randomized public media path', function (): void {
    Storage::fake('public');
    $admin = User::factory()->operations()->create();
    $this->actingAs($admin)->post('/admin/media', ['image' => UploadedFile::fake()->image('photo.png', 24, 16), 'alt_nb' => 'Bilde'])->assertRedirect();

    $asset = MediaAsset::firstOrFail();
    expect($asset->mime)->toBe('image/png')->and($asset->path)->toStartWith('media/')->and($asset->path)->not->toContain('photo');
    Storage::disk('public')->assertExists($asset->path);
});

test('an assigned asset cannot be deleted', function (): void {
    Storage::fake('public');
    $admin = User::factory()->operations()->create();
    $asset = mediaAsset($admin);
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => 'test', 'assignable_id' => 1, 'role' => 'hero']);

    $this->actingAs($admin)->delete('/admin/media/'.$asset->id)->assertStatus(409);
    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue();
});

test('an unassigned asset delete removes its public storage object', function (): void {
    Storage::fake('public');
    $admin = User::factory()->operations()->create();
    $asset = mediaAsset($admin);

    $this->actingAs($admin)->delete('/admin/media/'.$asset->id)->assertRedirect();
    Storage::disk('public')->assertMissing($asset->path);
    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeFalse();
});

function mediaAsset(User $user): MediaAsset
{
    $path = 'media/test-'.str()->random(20).'.png';
    Storage::disk('public')->put($path, 'fixture');

    return MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => 'fixture.png', 'mime' => 'image/png', 'byte_size' => 7, 'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $path), 'uploader_id' => $user->id]);
}
