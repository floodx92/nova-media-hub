<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Outl1ne\NovaMediaHub\Exceptions\FileTooLargeException;
use Outl1ne\NovaMediaHub\Exceptions\MimeTypeNotAllowedException;
use Outl1ne\NovaMediaHub\MediaHub;

class FileValidator
{
    /**
     * @throws MimeTypeNotAllowedException
     * @throws FileTooLargeException
     */
    public function validateFile(string $collectionName, string $localFilePath, string $fileName, string $extension, string $mimeType, int $fileSize): bool
    {
        $this->validateFileSize($fileSize, $fileName);
        $this->validateMimeType($mimeType, $fileName);

        return true;
    }

    /**
     * @throws FileTooLargeException
     */
    protected function validateFileSize(int $fileSize, string $fileName): void
    {
        $maxSizeBytes = MediaHub::getMaxFileSizeInBytes();

        if ($maxSizeBytes !== null && $fileSize > $maxSizeBytes) {
            throw new FileTooLargeException("File '{$fileName}' size of {$fileSize} bytes exceeds the maximum allowed of {$maxSizeBytes} bytes.");
        }
    }

    /**
     * @throws MimeTypeNotAllowedException
     */
    protected function validateMimeType(string $mimeType, string $fileName): void
    {
        $allowedMimeTypes = MediaHub::getAllowedMimeTypes();

        if (! in_array($mimeType, $allowedMimeTypes)) {
            throw new MimeTypeNotAllowedException("Mime type '{$mimeType}' is not allowed for file '{$fileName}'.");
        }
    }
}
