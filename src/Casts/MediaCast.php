<?php

namespace Outl1ne\NovaMediaHub\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Outl1ne\NovaMediaHub\Models\Media;

class MediaCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Collection
    {

        if (is_null($value)) {
            return collect([]);
        }
        if ($value === 'null') {
            return collect([]);
        }
        if (is_numeric($value) || is_int($value)) {
            return Media::where('id', $value)->get();
        }
        $value = json_decode($value, true);
        if (is_numeric($value)) {
            return Media::where('id', $value)->get();
        }

        return Media::whereIn('id', $value)->get();
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|bool|null
    {
        if (is_null($value)) {
            return null;
        }
        if ($value === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            return '["'.$value.'"]';
        }

        return json_encode($value);
    }
}
