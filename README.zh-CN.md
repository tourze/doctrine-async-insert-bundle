# Doctrine 异步操作包

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-bundle)

一个基于 Symfony Messenger 组件实现的 Doctrine DBAL 异步数据库操作包。

## 功能特性

- 异步数据库插入操作
- 重复数据检测和处理
- 可配置的重复数据错误处理
- 完整的操作日志记录
- JSON 数据类型支持，自动编码

## 安装

```bash
composer require tourze/doctrine-async-bundle
```

## 快速开始

```php
<?php

use Tourze\DoctrineAsyncBundle\Message\InsertTableMessage;

// 创建插入消息
$message = new InsertTableMessage();
$message->setTableName('your_table');
$message->setParams([
    'column1' => 'value1',
    'column2' => ['array', 'will', 'be', 'json_encoded'],
]);
$message->setAllowDuplicate(false); // 设置为 true 可以忽略重复数据错误

// 使用 Symfony Messenger 发送消息
$messageBus->dispatch($message);
```

## 贡献

请查看 [CONTRIBUTING.md](CONTRIBUTING.md) 了解详情。

## 开源协议

本项目采用 MIT 协议。详情请查看 [License 文件](LICENSE)。
