<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class DoctrineAsyncInsertExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function getAlias(): string
    {
        return 'doctrine_async_insert';
    }
}
