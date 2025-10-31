<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineAsyncInsertBundle\DoctrineAsyncInsertBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineAsyncInsertBundle::class)]
#[RunTestsInSeparateProcesses]
final class DoctrineAsyncInsertBundleTest extends AbstractBundleTestCase
{
}
