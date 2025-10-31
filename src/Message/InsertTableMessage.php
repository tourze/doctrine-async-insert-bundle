<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Message;

use Tourze\AsyncContracts\AsyncMessageInterface;

/**
 * 执行SQL插入任务
 */
class InsertTableMessage implements AsyncMessageInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array<string, mixed> $params
     */
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
