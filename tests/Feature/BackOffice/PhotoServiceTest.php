<?php

declare(strict_types=1);

use App\Services\PhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it resizes + stores an upload as jpeg, then deletes it', function () {
    Storage::fake('public');

    $path = app(PhotoService::class)->store(UploadedFile::fake()->image('big.png', 2400, 1800), 'pools');

    expect($path)->toStartWith('pools/')->toEndWith('.jpg');
    Storage::disk('public')->assertExists($path);

    app(PhotoService::class)->delete($path);
    Storage::disk('public')->assertMissing($path);
});

test('replace removes the previous image', function () {
    Storage::fake('public');
    $svc = app(PhotoService::class);

    $old = $svc->store(UploadedFile::fake()->image('a.jpg', 800, 600), 'customers');
    $new = $svc->replace(UploadedFile::fake()->image('b.jpg', 800, 600), $old, 'customers');

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($new);
});

test('delete is a safe no-op for null', function () {
    Storage::fake('public');
    app(PhotoService::class)->delete(null);
})->throwsNoExceptions();

test('a transparent store keeps the see-through background as png', function () {
    Storage::fake('public');

    $logo = imagecreatetruecolor(400, 400);
    imagesavealpha($logo, true);
    imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
    ob_start();
    imagepng($logo);
    $file = UploadedFile::fake()->createWithContent('logo.png', (string) ob_get_clean());

    $path = app(PhotoService::class)->store($file, 'tenants', transparent: true);

    expect($path)->toEndWith('.png');
    $pixel = imagecolorsforindex($img = imagecreatefromstring(Storage::disk('public')->get($path)), imagecolorat($img, 0, 0));
    expect($pixel['alpha'])->toBe(127); // fully transparent, not filled white
});
