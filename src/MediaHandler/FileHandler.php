<?php

namespace Outl1ne\NovaMediaHub\MediaHandler;

use Illuminate\Support\Facades\File;
use Outl1ne\NovaMediaHub\Exceptions\DiskDoesNotExistException;
use Outl1ne\NovaMediaHub\Exceptions\FileTooLargeException;
use Outl1ne\NovaMediaHub\Exceptions\FileValidationException;
use Outl1ne\NovaMediaHub\Exceptions\MimeTypeNotAllowedException;
use Outl1ne\NovaMediaHub\Exceptions\NoFileProvidedException;
use Outl1ne\NovaMediaHub\Exceptions\UnknownFileTypeException;
use Outl1ne\NovaMediaHub\Jobs\MediaHubOptimizeAndConvertJob;
use Outl1ne\NovaMediaHub\MediaHandler\Support\Base64File;
use Outl1ne\NovaMediaHub\MediaHandler\Support\FileHelpers;
use Outl1ne\NovaMediaHub\MediaHandler\Support\Filesystem;
use Outl1ne\NovaMediaHub\MediaHandler\Support\RemoteFile;
use Outl1ne\NovaMediaHub\MediaHub;
use Outl1ne\NovaMediaHub\Models\Media;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileHandler
{
    protected Filesystem $filesystem;

    protected mixed $file;

    protected string $fileName = '';

    protected string $pathToFile = '';

    protected ?string $diskName = null;

    protected ?string $conversionsDiskName = null;

    protected string $collectionName = '';

    protected array $modelData = [];

    protected bool $deleteOriginal = false;

    public function __construct()
    {
        $this->filesystem = app(Filesystem::class);
    }

    /**
     * @throws FileValidationException
     * @throws UnknownFileTypeException
     */
    public static function fromFile(mixed $file): self
    {
        return (new static)->withFile($file);
    }

    /**
     * @throws FileValidationException
     * @throws UnknownFileTypeException
     */
    public function withFile(mixed $file): self
    {
        $this->file = $file;

        $this->fileName = match (true) {
            is_string($file) => $this->handleStringFile($file),
            $file instanceof RemoteFile => $this->handleRemoteFile($file),
            $file instanceof UploadedFile => $this->handleUploadedFile($file),
            $file instanceof SymfonyFile, $file instanceof Base64File => $this->handleFileInterface($file),
            default => throw new UnknownFileTypeException()
        };

        return $this;
    }

    private function handleStringFile(string $file): string
    {
        $this->pathToFile = $file;

        return pathinfo($file, PATHINFO_BASENAME);
    }

    private function handleRemoteFile(RemoteFile $file): string
    {
        $file->downloadFileToCurrentFilesystem();
        $this->pathToFile = $file->getKey();

        return $file->getFilename();
    }

    /**
     * @throws FileValidationException
     */
    private function handleUploadedFile(UploadedFile $file): string
    {
        if ($file->getError()) {
            throw new FileValidationException($file->getErrorMessage());
        }
        $this->pathToFile = $file->getPath().'/'.$file->getFilename();

        return $file->getClientOriginalName();
    }

    private function handleFileInterface($file): string
    {
        $this->pathToFile = $file->getPath().'/'.$file->getFilename();

        return pathinfo($file->getFilename(), PATHINFO_BASENAME);
    }

    public function deleteOriginal(bool $deleteOriginal = true): self
    {
        $this->deleteOriginal = $deleteOriginal;

        return $this;
    }

    public function withCollection(string $collectionName): self
    {
        $this->collectionName = $collectionName;

        return $this;
    }

    public function storeOnDisk(?string $diskName): self
    {
        $this->diskName = $diskName;

        return $this;
    }

    public function storeConversionOnDisk(?string $diskName): self
    {
        $this->conversionsDiskName = $diskName;

        return $this;
    }

    public function withModelData(array $modelData): self
    {
        $this->modelData = $modelData;

        return $this;
    }

    /**
     * @throws FileValidationException
     * @throws NoFileProvidedException
     * @throws UnknownFileTypeException
     * @throws DiskDoesNotExistException
     */
    public function save(mixed $file = null): ?Media
    {
        if (! empty($file)) {
            $this->withFile($file);
        }

        if (empty($this->file) || ! is_file($this->pathToFile)) {
            throw new NoFileProvidedException();
        }

        try {
            return $this->saveFile();
        } finally {
            if ($this->deleteOriginal && is_file($this->pathToFile)) {
                unlink($this->pathToFile);
            }
        }
    }

    /**
     * @throws DiskDoesNotExistException
     */
    private function saveFile(): ?Media
    {
        // Check if file already exists
        $fileHash = FileHelpers::getFileHash($this->pathToFile);
        $existingMedia = MediaHub::getQuery()->where('original_file_hash', $fileHash)->first();
        if ($existingMedia) {
            $existingMedia->updated_at = now();
            $existingMedia->save();
            $existingMedia->wasExisting = true;

            // Delete original
            if ($this->deleteOriginal && is_file($this->pathToFile)) {
                unlink($this->pathToFile);
            }

            if (! $existingMedia->optimized_at) {
                MediaHubOptimizeAndConvertJob::dispatch($existingMedia);
            }

            return $existingMedia;
        }

        $sanitizedFileName = FileHelpers::sanitizeFileName($this->fileName);
        [$fileName, $rawExtension] = FileHelpers::splitNameAndExtension($sanitizedFileName);
        $extension = File::guessExtension($this->pathToFile) ?? $rawExtension;
        $mimeType = File::mimeType($this->pathToFile);
        $fileSize = File::size($this->pathToFile);

        $this->fileName = MediaHub::getFileNamer()->formatFileName($fileName, $extension);

        // Validate file
        $fileValidator = MediaHub::getFileValidator();
        try {
            $fileValidator->validateFile($this->collectionName, $this->pathToFile, $this->fileName, $extension, $mimeType, $fileSize);
        } catch (FileTooLargeException|MimeTypeNotAllowedException $e) {
            report($e);
            return null;
        }

        $mediaClass = MediaHub::getMediaModel();
        $media = new $mediaClass($this->modelData ?? []);

        $media->file_name = $this->fileName;
        $media->collection_name = $this->collectionName;
        $media->size = $fileSize;
        $media->mime_type = $mimeType;
        $media->original_file_hash = $fileHash;
        $media->data = [];
        $media->conversions = [];

        $media->disk = $this->getDiskName();
        $this->ensureDiskExists($media->disk);

        $media->conversions_disk = $this->getConversionsDiskName();
        $this->ensureDiskExists($media->conversions_disk);

        $media->save();

        $this->filesystem->copyFileToMediaLibrary($this->pathToFile, $media, $this->fileName, Filesystem::TYPE_ORIGINAL, $this->deleteOriginal);

        MediaHubOptimizeAndConvertJob::dispatch($media);

        return $media;
    }

    // Helpers
    protected function getDiskName(): string
    {
        return $this->diskName ?: config('nova-media-hub.disk_name');
    }

    protected function getConversionsDiskName(): string
    {
        return $this->conversionsDiskName ?: config('nova-media-hub.conversions_disk_name');
    }

    /**
     * @throws DiskDoesNotExistException
     */
    protected function ensureDiskExists(string $diskName): void
    {
        if (is_null(config("filesystems.disks.{$diskName}"))) {
            throw new DiskDoesNotExistException($diskName);
        }
    }
}
