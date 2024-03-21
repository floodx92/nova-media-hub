<?php

namespace Outl1ne\NovaMediaHub;

use Illuminate\Http\Request;
use Laravel\Nova\Exceptions\NovaException;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Outl1ne\NovaMediaHub\Exceptions\FileDoesNotExistException;
use Outl1ne\NovaMediaHub\Exceptions\NoFileProvidedException;
use Outl1ne\NovaMediaHub\MediaHandler\FileHandler;
use Outl1ne\NovaMediaHub\MediaHandler\Support\Base64File;
use Outl1ne\NovaMediaHub\MediaHandler\Support\FileNamer;
use Outl1ne\NovaMediaHub\MediaHandler\Support\FileValidator;
use Outl1ne\NovaMediaHub\MediaHandler\Support\MediaManipulator;
use Outl1ne\NovaMediaHub\MediaHandler\Support\PathMaker;
use Outl1ne\NovaMediaHub\MediaHandler\Support\RemoteFile;
use Outl1ne\NovaMediaHub\Models\Media;

class MediaHub extends Tool
{
    public bool $hideFromMenu = false;

    public array $customFields = [];

    public function __construct()
    {
        parent::__construct();

        $this->withCustomFields([
            'alt' => __('novaMediaHub.altTextTitle'),
            'title' => __('novaMediaHub.titleTextTitle'),
        ]);
    }

    public function boot(): void
    {
        Nova::script('nova-media-hub', __DIR__.'/../dist/js/entry.js');
        Nova::style('nova-media-hub', __DIR__.'/../dist/css/entry.css');

        Nova::provideToScript([
            'novaMediaHub' => $this->getScriptData(),
        ]);
    }

    private function getScriptData(): array
    {
        return [
            'basePath' => self::getBasePath(),
            'canCreateCollections' => self::userCanCreateCollections(),
            'locales' => self::getLocales(),
            'mediaDataFields' => $this->customFields,
        ];
    }

    /**
     * Allows custom (text) fields and data to be included with each media item.
     *
     * @param  array  $fields  Key-value pairs of fields where key is the field attribute
     *                         and value is the string displayed to the user.
     *
     * For example: ['copyright' => __('Copyright')]
     * @param  bool  $overwrite  Optionally force overwrite pre-existing fields.
     * @return self
     **/
    public function withCustomFields(array $fields, bool $overwrite = false): static
    {
        $this->customFields = $overwrite ? $fields : array_merge($this->customFields, $fields);

        return $this;
    }

    /**
     * @throws NovaException
     */
    public function menu(Request $request)
    {
        return $this->hideFromMenu ? null : MenuSection::make(__('novaMediaHub.navigationItemTitle'))
            ->path(self::getBasePath())
            ->icon('photograph');
    }

    public static function getDataFields(): array
    {
        $mediaHubTool = static::getSelfTool();

        return $mediaHubTool?->customFields ?? [];
    }

    public static function getSelfTool(): ?MediaHub
    {
        return collect(Nova::registeredTools())->first(fn ($tool) => $tool instanceof MediaHub);
    }

    /**
     * @throws NoFileProvidedException
     * @throws FileDoesNotExistException
     */
    public static function storeMediaFromDisk($filePath, $disk, $collectionName, $targetDisk = '', $targetConversionsDisk = ''): ?Media
    {
        $remoteFile = new RemoteFile($filePath, $disk);

        return FileHandler::fromFile($remoteFile)
            ->storeOnDisk($targetDisk)
            ->storeConversionOnDisk($targetConversionsDisk)
            ->withCollection($collectionName)
            ->save();
    }

    /**
     * @throws NoFileProvidedException
     * @throws FileDoesNotExistException
     */
    public static function storeMediaFromUrl($fileUrl, $collectionName, $targetDisk = '', $targetConversionsDisk = ''): Media
    {
        $remoteFile = new RemoteFile($fileUrl);

        return FileHandler::fromFile($remoteFile)
            ->deleteOriginal()
            ->storeOnDisk($targetDisk)
            ->storeConversionOnDisk($targetConversionsDisk)
            ->withCollection($collectionName)
            ->save();
    }

    /**
     * @throws NoFileProvidedException
     * @throws FileDoesNotExistException
     */
    public static function storeMediaFromBase64($base64String, $fileName, $collectionName, $targetDisk = '', $targetConversionsDisk = ''): Media
    {
        $base64File = new Base64File($base64String, $fileName);

        return FileHandler::fromFile($base64File)
            ->deleteOriginal()
            ->storeOnDisk($targetDisk)
            ->storeConversionOnDisk($targetConversionsDisk)
            ->withCollection($collectionName)
            ->save();
    }

    public static function getConversionForMedia(Media $media): array
    {
        $allConversions = static::getConversions();

        $appliesToAllConversions = $allConversions['*'] ?? [];
        $appliesToCollectionConv = $allConversions[$media->collection_name] ?? [];

        // Create merged conversions array
        $conversions = array_replace_recursive(
            $appliesToAllConversions,
            $appliesToCollectionConv,
        );

        // Remove invalid configurations
        return array_filter($conversions, function ($c) {
            if (empty($c)) {
                return false;
            }
            if (empty($c['fit'])) {
                return false;
            }
            if (empty($c['height']) && empty($c['width'])) {
                return false;
            }

            return true;
        });
    }

    public function hideFromMenu(): static
    {
        $this->hideFromMenu = true;

        return $this;
    }

    private static function getConfig(string $key, $default = null)
    {
        return config("nova-media-hub.$key", $default);
    }

    public static function getTableName()
    {
        return self::getConfig('table_name');
    }

    public static function getMediaModel()
    {
        return self::getConfig('model');
    }

    public static function getQuery()
    {
        $model = self::getMediaModel();

        return (new $model)->query();
    }

    public static function getBasePath()
    {
        return self::getConfig('base_path');
    }

    public static function getMaxFileSizeInBytes(): float|int|null
    {
        return self::getConfig('max_uploaded_file_size_in_kb', 0) > 0 ? self::getConfig('max_uploaded_file_size_in_kb', 0) * 1000 : null;
    }

    public static function getAllowedMimeTypes()
    {
        return self::getConfig('allowed_mime_types', []);
    }

    public static function getPathMaker(): PathMaker
    {
        $pathMakerClass = self::getConfig('path_maker');

        return new $pathMakerClass;
    }

    public static function getFileNamer(): FileNamer
    {
        $fileNamerClass = self::getConfig('file_namer');

        return new $fileNamerClass;
    }

    public static function getFileValidator(): FileValidator
    {
        $fileValidatorClass = self::getConfig('file_validator');

        return new $fileValidatorClass;
    }

    public static function getMediaManipulator(): MediaManipulator
    {
        $mediaManipulatorClass = self::getConfig('media_manipulator', MediaManipulator::class);

        return new $mediaManipulatorClass;
    }

    public static function isOptimizable(Media $media): bool
    {
        $optimizableMimeTypes = self::getConfig('optimizable_mime_types');

        return in_array($media->mime_type, $optimizableMimeTypes);
    }

    public static function getLocales()
    {
        return self::getConfig('locales');
    }

    public static function getDefaultCollections(): array
    {
        return self::getConfig('collections', []);
    }

    public static function userCanCreateCollections()
    {
        return self::getConfig('user_can_create_collections', false);
    }

    public static function getConversions()
    {
        return self::getConfig('image_conversions', []);
    }

    public static function getOriginalImageManipulationsJobQueue()
    {
        return self::getConfig('original_image_manipulations_job_queue', null);
    }

    public static function getImageConversionsJobQueue()
    {
        return self::getConfig('image_conversions_job_queue', null);
    }

    public static function getThumbnailConversionName()
    {
        return self::getConfig('thumbnail_conversion_name', null);
    }

    public static function getImageDriver()
    {
        return self::getConfig('image_driver', config('image.driver'));
    }
}
