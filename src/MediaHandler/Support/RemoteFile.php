<?php

namespace Outl1ne\NovaMediaHub\MediaHandler\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RemoteFile
{
    protected ?string $key;

    protected ?string $disk;

    protected ?string $originalFileName = null;

    public function __construct(string $key, ?string $disk = null)
    {
        $this->key = $key;
        $this->disk = $disk;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function getFilename(): string
    {
        return $this->originalFileName ?? basename($this->key);
    }

    public function getName(): string
    {
        return pathinfo($this->getFilename(), PATHINFO_FILENAME);
    }

    public function downloadFileToCurrentFilesystem(): string
    {
        $tempFilePath = FileHelpers::getTemporaryFilePath();

        if (! empty($this->disk)) {
            // Ensure the stream is properly closed
            $stream = Storage::disk($this->disk)->readStream($this->getKey());
            if ($stream) {
                Storage::disk('local')->put($tempFilePath, stream_get_contents($stream));
                fclose($stream);
            }
        } else {
            // Use Http::sink() to directly download the file to the temporary path
            Http::sink($tempFilePath)->get($this->getKey());
        }

        $this->originalFileName = $this->getFilename(); // Keep the original file name
        $this->key = $tempFilePath; // Update the key to the temporary file path

        return $tempFilePath;
    }
}
