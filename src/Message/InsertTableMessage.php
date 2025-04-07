<?php

namespace Tourze\DoctrineAsyncBundle\Message;

use Tourze\Symfony\Async\Message\AsyncMessageInterface;

/**
 * 执行SQL插入任务
 */
class InsertTableMessage implements AsyncMessageInterface
{
    private array $params = [];

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    private string $tableName;

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * @var bool 是否允许发生重复报错
     */
    private bool $allowDuplicate = false;

    public function isAllowDuplicate(): bool
    {
        return $this->allowDuplicate;
    }

    public function setAllowDuplicate(bool $allowDuplicate): void
    {
        $this->allowDuplicate = $allowDuplicate;
    }
}
