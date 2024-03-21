<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

class FileNamer
{
    public function formatFileName(string $fileName, string $extension): string
    {
        return "{$fileName}.{$extension}";
    }

    public function formatConversionFileName(string $fileName, string $extension, string $conversion): string
    {
        return "{$fileName}_{$conversion}.{$extension}";
    }

    public static function encode(string $fileName): string
    {
        return urlencode($fileName);
    }
}
