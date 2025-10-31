<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineDirectInsertBundle\DoctrineDirectInsertBundle;
use Tourze\DoctrineEntityCheckerBundle\DoctrineEntityCheckerBundle;

class DoctrineAsyncInsertBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            DoctrineEntityCheckerBundle::class => ['all' => true],
            DoctrineDirectInsertBundle::class => ['all' => true],
        ];
    }
}
