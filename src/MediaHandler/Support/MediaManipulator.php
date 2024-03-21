<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Outl1ne\NovaMediaHub\MediaHub;
use Outl1ne\NovaMediaHub\Models\Media;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class MediaManipulator
{
    /**
     * Add manipulations to $manipulations to define new constraits/formats etc.
     * The rest (handling, mime type, saving etc) is handled by the MediaOptimizer.
     *
     * Return null if you don't want to modify the original image and store it as-is.
     *
     * @return ?Image
     */
    public function manipulateOriginal(Media $media, Image &$manipulations): void
    {
        if (! $origOptimRules = $this->shouldOptimizeOriginal($media)) {
            return;
        }

        if ($maxDimens = $origOptimRules['max_dimensions']) {
            $manipulations->fit(Fit::Max, $maxDimens, $maxDimens);
        }
    }

    public function manipulateConversion(Media $media, Image &$manipulations, string $collectionName, array $conversionConfig): void
    {
        // Check has necessary data for resize
        $cFormat = $conversionConfig['format'] ?? null;
        $cFitMethod = $conversionConfig['fit'] ?? null;
        $cWidth = $conversionConfig['width'] ?? null;
        $cHeight = $conversionConfig['height'] ?? null;

        $manipulations->fit($cFitMethod, $cWidth, $cHeight);

        if ($cFormat) {
            $manipulations->format($cFormat);
        }
    }

    protected function shouldOptimizeOriginal(Media $media)
    {
        $ogRules = config('nova-media-hub.original_image_manipulations');
        if (! $ogRules['optimize']) {
            return false;
        }

        $allConversions = MediaHub::getConversions();

        $allOgDisabled = $allConversions['*']['original'] ?? null;
        $appliesToCollectionConv = $allConversions[$media->collection_name]['original'] ?? null;
        if ($allOgDisabled === false || $appliesToCollectionConv === false) {
            return false;
        }

        return $ogRules;
    }
}
