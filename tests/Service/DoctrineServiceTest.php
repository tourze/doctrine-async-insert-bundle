<?php

namespace Tourze\DoctrineAsyncBundle\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tourze\DoctrineAsyncBundle\EventSubscriber\DoctrineCleanSubscriber;
use Tourze\DoctrineAsyncBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncBundle\Service\DoctrineService;
use Tourze\DoctrineEntityCheckerBundle\Service\SqlFormatter;

/**
 * DoctrineService 测试类
 */
class DoctrineServiceTest extends TestCase
{
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|SqlFormatter $sqlFormatter;
    private MockObject|MessageBusInterface $messageBus;
    private MockObject|LoggerInterface $logger;
    private MockObject|DoctrineCleanSubscriber $doctrineCleanSubscriber;
    private MockObject|Connection $connection;
    private DoctrineService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->sqlFormatter = $this->createMock(SqlFormatter::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->doctrineCleanSubscriber = $this->createMock(DoctrineCleanSubscriber::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager->method('getConnection')
            ->willReturn($this->connection);

        $this->service = new DoctrineService(
            $this->entityManager,
            $this->sqlFormatter,
            $this->messageBus,
            $this->logger,
            $this->doctrineCleanSubscriber
        );
    }

    public function testIsDuplicateEntryException(): void
    {
        // 测试 UniqueConstraintViolationException 异常
        $uniqueException = $this->createMock(UniqueConstraintViolationException::class);
        $this->assertTrue($this->service->isDuplicateEntryException($uniqueException));

        // 测试包含 'Duplicate entry' 的异常消息
        $exception1 = new \Exception('Duplicate entry for key...');
        $this->assertTrue($this->service->isDuplicateEntryException($exception1));

        // 测试包含 'Integrity constraint violation' 的异常消息
        $exception2 = new \Exception('Integrity constraint violation...');
        $this->assertTrue($this->service->isDuplicateEntryException($exception2));

        // 测试不匹配的异常消息
        $exception3 = new \Exception('Some other error');
        $this->assertFalse($this->service->isDuplicateEntryException($exception3));
    }

    public function testDirectInsert(): void
    {
        $testObject = new \stdClass();
        $tableName = 'test_table';
        $params = ['column1' => 'value1', 'column2' => 'value2'];
        $lastInsertId = 123;

        $this->sqlFormatter->expects($this->once())
            ->method('getObjectInsertSql')
            ->with($this->entityManager, $testObject)
            ->willReturn([$tableName, $params]);

        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params);

        $this->connection->expects($this->once())
            ->method('lastInsertId')
            ->willReturn($lastInsertId);

        $result = $this->service->directInsert($testObject);
        $this->assertSame($lastInsertId, $result);
    }

    public function testDirectInsertWithIdInParams(): void
    {
        $testObject = new \stdClass();
        $tableName = 'test_table';
        $params = ['id' => 456, 'column1' => 'value1'];

        $this->sqlFormatter->expects($this->once())
            ->method('getObjectInsertSql')
            ->with($this->entityManager, $testObject)
            ->willReturn([$tableName, $params]);

        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params);

        // lastInsertId 不应该被调用
        $this->connection->expects($this->never())
            ->method('lastInsertId');

        $result = $this->service->directInsert($testObject);
        $this->assertSame(456, $result);
    }

    public function testAsyncInsert(): void
    {
        $testObject = new \stdClass();
        $tableName = 'test_table';
        $params = ['column1' => 'value1', 'column2' => 'value2'];
        $delayMs = 1000;

        $this->sqlFormatter->expects($this->once())
            ->method('getObjectInsertSql')
            ->with($this->entityManager, $testObject)
            ->willReturn([$tableName, $params]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (InsertTableMessage $message) use ($tableName, $params) {
                    return $message->getTableName() === $tableName &&
                        $message->getParams() === $params &&
                        $message->isAllowDuplicate() === true;
                }),
                $this->callback(function (array $stamps) use ($delayMs) {
                    return count($stamps) === 1 &&
                        $stamps[0] instanceof DelayStamp &&
                        $stamps[0]->getDelay() === $delayMs;
                })
            )
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->asyncInsert($testObject, $delayMs, true);
    }

    public function testAsyncInsertWithForceSync(): void
    {
        $testObject = new \stdClass();
        $backupEnv = $_ENV;

        try {
            $_ENV['FORCE_REPOSITORY_SYNC_INSERT'] = true;

            // 直接插入应该被调用
            $this->sqlFormatter->expects($this->once())
                ->method('getObjectInsertSql')
                ->willReturn(['test_table', ['column1' => 'value1']]);

            $this->connection->expects($this->once())
                ->method('insert');

            $this->connection->expects($this->once())
                ->method('lastInsertId')
                ->willReturn(123);

            // messageBus 不应该被调用
            $this->messageBus->expects($this->never())
                ->method('dispatch');

            $this->service->asyncInsert($testObject);
        } finally {
            $_ENV = $backupEnv;
        }
    }

    /**
     * 测试消息分发异常时的直接插入备用路径
     */
    public function testAsyncInsertDirectBackupWhenDispatchFails(): void
    {
        $testObject = new \stdClass();
        $tableName = 'test_table';
        $params = ['column1' => 'value1', 'column2' => 'value2'];
        $dispatchException = new \Exception('Message dispatch failed');

        // 创建一个部分模拟，允许测试内部逻辑而不需要模拟所有日志调用
        $partialMock = $this->getMockBuilder(DoctrineService::class)
            ->setConstructorArgs([
                $this->entityManager,
                $this->sqlFormatter,
                $this->messageBus,
                $this->logger,
                $this->doctrineCleanSubscriber
            ])
            ->onlyMethods(['directInsert'])
            ->getMock();

        $this->sqlFormatter->expects($this->once())
            ->method('getObjectInsertSql')
            ->with($this->entityManager, $testObject)
            ->willReturn([$tableName, $params]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException($dispatchException);

        // 验证日志记录
        $this->logger->expects($this->once())
            ->method('error')
            ->with('asyncInsert时发生错误，尝试直接插入数据库', $this->anything());

        // 验证直接插入被调用
        $partialMock->expects($this->once())
            ->method('directInsert')
            ->with($testObject)
            ->willReturn(123);

        $partialMock->asyncInsert($testObject);
    }

    /**
     * 测试基本的错误处理流程
     */
    public function testAsyncInsertFallbackToCleanSubscriber(): void
    {
        $testObject = new \stdClass();
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];

        // 创建部分模拟，只模拟需要的方法
        $partialMock = $this->getMockBuilder(DoctrineService::class)
            ->setConstructorArgs([
                $this->entityManager,
                $this->sqlFormatter,
                $this->messageBus,
                $this->logger,
                $this->doctrineCleanSubscriber
            ])
            ->onlyMethods(['directInsert'])
            ->getMock();

        $dispatchException = new \Exception('Dispatch failed');

        $this->sqlFormatter->expects($this->once())
            ->method('getObjectInsertSql')
            ->willReturn([$tableName, $params]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException($dispatchException);

        // directInsert 也会失败
        $insertException = new \Exception('Insert failed');
        $partialMock->expects($this->once())
            ->method('directInsert')
            ->willThrowException($insertException);

        // 验证是否添加到了 doctrineCleanSubscriber
        $this->doctrineCleanSubscriber->expects($this->once())
            ->method('addTableRecord')
            ->with($tableName, $params);

        // 执行测试
        $partialMock->asyncInsert($testObject);
    }
}
