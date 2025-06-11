<?php

namespace Tourze\DoctrineAsyncInsertBundle\Tests\MessageHandler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\MessageHandler\InsertTableHandler;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;
use Yiisoft\Json\Json;

/**
 * InsertTableHandler 测试类
 */
class InsertTableHandlerTest extends TestCase
{
    private MockObject|LoggerInterface $logger;
    private MockObject|Connection $connection;
    private MockObject|AsyncInsertService $doctrineService;
    private InsertTableHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->doctrineService = $this->createMock(AsyncInsertService::class);

        $this->handler = new InsertTableHandler(
            $this->logger,
            $this->connection,
            $this->doctrineService
        );
    }

    public function testInvokeSuccessful(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1', 'column2' => ['nested' => 'array']];
        $expectedParams = [
            'column1' => 'value1',
            'column2' => Json::encode(['nested' => 'array'])
        ];

        $message = new InsertTableMessage();
        $message->setTableName($tableName);
        $message->setParams($params);

        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $expectedParams);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('异步插入数据库成功', [
                'tableName' => $tableName,
                'params' => $expectedParams,
            ]);

        ($this->handler)($message);
    }

    public function testInvokeDuplicateEntryAllowed(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];
        $exception = new \Exception('Duplicate entry');

        $message = new InsertTableMessage();
        $message->setTableName($tableName);
        $message->setParams($params);
        $message->setAllowDuplicate(true);

        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params)
            ->willThrowException($exception);

        $this->doctrineService->expects($this->once())
            ->method('isDuplicateEntryException')
            ->with($exception)
            ->willReturn(true);

        // 当允许重复记录时，不应该记录错误日志
        $this->logger->expects($this->never())
            ->method('error')
            ->with('异步插入数据库时发现重复数据', $this->anything());

        ($this->handler)($message);
    }

    public function testInvokeDuplicateEntryNotAllowed(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];
        $exception = new \Exception('Duplicate entry');

        $message = new InsertTableMessage();
        $message->setTableName($tableName);
        $message->setParams($params);
        $message->setAllowDuplicate(false);

        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params)
            ->willThrowException($exception);

        $this->doctrineService->expects($this->once())
            ->method('isDuplicateEntryException')
            ->with($exception)
            ->willReturn(true);

        // 当不允许重复记录时，应该记录错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with('异步插入数据库时发现重复数据', [
                'tableName' => $tableName,
                'params' => $params,
                'exception' => $exception,
            ]);

        ($this->handler)($message);
    }

    public function testInvokeOtherException(): void
    {
        $tableName = 'test_table';
        $params = ['column1' => 'value1'];
        $exception = new \Exception('General database error');

        $message = new InsertTableMessage();
        $message->setTableName($tableName);
        $message->setParams($params);

        $this->connection->expects($this->once())
            ->method('insert')
            ->with($tableName, $params)
            ->willThrowException($exception);

        $this->doctrineService->expects($this->once())
            ->method('isDuplicateEntryException')
            ->with($exception)
            ->willReturn(false);

        // 其他异常应该记录错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with('异步插入数据库失败', [
                'tableName' => $tableName,
                'params' => $params,
                'exception' => $exception,
            ]);

        ($this->handler)($message);
    }
}
