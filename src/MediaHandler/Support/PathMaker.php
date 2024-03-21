<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Outl1ne\NovaMediaHub\MediaHandler\Support\Traits\PathMakerHelpers;
use Outl1ne\NovaMediaHub\Models\Media;

class PathMaker
{
    use PathMakerHelpers;

    public function getPath(Media $media): string
    {
        return "{$this->getBasePath($media)}/";
    }

    public function getConversionsPath(Media $media): string
    {
        return "{$this->getBasePath($media)}/conversions/";
    }

    protected function getBasePath(Media $media): string
    {
        $prefix = trim(config('nova-media-hub.path_prefix', ''), '/');

        return "{$prefix}/{$media->getKey()}";
    }
}
