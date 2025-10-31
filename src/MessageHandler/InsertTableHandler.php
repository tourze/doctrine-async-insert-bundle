<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\MessageHandler;

use Doctrine\DBAL\Connection;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;
use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;
use Yiisoft\Json\Json;

/**
 * 执行SQL插入
 */
#[AsMessageHandler]
#[WithMonologChannel(channel: 'doctrine-async-insert')]
#[Autoconfigure(public: true)]
readonly class InsertTableHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private Connection $connection,
        private AsyncInsertService $doctrineService,
    ) {
    }

    public function __invoke(InsertTableMessage $message): void
    {
        $tableName = $message->getTableName();
        $params = $this->prepareParams($message->getParams());

        try {
            $this->connection->insert($tableName, $params);
            $this->logSuccess($tableName, $params);
        } catch (\Throwable $exception) {
            $this->handleInsertException($exception, $message, $tableName, $params);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function prepareParams(array $params): array
    {
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $params[$k] = Json::encode($v);
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleInsertException(\Throwable $exception, InsertTableMessage $message, string $tableName, array $params): void
    {
        if ($this->doctrineService->isDuplicateEntryException($exception)) {
            $this->handleDuplicateEntry($exception, $message, $tableName, $params);
        } else {
            $this->logGeneralError($exception, $tableName, $params);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleDuplicateEntry(\Throwable $exception, InsertTableMessage $message, string $tableName, array $params): void
    {
        if (!$message->isAllowDuplicate() && !$this->isTestEnvironment()) {
            $this->logger->error('异步插入数据库时发现重复数据', [
                'tableName' => $tableName,
                'params' => $params,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logGeneralError(\Throwable $exception, string $tableName, array $params): void
    {
        if (!$this->isTestEnvironment()) {
            $this->logger->error('异步插入数据库失败', [
                'tableName' => $tableName,
                'params' => $params,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logSuccess(string $tableName, array $params): void
    {
        // 在测试环境中禁用日志输出
        if (!$this->isTestEnvironment()) {
            $this->logger->info('异步插入数据库成功', [
                'tableName' => $tableName,
                'params' => $params,
            ]);
        }
    }

    private function isTestEnvironment(): bool
    {
        return 'true' === getenv('DISABLE_LOGGING_IN_TESTS')
            || ($_ENV['APP_ENV'] ?? '') === 'test'
            || defined('PHPUNIT_COMPOSER_INSTALL')
            || isset($_ENV['SYMFONY_PHPUNIT_VERSION']);
    }
}
