<?php

namespace Tourze\DoctrineAsyncInsertBundle\Tests\MessageHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\DoctrineAsyncInsertBundle\DoctrineAsyncInsertBundle;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\MessageHandler\InsertTableHandler;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class InsertTableHandlerIntegrationTest extends KernelTestCase
{
    private InsertTableHandler $handler;
    private Connection $connection;
    private MockObject|LoggerInterface $logger;

    protected static function createKernel(array $options = []): IntegrationTestKernel
    {
        return new IntegrationTestKernel('test', true, [
            DoctrineAsyncInsertBundle::class => ['all' => true],
        ]);
    }

    public function test_invoke_withSuccessfulInsert_persistsData(): void
    {
        $message = new InsertTableMessage();
        $message->setTableName('test_table');
        $message->setParams(['name' => 'test-value']);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('异步插入数据库成功', [
                'tableName' => 'test_table',
                'params' => ['name' => 'test-value'],
            ]);

        ($this->handler)($message);

        $result = $this->connection->fetchAssociative('SELECT * FROM test_table WHERE name = ?', ['test-value']);
        $this->assertEquals('test-value', $result['name']);
    }

    public function test_invoke_withDuplicateEntryAndAllowed_logsNothing(): void
    {
        // Arrange: Insert initial data
        $this->connection->insert('test_table', ['name' => 'duplicate-value']);

        $message = new InsertTableMessage();
        $message->setTableName('test_table');
        $message->setParams(['name' => 'duplicate-value']);
        $message->setAllowDuplicate(true);

        // Act & Assert
        $this->logger->expects($this->never())->method('error');

        // We expect a UniqueConstraintViolationException to be caught internally
        ($this->handler)($message);

        // Ensure no new record was inserted
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test_table');
        $this->assertEquals(1, $count);
    }

    public function test_invoke_withDuplicateEntryAndNotAllowed_logsError(): void
    {
        // Arrange: Insert initial data
        $this->connection->insert('test_table', ['name' => 'duplicate-value']);

        $message = new InsertTableMessage();
        $message->setTableName('test_table');
        $message->setParams(['name' => 'duplicate-value']);
        $message->setAllowDuplicate(false);

        // Act & Assert
        $this->logger->expects($this->once())
            ->method('error')
            ->with('异步插入数据库时发现重复数据', $this->callback(function ($subject) {
                $this->assertEquals('test_table', $subject['tableName']);
                $this->assertEquals(['name' => 'duplicate-value'], $subject['params']);
                $this->assertInstanceOf(UniqueConstraintViolationException::class, $subject['exception']);
                return true;
            }));

        ($this->handler)($message);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->connection = $container->get(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new InsertTableHandler(
            $this->logger,
            $this->connection,
            $container->get(AsyncInsertService::class)
        );

        $this->connection->executeStatement('DROP TABLE IF EXISTS test_table');
        $this->connection->executeStatement('CREATE TABLE test_table (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE (name))');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS test_table');
        parent::tearDown();
    }
}
