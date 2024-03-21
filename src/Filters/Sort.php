<?php

namespace Outl1ne\NovaMediaHub\Filters;

use Closure;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Sort
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle($query, Closure $next)
    {
        if (empty(request()->get('orderBy'))) {
            return $next($query)->orderBy('updated_at', 'DESC');
        }

        [$column, $direction] = Str::of(request()->get('orderBy', ''))
            ->explode(':')
            ->toArray();

        return $next($query)->orderBy($column, $direction);
    }
}
