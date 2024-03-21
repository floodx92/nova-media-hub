<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Illuminate\Support\Str;
use Outl1ne\NovaMediaHub\MediaHub;
use Outl1ne\NovaMediaHub\Models\Media;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;

use Spatie\ImageOptimizer\OptimizerChainFactory;

class MediaOptimizer
{
    /**
     * @throws CouldNotLoadImage
     */
    public static function optimizeOriginalImage(Media $media, ?string $localFilePath = null): void
    {
        if (! empty($media->optimized_at) || ! Str::startsWith($media->mime_type, 'image') || ! MediaHub::isOptimizable($media)) {
            return;
        }

        $fileSystem = app(Filesystem::class);
        $localFilePath = self::ensureLocalFilePath($media, $localFilePath, $fileSystem);

        if (! $localFilePath) {
            return;
        }

        $fileSystem->copyFileToMediaLibrary($localFilePath, $media, $media->file_name, Filesystem::TYPE_ORIGINAL, false);

        self::performOptimization($localFilePath);

        $media->update([
            'mime_type' => FileHelpers::getMimeType($localFilePath),
            'size' => filesize($localFilePath) ?: 0,
            'optimized_at' => now(),
        ]);
    }

    /**
     * @throws CouldNotLoadImage
     */
    public static function makeConversion(Media $media, string $localFilePath, string $conversionName, array $conversionConfig): void
    {
        if (! empty($media->conversion[$conversionName]) || ! Str::startsWith($media->mime_type, 'image') || ! MediaHub::isOptimizable($media)) {
            return;
        }

        $fileSystem = app(Filesystem::class);
        $localFilePath = self::ensureLocalFilePath($media, $localFilePath, $fileSystem);

        if (! $localFilePath) {
            return;
        }

        $manipulations = Image::load($localFilePath);
        MediaHub::getMediaManipulator()->manipulateConversion($media, $manipulations, $conversionName, $conversionConfig);
        $manipulations->optimize(OptimizerChainFactory::create());
        $manipulations->save($localFilePath);

        $conversionFileName = MediaHub::getPathMaker()->getConversionFileName($media, $conversionName);
        $fileSystem->copyFileToMediaLibrary($localFilePath, $media, $conversionFileName, Filesystem::TYPE_CONVERSION, false);

        $media->update([
            'conversions' => array_merge($media->conversions ?? [], [$conversionName => $conversionFileName]),
        ]);
    }


    private static function performOptimization(string $localFilePath): void
    {
        $optimizerChain = OptimizerChainFactory::create();

        $optimizerChain->optimize($localFilePath);
    }

    private static function ensureLocalFilePath(Media $media, ?string $localFilePath, Filesystem $fileSystem): ?string
    {
        if (! $localFilePath || ! is_file($localFilePath)) {
            $localFilePath = FileHelpers::getTemporaryFilePath();

            return $fileSystem->copyFromMediaLibrary($media, $localFilePath);
        }

        return $localFilePath;
    }
}
