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

class MediaHubOptimizeAndConvertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    protected ?int $mediaId = null;

    private Filesystem $fileSystem;

    public function __construct(Media $media, Filesystem $fileSystem)
    {
        $this->mediaId = $media->id;
        $this->fileSystem = $fileSystem;
        $this->onQueue(MediaHub::getImageConversionsJobQueue());
    }

    public function handle(): void
    {
        $media = MediaHub::getQuery()->find($this->mediaId);
        if (! $media) {
            return;
        }

        $localFilePath = $this->fileSystem->copyFromMediaLibrary($media, FileHelpers::getTemporaryFilePath('job-tmp-media-'));
        if (! $localFilePath) {
            return;
        }

        try {
            MediaOptimizer::optimizeOriginalImage($media, $localFilePath);
            $this->handleConversions($media, $localFilePath);
        } catch (FileDoesNotExistException $e) {
            report($e);
        } finally {
            $this->cleanupFile($localFilePath);
        }
    }

    /**
     * @throws FileDoesNotExistException
     */
    private function handleConversions(Media $media, string $localFilePath): void
    {
        $conversions = MediaHub::getConversionForMedia($media);
        foreach ($conversions as $conversionName => $conversion) {
            $copyOfOriginal = $this->fileSystem->makeTemporaryCopy($localFilePath);
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
