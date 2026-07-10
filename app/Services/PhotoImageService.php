<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PhotoImageService
{
    public const QUALITY = 85;
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1920;

    public function compressAndStore(UploadedFile $file, int $userId, string $photoType): array
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('GD image extension is not available.');
        }

        $sourcePath = $file->getRealPath();
        if (! $sourcePath) {
            throw new RuntimeException('Uploaded image cannot be read.');
        }

        $imageInfo = @getimagesize($sourcePath);
        if (! is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new RuntimeException('Uploaded file is not a valid image.');
        }

        $source = $this->createImageResource($sourcePath, $imageInfo['mime']);
        $source = $this->applyExifOrientation($source, $sourcePath, $imageInfo['mime']);
        $source = $this->resizeIfNeeded($source);

        $binary = $this->encodeWebp($source);
        imagedestroy($source);

        $path = sprintf(
            'photos/%d/%s/%s.webp',
            $userId,
            $photoType,
            (string) Str::uuid()
        );

        if (! Storage::disk('public')->put($path, $binary)) {
            throw new RuntimeException('Compressed image could not be saved.');
        }

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'original_size' => $file->getSize(),
            'compressed_size' => strlen($binary),
            'mime_type' => 'image/webp',
            'quality' => self::QUALITY,
        ];
    }

    private function createImageResource(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => throw new RuntimeException('Unsupported image MIME type.'),
        } ?: throw new RuntimeException('Image could not be decoded.');
    }

    private function applyExifOrientation($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        return match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function resizeIfNeeded($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= self::MAX_WIDTH && $height <= self::MAX_HEIGHT) {
            return $image;
        }

        $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $newWidth = max(1, (int) floor($width * $ratio));
        $newHeight = max(1, (int) floor($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        if (! imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
            imagedestroy($resized);
            throw new RuntimeException('Image could not be resized.');
        }

        imagedestroy($image);

        return $resized;
    }

    private function encodeWebp($image): string
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('WEBP encoding is not available.');
        }

        ob_start();
        $encoded = imagewebp($image, null, self::QUALITY);
        $binary = ob_get_clean();

        if (! $encoded || ! is_string($binary) || $binary === '') {
            throw new RuntimeException('Image could not be compressed.');
        }

        return $binary;
    }
}
