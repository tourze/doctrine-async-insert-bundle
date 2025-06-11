<?php

namespace Tourze\DoctrineAsyncInsertBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineAsyncInsertBundle\DoctrineAsyncInsertBundle;

/**
 * DoctrineAsyncInsertBundle 测试类
 */
class DoctrineAsyncInsertBundleTest extends TestCase
{
    public function testBundleInheritance(): void
    {
        $bundle = new DoctrineAsyncInsertBundle();

        $this->assertInstanceOf(Bundle::class, $bundle);
    }
}
