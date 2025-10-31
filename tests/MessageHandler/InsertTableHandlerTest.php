<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Tests\MessageHandler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\MessageHandler\InsertTableHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InsertTableHandler::class)]
#[RunTestsInSeparateProcesses]
final class InsertTableHandlerTest extends AbstractIntegrationTestCase
{
    private InsertTableHandler $handler;

    private Connection $connection;

    public function testInvokeWithSuccessfulInsertPersistsData(): void
    {
        $message = new InsertTableMessage();
        $message->setTableName('test_handler_entity');
        $message->setParams(['name' => 'test-value']);

        ($this->handler)($message);

        $result = $this->connection->fetchAssociative('SELECT * FROM test_handler_entity WHERE name = ?', ['test-value']);
        $this->assertNotFalse($result, 'Expected to find a record with name "test-value"');
        $this->assertEquals('test-value', $result['name']);
    }

    public function testInvokeWithDuplicateEntryAndAllowedLogsNothing(): void
    {
        // Arrange: Insert initial data
        $this->connection->insert('test_handler_entity', ['name' => 'duplicate-value']);

        $message = new InsertTableMessage();
        $message->setTableName('test_handler_entity');
        $message->setParams(['name' => 'duplicate-value']);
        $message->setAllowDuplicate(true);

        // Act & Assert
        // We expect a UniqueConstraintViolationException to be caught internally
        ($this->handler)($message);

        // Ensure no new record was inserted
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test_handler_entity');
        $this->assertEquals(1, $count);
    }

    public function testInvokeWithDuplicateEntryAndNotAllowedLogsError(): void
    {
        // Arrange: Insert initial data
        $this->connection->insert('test_handler_entity', ['name' => 'duplicate-value']);

        $message = new InsertTableMessage();
        $message->setTableName('test_handler_entity');
        $message->setParams(['name' => 'duplicate-value']);
        $message->setAllowDuplicate(false);

        // Act & Assert
        ($this->handler)($message);

        // 验证没有新记录被插入（因为有重复约束）
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test_handler_entity');
        $this->assertEquals(1, $count);
    }

    protected function onSetUp(): void
    {
        $this->connection = self::getService(Connection::class);
        $this->handler = self::getService(InsertTableHandler::class);

        // 创建测试表
        $this->createTestTable();
    }

    protected function onTearDown(): void
    {
        // 清理测试表
        $this->dropTestTable();
    }

    private function createTestTable(): void
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS test_handler_entity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                UNIQUE(name)
            )
        ';
        $this->connection->executeStatement($sql);
    }

    private function dropTestTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS test_handler_entity');
    }
}
