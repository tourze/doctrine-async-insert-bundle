<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Tests\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;
use Tourze\DoctrineDirectInsertBundle\Service\DirectInsertService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncInsertService::class)]
#[RunTestsInSeparateProcesses]
final class AsyncInsertServiceTest extends AbstractIntegrationTestCase
{
    private AsyncInsertService $service;

    private MessageBusInterface $messageBus;

    private DirectInsertService $directInsertService;

    public function testIsDuplicateEntryExceptionWithUniqueConstraintViolation(): void
    {
        // 使用反射创建异常，因为构造函数需要特定参数
        $reflection = new \ReflectionClass(UniqueConstraintViolationException::class);
        $this->assertTrue($reflection->isSubclassOf(\Throwable::class));

        // 测试类型检查
        $exception = $this->createMock(UniqueConstraintViolationException::class);
        $result = $this->service->isDuplicateEntryException($exception);
        $this->assertTrue($result);
    }

    public function testIsDuplicateEntryExceptionWithDuplicateEntryMessage(): void
    {
        $exception = new \Exception('Duplicate entry for key');

        $result = $this->service->isDuplicateEntryException($exception);

        $this->assertTrue($result);
    }

    public function testIsDuplicateEntryExceptionWithIntegrityConstraintViolation(): void
    {
        $exception = new \Exception('Integrity constraint violation');

        $result = $this->service->isDuplicateEntryException($exception);

        $this->assertTrue($result);
    }

    public function testIsDuplicateEntryExceptionWithOtherException(): void
    {
        $exception = new \Exception('Some other error');

        $result = $this->service->isDuplicateEntryException($exception);

        $this->assertFalse($result);
    }

    public function testAsyncInsertMethodExists(): void
    {
        // 验证方法签名通过反射
        $reflection = new \ReflectionMethod($this->service, 'asyncInsert');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(3, $reflection->getNumberOfParameters());

        // 验证参数类型
        $parameters = $reflection->getParameters();
        $this->assertEquals('object', $parameters[0]->getName());
        $this->assertEquals('delayMs', $parameters[1]->getName());
        $this->assertEquals('allowDuplicate', $parameters[2]->getName());
    }

    public function testServiceCanBeInstantiated(): void
    {
        // 验证服务可以正常实例化和注入
        $this->assertInstanceOf(AsyncInsertService::class, $this->service);
        $this->assertInstanceOf(MessageBusInterface::class, $this->messageBus);
        $this->assertInstanceOf(DirectInsertService::class, $this->directInsertService);
    }

    protected function onSetUp(): void
    {
        $this->service = self::getService(AsyncInsertService::class);
        $this->messageBus = self::getService(MessageBusInterface::class);
        $this->directInsertService = self::getService(DirectInsertService::class);
    }
}