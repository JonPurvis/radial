<?php

namespace Radial;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class RadialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerBladeComponents();
    }

    protected function registerBladeComponents(): void
    {
        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/radial',
            'radial'
        );
    }
}
