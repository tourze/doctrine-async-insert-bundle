<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineAsyncInsertBundle\DependencyInjection\DoctrineAsyncInsertExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * DoctrineAsyncExtension 测试类
 *
 * @internal
 */
#[CoversClass(DoctrineAsyncInsertExtension::class)]
final class DoctrineAsyncInsertExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
