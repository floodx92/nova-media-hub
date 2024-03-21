<?php

namespace Outl1ne\NovaMediaHub\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Outl1ne\NovaMediaHub\MediaHub;

class Authorize
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $tool = collect(Nova::registeredTools())->first([$this, 'matchesTool']);

        return optional($tool)->authorize($request) ? $next($request) : abort(403);
    }

    /**
     * Determine whether this tool belongs to the package.
     */
    public function matchesTool(Tool $tool): bool
    {
        return $tool instanceof MediaHub;
    }
}
