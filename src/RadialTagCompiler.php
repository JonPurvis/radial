<?php

namespace Radial;

/**
 * Precompiler that converts <radial:component> tags to <x-radial::component>
 * so Laravel's Blade compiler will compile them (it only compiles x- and x: tags).
 */
class RadialTagCompiler
{
    public function compile(string $value): string
    {
        $value = preg_replace('/<radial:([\w\-\.]+)/', '<x-radial::$1', $value);

        $value = preg_replace('/<\/radial:([\w\-\.]+)\s*>/', '</x-radial::$1>', $value);

        return $value;
    }
}
