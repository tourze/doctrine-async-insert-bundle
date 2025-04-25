<?php

namespace Tourze\DoctrineAsyncBundle\Tests\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\DoctrineAsyncBundle\EventSubscriber\DoctrineCleanSubscriber;
use Tourze\DoctrineAsyncBundle\Service\DoctrineService;

/**
 * DoctrineCleanSubscriber 测试类
 */
class DoctrineCleanSubscriberTest extends TestCase
{
    private MockObject|ManagerRegistry $registry;
    private MockObject|Connection $connection;
    private MockObject|LoggerInterface $logger;
    private MockObject|DoctrineService $doctrineService;
    private DoctrineCleanSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->doctrineService = $this->createMock(DoctrineService::class);

        $this->subscriber = new DoctrineCleanSubscriber(
            $this->registry,
            $this->connection,
            $this->logger,
            $this->doctrineService
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = DoctrineCleanSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::TERMINATE, $events);
        $this->assertCount(2, $events[KernelEvents::TERMINATE]);

        $this->assertEquals(['executeAllCollectSql', 0], $events[KernelEvents::TERMINATE][0]);
        $this->assertEquals(['clearLogs', -1224], $events[KernelEvents::TERMINATE][1]);
    }

    public function testExecuteAllCollectSqlWithSuccess(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];

        // 添加一条记录
        $this->subscriber->addTableRecord($tableName, $params);

        // 预期连接将执行插入操作
        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params);

        $this->subscriber->executeAllCollectSql();

        // 验证记录被清除 - 通过 reset 方法触发验证
        $this->subscriber->reset();

        // 再次执行确保记录已被清除，不会再有插入
        $this->connection->expects($this->never())
            ->method('insert');

        $this->subscriber->executeAllCollectSql();
    }

    public function testExecuteAllCollectSqlWithDuplicateEntry(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];
        $exception = new \Exception('Duplicate entry');

        // 添加一条记录
        $this->subscriber->addTableRecord($tableName, $params);

        // 模拟插入时出现重复数据异常
        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params)
            ->willThrowException($exception);

        // 模拟重复数据检测返回 true
        $this->doctrineService->expects($this->once())
            ->method('isDuplicateEntryException')
            ->with($exception)
            ->willReturn(true);

        // 执行所有收集的 SQL
        $this->subscriber->executeAllCollectSql();

        // 验证记录被清除 - 执行不会触发插入
        $this->connection->expects($this->never())
            ->method('insert');

        $this->subscriber->executeAllCollectSql();
    }

    /**
     * 使用部分模拟来测试记录重试，基本功能测试
     */
    public function testAddTableRecordAndReset(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];

        // 添加记录
        $this->subscriber->addTableRecord($tableName, $params);

        // 通过反射检查内部状态
        $reflectionClass = new \ReflectionClass($this->subscriber);
        $property = $reflectionClass->getProperty('newTableRecords');
        $property->setAccessible(true);
        $records = $property->getValue($this->subscriber);

        // 验证记录已添加，并且重试次数为0
        $this->assertCount(1, $records);
        $this->assertEquals($tableName, $records[0][0]);
        $this->assertEquals($params, $records[0][1]);
        $this->assertEquals(0, $records[0][2]);

        // 重置
        $this->subscriber->reset();

        // 验证记录已清除
        $recordsAfterReset = $property->getValue($this->subscriber);
        $this->assertEmpty($recordsAfterReset);
    }

    /**
     * 测试实现接口
     */
    public function testImplementsInterfaces(): void
    {
        $this->assertInstanceOf(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class, $this->subscriber);
        $this->assertInstanceOf(\Symfony\Contracts\Service\ResetInterface::class, $this->subscriber);
    }

    /**
     * 测试服务是否可以重置
     */
    public function testResetInterface(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];

        // 添加记录
        $this->subscriber->addTableRecord($tableName, $params);

        // 调用 ResetInterface 中定义的 reset 方法
        $this->subscriber->reset();

        // 通过反射检查内部状态
        $reflectionClass = new \ReflectionClass($this->subscriber);
        $property = $reflectionClass->getProperty('newTableRecords');
        $property->setAccessible(true);
        $records = $property->getValue($this->subscriber);

        // 验证记录已清除
        $this->assertEmpty($records);
    }

    /**
     * 简化的 clearLogs 测试
     */
    public function testClearLogsWithNoManagers(): void
    {
        // 模拟没有实体管理器
        $this->registry->expects($this->once())
            ->method('getManagers')
            ->willReturn([]);

        // 执行方法
        $this->subscriber->clearLogs();

        // 如果没有异常，测试通过
        $this->assertTrue(true);
    }
}
