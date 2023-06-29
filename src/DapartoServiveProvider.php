<?php

namespace macropage\laravel_daparto;

use Illuminate\Support\ServiceProvider;
use macropage\laravel_daparto\Console\DapartoCommandListOrders;
use macropage\laravel_daparto\Console\DapartoCommandsetDone;

class DapartoServiveProvider extends ServiceProvider {

    protected string $configPath = __DIR__ . '/../config/daparto.php';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void {
        $this->mergeConfigFrom($this->configPath, 'daparto');
        $this->app->singleton('Daparto', fn() => new Daparto());
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->configPath => config_path('daparto.php')], 'config');
            $this->commands([
                                DapartoCommandListOrders::class,
                                DapartoCommandsetDone::class
                            ]);
        }
    }
}
