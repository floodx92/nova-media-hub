<?php

namespace Outl1ne\NovaMediaHub\Nova\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Resource;
use Outl1ne\NovaMediaHub\MediaHub;

class Media extends Resource
{
    public static $title = 'key';

    public static mixed $model = null;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    public function __construct($resource)
    {
        self::$model = MediaHub::getMediaModel();
        parent::__construct($resource);
    }

    public function fields(Request $request)
    {
        return [];
    }
}
