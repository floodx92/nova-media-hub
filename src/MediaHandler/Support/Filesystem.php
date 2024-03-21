<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Illuminate\Contracts\Filesystem\Factory;
use Outl1ne\NovaMediaHub\Exceptions\FileDoesNotExistException;
use Outl1ne\NovaMediaHub\MediaHub;
use Outl1ne\NovaMediaHub\Models\Media;
use RuntimeException;

class Filesystem
{
    const TYPE_ORIGINAL = 'original';

    const TYPE_CONVERSION = 'conversion';

    public function __construct(protected Factory $filesystem)
    {
    }

    public function deleteFromMediaLibrary(Media $media)
    {
        $mainDisk = $this->filesystem->disk($media->disk);
        $convDisk = $this->filesystem->disk($media->conversions_disk);

        // Delete main file
        $mainDisk->delete("{$media->path}{$media->file_name}");

        // Delete conversions
        $conversions = $media->conversions ?? [];
        foreach ($conversions as $conversionName => $fileName) {
            $convDisk->delete("{$media->conversions_path}/{$fileName}");
        }

        $this->deleteDirectoryIfEmpty($convDisk, $media->conversions_path);
        $this->deleteDirectoryIfEmpty($mainDisk, $media->path);

        return true;
    }

    private function deleteDirectoryIfEmpty(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $directoryPath): void
    {
        if (! $disk->allFiles($directoryPath) && ! $disk->allDirectories($directoryPath)) {
            $disk->deleteDirectory($directoryPath);
        }
    }

    public function copyFileToMediaLibrary(string $pathToFile, Media $media, ?string $targetFileName = null, ?string $type = null, bool $deleteFile = true): void
    {
        $destinationPath = $this->getMediaDirectory($media, $type);
        $newFileName = $targetFileName ?: basename($pathToFile);
        $destination = "{$destinationPath}{$newFileName}";

        $disk = $this->filesystem->disk($type !== self::TYPE_CONVERSION ? $media->disk : $media->conversions_disk);
        $disk->put($destination, fopen($pathToFile, 'r'));

        if ($deleteFile && $pathToFile !== $destination) {
            unlink($pathToFile);
        }
    }

    public function copyFromMediaLibrary(Media $media, string $targetFilePath): ?string
    {
        $filePath = "{$media->path}{$media->file_name}";

        if (! $this->filesystem->disk($media->disk)->exists($filePath)) {
            report(new FileDoesNotExistException("File for media [{$media->id}] does not exist."));

            return null;
        }

        $stream = $this->filesystem->disk($media->disk)->readStream($filePath);
        file_put_contents($targetFilePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $targetFilePath;
    }

    public function getMediaDirectory(Media $media, ?string $type = null): string
    {
        $pathMaker = MediaHub::getPathMaker();
        $directory = $type === self::TYPE_CONVERSION ? $pathMaker->getConversionsPath($media) : $pathMaker->getPath($media);

        $disk = $this->filesystem->disk($type === self::TYPE_CONVERSION ? $media->conversions_disk : $media->disk);
        $disk->makeDirectory($directory);

        return $directory;
    }

    /**
     * @throws FileDoesNotExistException
     */
    public function makeTemporaryCopy(string $localFilePath): string
    {
        if (! is_file($localFilePath)) {
            throw new FileDoesNotExistException("File at {$localFilePath} does not exist.");
        }

        $newFilePath = FileHelpers::getTemporaryFilePath('tmp-conversion-copy');
        if (! copy($localFilePath, $newFilePath)) {
            throw new RuntimeException('Failed to make a temporary copy of the file.');
        }

        return $newFilePath;
    }
}
