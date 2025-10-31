<?php

declare(strict_types=1);

namespace Tourze\DoctrineAsyncInsertBundle\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;

/**
 * InsertTableMessage 测试类
 *
 * @internal
 */
#[CoversClass(InsertTableMessage::class)]
final class InsertTableMessageTest extends TestCase
{
    public function testGetSetParams(): void
    {
        $message = new InsertTableMessage();
        $params = ['column1' => 'value1', 'column2' => 'value2'];

        $message->setParams($params);
        $this->assertSame($params, $message->getParams());
    }

    public function testGetSetTableName(): void
    {
        $message = new InsertTableMessage();
        $tableName = 'test_table';

        $message->setTableName($tableName);
        $this->assertSame($tableName, $message->getTableName());
    }

    public function testGetSetAllowDuplicate(): void
    {
        $message = new InsertTableMessage();

        // 默认值应为 false
        $this->assertFalse($message->isAllowDuplicate());

        $message->setAllowDuplicate(true);
        $this->assertTrue($message->isAllowDuplicate());

        $message->setAllowDuplicate(false);
        $this->assertFalse($message->isAllowDuplicate());
    }

    public function testMessageInstantiation(): void
    {
        $message = new InsertTableMessage();
        $this->assertNotNull($message);
        $this->assertSame('Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage', $message::class);
    }
}
