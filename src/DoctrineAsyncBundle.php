<?php

namespace Tourze\DoctrineAsyncBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class DoctrineAsyncBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            \Tourze\Symfony\Async\AsyncBundle::class => ['all' => true],
        ];
    }
}
