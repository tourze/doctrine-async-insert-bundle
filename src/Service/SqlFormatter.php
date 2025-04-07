<?php

namespace Tourze\DoctrineAsyncBundle\Service;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Query as OrmQuery;
use Doctrine\ORM\Query\Parameter as OrmParameter;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DoctrineEntityCheckerBundle\Service\EntityChecker;
use Tourze\DoctrineEntityCheckerBundle\Service\EntityPrimaryKeyService;
use Yiisoft\Json\Json;
use Yiisoft\Strings\Inflector;

/**
 * 助手方法，用于生成query的真实sql.
 */
#[Autoconfigure(lazy: true)]
class SqlFormatter
{
    public function __construct(
        private readonly EntityChecker $entityChecker,
        private readonly Inflector $inflector,
        private readonly EntityPrimaryKeyService $primaryKeyService,
    ) {
    }

    public function getObjectInsertSql(ObjectManager $objectManager, object $object): array
    {
        $this->entityChecker->prePersistEntity($objectManager, $object);

        // 生成sql咯
        $params = [];
        $reflection = $objectManager->getClassMetadata($object::class)->getReflectionClass();
        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(ORM\OneToMany::class) || $property->getAttributes(ORM\ManyToMany::class)) {
                continue;
            }
            $column = $property->getAttributes(ORM\Column::class);
            if (!empty($column)) {
                $column = $column[0]->newInstance();
            } else {
                $column = null;
            }
            /** @var ORM\Column|null $column */
            $name = $column?->name;
            if (!$name) {
                $name = $this->inflector->toSnakeCase($property->getName());
                if ($property->getAttributes(ORM\ManyToOne::class)) {
                    $name = "{$name}_id";
                }
                if ($property->getAttributes(ORM\OneToOne::class)) {
                    if (empty($property->getAttributes(ORM\OneToOne::class)[0]->newInstance()->inversedBy)) {
                        continue;
                    }
                    $name = "{$name}_id";
                }
            }

            $val = $property->getValue($object);

            // 如果是主键并且没值的话，就跳过
            if (!empty($property->getAttributes(ORM\Id::class)) && $val <= 0) {
                continue;
            }

            // 如果是一个Entity的话，那我们就只关注其中的ID
            if (is_object($val)) {
                try {
                    $pkValues = $this->primaryKeyService->getPrimaryKeyValues($val);
                    if (!empty($pkValues)) {
                        // 一般来讲，doctrine entity 是必须有主键的
                        $val = array_shift($pkValues);
                    }
                } catch (MappingException $exception) {
                    // 字段不存在，类不存在的话，这里就会报错，我们不需要处理
                }
            }

            if (is_bool($val)) {
                $val = $val ? 1 : 0;
            }

            if ($val instanceof \BackedEnum) {
                $val = strval($val->value);
            }

            // JSON格式？
            if (is_array($val)) {
                $val = Json::encode($val);
            }

            $params[$name] = $val;
        }
        $tableName = $reflection->getAttributes(ORM\Table::class)[0]->newInstance();
        /** @var ORM\Table $tableName */
        $tableName = $tableName->name;

        return [
            $tableName,
            $params,
        ];
    }

    /**
     * 格式化参数值
     *
     * @param mixed $value
     */
    private function formatOrmValue(OrmParameter $value): string
    {
        $value = $value->getValue();

        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return "'{$value->format('Y-m-d H:i:s')}'";
        }

        if (is_array($value)) {
            if (empty($value)) {
                return "''";
            }

            $tmp = [];
            foreach ($value as $item) {
                $tmp[] = "'{$item}'";
            }

            return implode(', ', $tmp);
        }

        if (is_object($value)) {
            if (method_exists($value, 'getId')) {
                return $value->getId();
            }
        }

        return "'{$value}'";
    }

    /**
     * 格式化并返回完整可执行的DQL.
     */
    public function fromOrmQuery(OrmQuery $query): string
    {
        $dql = $query->getDQL();
        foreach ($query->getParameters() as $k => $parameter) {
            $search = ":{$parameter->getName()}";
            $replace = $this->formatOrmValue($parameter);
            $dql = str_replace($search, $replace, $dql);
        }

        return $dql;
    }
}
