<?php

namespace Outl1ne\NovaMediaHub\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Outl1ne\NovaMediaHub\MediaHub;

/**
 * @property int $id
 * @property string|null $uuid
 * @property string $collection_name
 * @property string $disk
 * @property string $file_name
 * @property int $size
 * @property string|null $mime_type
 * @property string $original_file_hash
 * @property string|null $conversions_disk
 * @property array $data
 * @property array $conversions
 * @property Carbon|null $optimized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $path
 * @property-read string $conversions_path
 * @property-read string $url
 * @property-read string $thumbnail_url
 */
class Media extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'conversions' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'optimized_at' => 'datetime',
        ];
    }

    protected $appends = ['url', 'thumbnail_url'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(MediaHub::getTableName());
    }

    public function getPathAttribute(): string
    {
        $pathMaker = MediaHub::getPathMaker();

        return $pathMaker->getPath($this);
    }

    public function getConversionsPathAttribute(): string
    {
        $pathMaker = MediaHub::getPathMaker();

        return $pathMaker->getConversionsPath($this);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path.$this->file_name);
    }

    public function getUrl(?string $forConversion = null): ?string
    {
        if (! $forConversion) {
            return $this->url;
        }

        $conversionName = $this->conversions[$forConversion] ?? null;
        if (empty($conversionName)) {
            return null;
        }

        return Storage::disk($this->conversions_disk)->url($this->conversions_path.$conversionName);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->getUrl(MediaHub::getThumbnailConversionName());
    }

    public function formatForNova(): array
    {
        return [
            'id' => $this->id,
            'collection_name' => $this->collection_name,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'file_name' => $this->file_name,
            'data' => $this->data,
            'conversions' => $this->conversions,
        ];
    }
}
