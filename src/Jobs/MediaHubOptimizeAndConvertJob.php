<?php

namespace Outl1ne\NovaMediaHub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Outl1ne\NovaMediaHub\Exceptions\FileDoesNotExistException;
use Outl1ne\NovaMediaHub\MediaHandler\Support\FileHelpers;
use Outl1ne\NovaMediaHub\MediaHandler\Support\Filesystem;
use Outl1ne\NovaMediaHub\MediaHandler\Support\MediaOptimizer;
use Outl1ne\NovaMediaHub\MediaHub;
use Outl1ne\NovaMediaHub\Models\Media;
use Spatie\Image\Exceptions\CouldNotLoadImage;

class MediaHubOptimizeAndConvertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    protected ?int $mediaId = null;


    public function __construct(Media $media)
    {
        $this->mediaId = $media->id;
        $this->onQueue(MediaHub::getImageConversionsJobQueue());
    }

    public function handle(): void
    {
        $media = MediaHub::getQuery()->find($this->mediaId);
        if (! $media) {
            return;
        }

        $fileSystem = app(Filesystem::class);

        $localFilePath = $fileSystem->copyFromMediaLibrary($media, FileHelpers::getTemporaryFilePath('job-tmp-media-'));
        if (! $localFilePath) {
            return;
        }

        try {
            MediaOptimizer::optimizeOriginalImage($media, $localFilePath);
            $this->handleConversions($media, $localFilePath);
        } catch (FileDoesNotExistException|CouldNotLoadImage $e) {
            report($e);
        } finally {
            $this->cleanupFile($localFilePath);
        }
    }

    /**
     * @throws FileDoesNotExistException
     * @throws CouldNotLoadImage
     */
    private function handleConversions(Media $media, string $localFilePath): void
    {
        $fileSystem = app(Filesystem::class);
        $conversions = MediaHub::getConversionForMedia($media);
        foreach ($conversions as $conversionName => $conversion) {
            $copyOfOriginal = $fileSystem->makeTemporaryCopy($localFilePath);
            if ($copyOfOriginal) {
                MediaOptimizer::makeConversion($media, $copyOfOriginal, $conversionName, $conversion);
                $this->cleanupFile($copyOfOriginal);
            }
        }
    }

    private function cleanupFile(string $filePath): void
    {
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}
