<?php

namespace Radial;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class RadialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerBladeComponents();
        $this->registerTagPrecompiler();
    }

    protected function registerBladeComponents(): void
    {
        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/radial',
            'radial'
        );
    }

    protected function registerTagPrecompiler(): void
    {
        $compiler = new RadialTagCompiler;

        // Run before compileComponentTags so <radial:foo> becomes <x-radial::foo> and gets compiled
        Blade::prepareStringsForCompilationUsing(function (string $string) use ($compiler): string {
            return $compiler->compile($string);
        });
    }
}
