# Doctrine 异步插入包

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![PHP Version Require](https://img.shields.io/packagist/php-v/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![License](https://img.shields.io/packagist/l/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat-square)](https://github.com/tourze/doctrine-async-insert-bundle)

一个基于 Symfony Messenger 组件实现的 Doctrine 实体异步数据库插入包，
用于高性能数据插入操作。

## 功能特性

- 🚀 **异步数据库插入操作** - 异步执行数据库插入以提高响应时间
- 🔍 **重复数据检测和处理** - 可配置的重复数据错误处理和日志记录
- ⏰ **延迟执行支持** - 使用 Symfony Messenger DelayStamp 
  支持可配置的延迟插入
- 📝 **完整的日志记录** - 所有插入操作的完整日志记录和详细错误跟踪
- 🔧 **回退机制** - 异步失败时自动回退到同步插入
- 🎯 **实体感知** - 直接支持实体并自动生成 SQL
- 🔄 **基于环境的控制** - 通过环境变量强制同步模式

## 安装

```bash
composer require tourze/doctrine-async-insert-bundle
```

## 快速开始

### Bundle 注册

添加到您的 `config/bundles.php`：

```php
return [
    // ... 其他 bundles
    Tourze\DoctrineAsyncInsertBundle\DoctrineAsyncInsertBundle::class => ['all' => true],
];
```

### 基本使用

```php
<?php

use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;

/** @var AsyncInsertService $asyncInsertService */
$asyncInsertService = $container->get(AsyncInsertService::class);

// 异步插入实体
$entity = new YourEntity();
$entity->setName('example');

$asyncInsertService->asyncInsert($entity);
```

## 文档

### 配置

#### 环境变量

- `FORCE_REPOSITORY_SYNC_INSERT=true` - 强制同步插入模式（绕过异步）

#### Messenger 配置

确保您的 `config/packages/messenger.yaml` 包含路由配置：

```yaml
framework:
    messenger:
        routing:
            Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage: async
```

### 高级使用

#### 延迟和重复处理

```php
<?php

// 5秒延迟插入
$asyncInsertService->asyncInsert($entity, 5000);

// 允许重复数据
$asyncInsertService->asyncInsert($entity, 0, true);
```

#### 手动消息发送

```php
<?php

use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;

// 手动创建插入消息
$message = new InsertTableMessage();
$message->setTableName('your_table');
$message->setParams([
    'column1' => 'value1',
    'column2' => ['array', 'will', 'be', 'json_encoded'],
]);
$message->setAllowDuplicate(false);

// 使用 Symfony Messenger 发送消息
$messageBus->dispatch($message);
```

### 架构

#### 组件

- **AsyncInsertService** - 异步实体插入的主要服务
- **InsertTableMessage** - 异步处理的消息对象
- **InsertTableHandler** - 处理插入操作的消息处理器
- **DoctrineAsyncInsertBundle** - 服务注册的 Bundle 类

#### 流程

1. 实体传递给 `AsyncInsertService`
2. 从实体中提取 SQL 和参数
3. 创建并发送 `InsertTableMessage`
4. `InsertTableHandler` 异步处理消息
5. 失败时回退到同步插入

### 错误处理

该包包含全面的错误处理机制：

- **重复数据错误** - 可配置的处理和日志记录
- **消息发送失败** - 自动回退到直接插入
- **插入失败** - 详细的错误日志记录和上下文信息

### 依赖项

该包依赖于：

- `symfony/messenger` - 异步消息处理
- `doctrine/orm` - 实体管理
- `doctrine/dbal` - 数据库操作
- `tourze/doctrine-direct-insert-bundle` - 回退直接插入
- `tourze/doctrine-entity-checker-bundle` - SQL 格式化

### 测试

运行测试套件：

```bash
./vendor/bin/phpunit packages/doctrine-async-insert-bundle/tests
```

**覆盖率**：所有测试均通过，100% 断言覆盖率。

## 贡献

请查看 [CONTRIBUTING.md](CONTRIBUTING.md) 了解详情。

## 开源协议

本项目采用 MIT 协议。详情请查看 [License 文件](LICENSE)。
