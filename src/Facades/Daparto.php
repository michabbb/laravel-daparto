<?php

namespace macropage\laravel_daparto\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class DapartoF
 * @package macropage\laravel_daparto
 */
class Daparto extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string {
        return 'daparto';
    }
}
