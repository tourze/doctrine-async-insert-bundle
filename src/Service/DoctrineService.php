<?php

namespace Tourze\DoctrineAsyncBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tourze\DoctrineAsyncBundle\EventSubscriber\DoctrineCleanSubscriber;
use Tourze\DoctrineAsyncBundle\Message\InsertTableMessage;

#[Autoconfigure(lazy: true)]
class DoctrineService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SqlFormatter $sqlFormatter,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly DoctrineCleanSubscriber $doctrineCleanSubscriber,
    )
    {
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

    private function getConnection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * 直接插入数据库
     */
    public function directInsert(object $object): int|string
    {
        [$tableName, $params] = $this->sqlFormatter->getObjectInsertSql($this->entityManager, $object);

        $this->getConnection()->insert($tableName, $params);
        if (!empty($params['id'])) {
            $id = $params['id'];
        } else {
            $id =  $this->getConnection()->lastInsertId();
        }
        return $id;
    }

    /**
     * 延迟保存记录的时间，不关心结果返回值
     * 这个SQL会在响应内容返回给消费者后开始执行
     * 这个类绝对不能抛出错误，暂时先在内部处理
     */
    public function asyncInsert(object $object, int $delayMs = 0, bool $allowDuplicate = false): void
    {
        if ($_ENV['FORCE_REPOSITORY_SYNC_INSERT'] ?? false) {
            $this->directInsert($object);

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
        } catch (\Throwable $exception) {
            $this->logger->error('asyncInsert时发生错误，尝试直接插入数据库', [
                'exception' => $exception,
                'object' => $object,
            ]);
            try {
                $this->directInsert($object);
            } catch (\Throwable $exception) {
                $this->logger->error('asyncInsert时发生错误，尝试请求结束后再继续', [
                    'exception' => $exception,
                    'object' => $object,
                ]);
                $this->doctrineCleanSubscriber->addTableRecord($tableName, $params);
            }
        }
    }
}
