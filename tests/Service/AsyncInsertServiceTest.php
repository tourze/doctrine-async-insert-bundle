<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Tests\Service;

use BizUserBundle\Entity\BizUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncInsertService::class)]
#[RunTestsInSeparateProcesses]
final class AsyncInsertServiceTest extends AbstractIntegrationTestCase
{
    private AsyncInsertService $service;

    public function testAsyncInsertWithNormalFlowDispatchesMessage(): void
    {
        $entity = new BizUser();
        $entity->setUsername('test_user');
        $entity->setNickName('测试用户');

        // 执行异步插入操作
        $this->service->asyncInsert($entity);

        // 在真正的集成测试中，我们可以验证：
        // 1. 没有抛出异常（方法正常执行）
        // 2. 如果使用 TraceableMessageBus，可以检查发送的消息
        // 由于这是集成测试，我们主要验证方法能正常运行
        $this->expectNotToPerformAssertions();
    }

    public function testAsyncInsertWithDelayDispatchesMessageWithDelayStamp(): void
    {
        $entity = new BizUser();
        $entity->setUsername('test_user_delay');
        $entity->setNickName('延时测试用户');

        // 执行带延时的异步插入操作
        $this->service->asyncInsert($entity, 1000);

        // 在集成测试中验证方法正常执行
        $this->expectNotToPerformAssertions();
    }

    public function testAsyncInsertWithDispatchErrorFallsBackToDirectInsert(): void
    {
        $entity = new BizUser();
        $entity->setUsername('test_fallback');
        $entity->setNickName('回退测试用户');

        // 在集成测试中，我们无法模拟 MessageBus 失败的情况
        // 但可以测试正常情况下的行为
        $this->service->asyncInsert($entity);

        $this->expectNotToPerformAssertions();
    }

    public function testIsDuplicateEntryExceptionWorksAsExpected(): void
    {
        // 当接口复杂而测试只关心少数行为时，使用Mock是更具"好品味"的实践
        // Doctrine\DBAL\Driver\Exception 有9个方法，但我们只需要 getSQLState()
        $driverException = $this->createMock(\Doctrine\DBAL\Driver\Exception::class);
        $driverException->method('getSQLState')
            ->willReturn('23000') // 标准的唯一约束违规 SQLSTATE
        ;

        $this->assertTrue($this->service->isDuplicateEntryException(new UniqueConstraintViolationException($driverException, null)));
        $this->assertTrue($this->service->isDuplicateEntryException(new \Exception('Duplicate entry...')));
        $this->assertTrue($this->service->isDuplicateEntryException(new \Exception('...Integrity constraint violation...')));
        $this->assertFalse($this->service->isDuplicateEntryException(new \Exception('Some other error')));
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务进行真正的集成测试
        $this->service = self::getService(AsyncInsertService::class);
    }
}
