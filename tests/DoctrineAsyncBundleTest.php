<?php

namespace Tourze\DoctrineAsyncBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineAsyncBundle\DoctrineAsyncBundle;

/**
 * DoctrineAsyncBundle 测试类
 */
class DoctrineAsyncBundleTest extends TestCase
{
    public function testBundleInheritance(): void
    {
        $bundle = new DoctrineAsyncBundle();

        $this->assertInstanceOf(Bundle::class, $bundle);
    }
}
