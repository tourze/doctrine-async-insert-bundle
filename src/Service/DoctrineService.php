<?php

namespace Tourze\DoctrineAsyncBundle\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class DoctrineService
{
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
}
