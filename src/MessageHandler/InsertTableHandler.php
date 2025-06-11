<?php

namespace Tourze\DoctrineAsyncInsertBundle\MessageHandler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\Service\DoctrineService;
use Yiisoft\Json\Json;

/**
 * 执行SQL插入
 */
#[AsMessageHandler]
class InsertTableHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly DoctrineService $doctrineService,
    ) {
    }

    public function __invoke(InsertTableMessage $message): void
    {
        $tableName = $message->getTableName();

        $params = $message->getParams();
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $params[$k] = Json::encode($v);
            }
        }

        try {
            $this->connection->insert($tableName, $params);
        } catch (\Throwable $exception) {
            if ($this->doctrineService->isDuplicateEntryException($exception)) {
                if (!$message->isAllowDuplicate()) {
                    $this->logger->error('异步插入数据库时发现重复数据', [
                        'tableName' => $tableName,
                        'params' => $params,
                        'exception' => $exception,
                    ]);
                }
            } else {
                $this->logger->error('异步插入数据库失败', [
                    'tableName' => $tableName,
                    'params' => $params,
                    'exception' => $exception,
                ]);
            }

            return;
        }
        $this->logger->info('异步插入数据库成功', [
            'tableName' => $tableName,
            'params' => $params,
        ]);
    }
}
