<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Finfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

class FileHelpers
{
    public static function getHumanReadableSize(int $sizeInBytes): string
    {
        return Number::fileSize($sizeInBytes);
    }

    public static function getMimeType(string $path): string
    {
        return (new Finfo(FILEINFO_MIME_TYPE))->file($path) ?? 'application/octet-stream';
    }

    public static function getBase64FileInfo(string $base64): ?array
    {
        $finfo = new Finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer(base64_decode($base64));
        if (! Str::startsWith($mimeType, 'image/')) {
            return null;
        }

        $extension = static::getExtensionFromMimeType($mimeType);

        return [$mimeType, $extension];
    }

    public static function getExtensionFromMimeType(string $mimeType): ?string
    {
        return Str::after($mimeType, '/') ?: null;
    }

    public static function getFileHash(string $path, ?string $disk = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            $stream = $disk ? Storage::disk($disk)->readStream($path) : fopen($path, 'rb');
            $ctx = hash_init('md5');
            hash_update_stream($ctx, $stream);
            $hash = hash_final($ctx);
            fclose($stream);

            return $hash;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function sanitizeFileName(string $fileName): string
    {
        $sanitized = Str::slug($fileName, '_');

        return $sanitized ?: $fileName;
    }

    public static function splitNameAndExtension(string $fileName): array
    {
        return [pathinfo($fileName, PATHINFO_FILENAME), pathinfo($fileName, PATHINFO_EXTENSION)];
    }

    public static function getTemporaryFilePath(string $prefix = 'media-'): string
    {
        return tempnam(sys_get_temp_dir(), $prefix) ?: '';
    }
}
