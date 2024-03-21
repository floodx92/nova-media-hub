<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Illuminate\Support\Str;
use Outl1ne\NovaMediaHub\Exceptions\UnknownFileTypeException;

class Base64File
{
    public function __construct(protected string $base64Data, protected ?string $fileName = null)
    {
    }

    public function getBase64Data(): string
    {
        return $this->base64Data;
    }

    public function getFilename(): string
    {
        return $this->fileName ?? 'file_'.uniqid();
    }

    /**
     * @throws UnknownFileTypeException
     */
    public function saveBase64ImageToTemporaryFile(): string
    {
        $fileData = Str::after($this->base64Data, ',');
        [$mimeType, $extension] = FileHelpers::getBase64FileInfo($fileData);

        if (! $mimeType || ! $extension) {
            throw new UnknownFileTypeException('File had no detectable mime-type or extension.');
        }

        $tmpFilePath = FileHelpers::getTemporaryFilePath('base64-').".$extension";
        file_put_contents($tmpFilePath, base64_decode($fileData));
        $this->fileName = $this->getFilename().".$extension";

        return $tmpFilePath;
    }
}
