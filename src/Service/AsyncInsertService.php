<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineDirectInsertBundle\Service\DirectInsertService;
use Tourze\DoctrineEntityCheckerBundle\Service\SqlFormatter;

#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'doctrine_async_insert')]
readonly class AsyncInsertService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SqlFormatter $sqlFormatter,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private DirectInsertService $directInsertService,
    ) {
    }

    /**
     * 判断这个错误，是否因为数据重复导致
     */
    public function isDuplicateEntryException(\Throwable $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        return str_contains($exception->getMessage(), 'Duplicate entry') || str_contains($exception->getMessage(), 'Integrity constraint violation');
    }

    /**
     * 延迟保存记录的时间，不关心结果返回值
     * 这个SQL会在响应内容返回给消费者后开始执行
     * 这个类绝对不能抛出错误，暂时先在内部处理
     */
    public function asyncInsert(object $object, int $delayMs = 0, bool $allowDuplicate = false): void
    {
        if ('true' === getenv('FORCE_REPOSITORY_SYNC_INSERT')) {
            $this->directInsertService->directInsert($object);

            return;
        }

        [$tableName, $params] = $this->sqlFormatter->getObjectInsertSql($this->entityManager, $object);
        try {
            $message = new InsertTableMessage();
            $message->setTableName($tableName);
            $message->setParams($params);
            $message->setAllowDuplicate($allowDuplicate);

            $stamps = [];
            if ($delayMs > 0) {
                $stamps[] = new DelayStamp($delayMs);
            }
            $this->messageBus->dispatch($message, $stamps);
        } catch (\Throwable $exception0) {
            $this->logger->error("asyncInsert时发生错误[{$exception0->getMessage()}]，尝试直接插入数据库", [
                'exception' => $exception0,
                'object' => $object,
            ]);
            try {
                $this->directInsertService->directInsert($object);
            } catch (\Throwable $exception1) {
                $this->logger->error('asyncInsert时发生错误，尝试请求结束后再继续', [
                    'exception' => $exception1,
                    'object' => $object,
                ]);
            }
        }
    }
}
