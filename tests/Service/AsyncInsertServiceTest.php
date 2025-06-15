<?php

namespace Tourze\DoctrineAsyncInsertBundle\Tests\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tourze\DoctrineAsyncInsertBundle\DoctrineAsyncInsertBundle;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;
use Tourze\DoctrineAsyncInsertBundle\Tests\Fixtures\Entity\TestEntity;
use Tourze\DoctrineDirectInsertBundle\Service\DirectInsertService;
use Tourze\DoctrineEntityCheckerBundle\Service\SqlFormatter;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class AsyncInsertServiceTest extends KernelTestCase
{
    private AsyncInsertService $service;
    private MockObject|MessageBusInterface $messageBus;
    private MockObject|LoggerInterface $logger;
    private MockObject|DirectInsertService $directInsertService;
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected static function createKernel(array $options = []): IntegrationTestKernel
    {
        return new IntegrationTestKernel('test', true,
            [
                DoctrineAsyncInsertBundle::class => ['all' => true],
            ],
            [
                'Tourze\DoctrineAsyncInsertBundle\Tests\Fixtures\Entity' => __DIR__ . '/../Fixtures/Entity',
            ]
        );
    }

    public function test_asyncInsert_withNormalFlow_dispatchesMessage(): void
    {
        $entity = new TestEntity();
        $entity->setName('test');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (InsertTableMessage $message) {
                    $this->assertEquals('test_entity', $message->getTableName());
                    $this->assertEquals(['name' => 'test'], $message->getParams());
                    $this->assertFalse($message->isAllowDuplicate());
                    return true;
                }),
                $this->equalTo([])
            )->willReturn(new Envelope(new \stdClass()));

        $this->service->asyncInsert($entity);
    }

    public function test_asyncInsert_withDelay_dispatchesMessageWithDelayStamp(): void
    {
        $entity = new TestEntity();
        $entity->setName('test');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(InsertTableMessage::class),
                $this->callback(function (array $stamps) {
                    $this->assertCount(1, $stamps);
                    $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                    return true;
                })
            )->willReturn(new Envelope(new \stdClass()));

        $this->service->asyncInsert($entity, 1000);
    }

    public function test_asyncInsert_withDispatchError_fallsBackToDirectInsert(): void
    {
        $entity = new TestEntity();
        $entity->setName('test-fallback');
        $exception = new Exception('Message bus failed');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('asyncInsert时发生错误[Message bus failed]，尝试直接插入数据库', [
                'exception' => $exception,
                'object' => $entity,
            ]);

        $this->directInsertService->expects($this->once())
            ->method('directInsert')
            ->with($entity);

        $this->service->asyncInsert($entity);
    }

    public function test_asyncInsert_withForceSync_usesDirectInsert(): void
    {
        $_ENV['FORCE_REPOSITORY_SYNC_INSERT'] = true;

        $entity = new TestEntity();
        $entity->setName('test-sync');

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->directInsertService->expects($this->once())->method('directInsert')->with($entity);

        $this->service->asyncInsert($entity);

        unset($_ENV['FORCE_REPOSITORY_SYNC_INSERT']);
    }

    public function test_isDuplicateEntryException_worksAsExpected(): void
    {
        $previous = $this->createMock(\Doctrine\DBAL\Driver\Exception::class);
        $this->assertTrue($this->service->isDuplicateEntryException(new \Doctrine\DBAL\Exception\UniqueConstraintViolationException($previous, null)));
        $this->assertTrue($this->service->isDuplicateEntryException(new \Exception('Duplicate entry...')));
        $this->assertTrue($this->service->isDuplicateEntryException(new \Exception('...Integrity constraint violation...')));
        $this->assertFalse($this->service->isDuplicateEntryException(new \Exception('Some other error')));
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // Mocks
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->directInsertService = $this->createMock(DirectInsertService::class);

        // Real services
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        // Service under test
        $this->service = new AsyncInsertService(
            $this->entityManager,
            $container->get(SqlFormatter::class),
            $this->messageBus,
            $this->logger,
            $this->directInsertService
        );

        // Schema
        $this->connection->executeStatement('DROP TABLE IF EXISTS test_entity');
        $this->connection->executeStatement('CREATE TABLE test_entity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS test_entity');
        parent::tearDown();
    }
}
