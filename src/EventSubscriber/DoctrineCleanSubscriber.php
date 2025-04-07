<?php

namespace Tourze\DoctrineAsyncBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\Logging\CacheLoggerChain;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\DoctrineAsyncBundle\Service\DoctrineService;

/**
 * 在请求结束后，我们执行一些额外逻辑，例如执行失败的sql进行兜底处理
 */
#[AutoconfigureTag('as-coroutine')]
class DoctrineCleanSubscriber implements EventSubscriberInterface, ResetInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [
                // 执行SQL按照正常来做
                ['executeAllCollectSql', 0],
                // 要在 \Symfony\Component\HttpKernel\EventListener\ProfilerListener::getSubscribedEvents 之后执行
                ['clearLogs', -1224],
            ],
        ];
    }

    /**
     * @var array 在正常执行时，如果有SQL执行失败，则回尝试在这里最最后一次尝试
     */
    private array $newTableRecords = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly DoctrineService $doctrineService,
    )
    {
    }

    public function executeAllCollectSql(): void
    {
        foreach ($this->newTableRecords as $key => [$tableName, $params, $retryTimes]) {
            try {
                $this->connection->insert($tableName, $params);
                unset($this->newTableRecords[$key]); // 执行成功后删除
            } catch (\Throwable $exception) {
                // 如果是数据重复了，那我们就不继续插入了，没必要了
                if ($this->doctrineService->isDuplicateEntryException($exception)) {
                    unset($this->newTableRecords[$key]);
                    continue;
                }

                if ($retryTimes > intval($_ENV['DOCTRINE_FALLBACK_SQL_RETRY_TIMES'] ?? 5)) {
                    $this->logger->error('兜底执行SQL失败次数过多，放弃', [
                        'exception' => $exception,
                        'tableName' => $tableName,
                        'params' => $params,
                        'retryTimes' => $retryTimes,
                    ]);
                    unset($this->newTableRecords[$key]);
                    continue;
                }
                $this->logger->error('请求结束后再保存数据库记录失败', [
                    'exception' => $exception,
                    'tableName' => $tableName,
                    'params' => $params,
                    'retryTimes' => $retryTimes,
                ]);
                $this->newTableRecords[$key][2]++;
            }
        }
    }

    public function clearLogs(): void
    {
        foreach ($this->registry->getManagers() as $em) {
            $emConfig = $em->getConfiguration();

            /** @var CacheConfiguration|null $cacheConfiguration */
            $cacheConfiguration = $emConfig->getSecondLevelCacheConfiguration();
            if (!$cacheConfiguration) {
                continue;
            }

            /** @var CacheLoggerChain|null $cacheLoggerChain */
            $cacheLoggerChain = $cacheConfiguration?->getCacheLogger();
            if (!$cacheLoggerChain) {
                continue;
            }

            if (!$cacheLoggerChain->getLogger('statistics')) {
                continue;
            }

            $cacheLoggerStats = $cacheLoggerChain->getLogger('statistics');
            $cacheLoggerStats?->clearStats();
        }
    }

    public function addTableRecord(string $tableName, array $params): void
    {
        $this->newTableRecords[] = [$tableName, $params, 0];
    }

    public function reset(): void
    {
        $this->newTableRecords = [];
    }
}
