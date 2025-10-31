# Doctrine Async Insert Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![PHP Version Require](https://img.shields.io/packagist/php-v/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![License](https://img.shields.io/packagist/l/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/doctrine-async-insert-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-insert-bundle)
[![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat-square)](https://github.com/tourze/doctrine-async-insert-bundle)

A Symfony bundle that provides asynchronous database insert operations for Doctrine 
entities, built on top of Symfony Messenger component for high-performance data insertion.

## Features

- 🚀 **Asynchronous database insert operations** - Execute database inserts 
  asynchronously to improve response times
- 🔍 **Duplicate entry detection and handling** - Configurable duplicate entry 
  error handling with logging
- ⏰ **Delayed execution support** - Schedule inserts with configurable delays 
  using Symfony Messenger DelayStamp
- 📝 **Comprehensive logging** - Full logging for all insert operations with 
  detailed error tracking
- 🔧 **Fallback mechanism** - Automatic fallback to synchronous insert when 
  async fails
- 🎯 **Entity-aware** - Direct entity support with automatic SQL generation
- 🔄 **Environment-based control** - Force synchronous mode via environment 
  variable

## Installation

```bash
composer require tourze/doctrine-async-insert-bundle
```

## Quick Start

### Bundle Registration

Add to your `config/bundles.php`:

```php
return [
    // ... other bundles
    Tourze\DoctrineAsyncInsertBundle\DoctrineAsyncInsertBundle::class => ['all' => true],
];
```

### Basic Usage

```php
<?php

use Tourze\DoctrineAsyncInsertBundle\Service\AsyncInsertService;

/** @var AsyncInsertService $asyncInsertService */
$asyncInsertService = $container->get(AsyncInsertService::class);

// Insert entity asynchronously
$entity = new YourEntity();
$entity->setName('example');

$asyncInsertService->asyncInsert($entity);
```

## Documentation

### Configuration

#### Environment Variables

- `FORCE_REPOSITORY_SYNC_INSERT=true` - Force synchronous insert mode (bypasses async)

#### Messenger Configuration

Ensure your `config/packages/messenger.yaml` includes the routing:

```yaml
framework:
    messenger:
        routing:
            Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage: async
```

### Advanced Usage

#### Delay and Duplicate Handling

```php
<?php

// Insert with 5-second delay
$asyncInsertService->asyncInsert($entity, 5000);

// Allow duplicate entries
$asyncInsertService->asyncInsert($entity, 0, true);
```

#### Manual Message Dispatch

```php
<?php

use Tourze\DoctrineAsyncInsertBundle\Message\InsertTableMessage;

// Create an insert message manually
$message = new InsertTableMessage();
$message->setTableName('your_table');
$message->setParams([
    'column1' => 'value1',
    'column2' => ['array', 'will', 'be', 'json_encoded'],
]);
$message->setAllowDuplicate(false);

// Dispatch the message using Symfony Messenger
$messageBus->dispatch($message);
```

### Architecture

#### Components

- **AsyncInsertService** - Main service for asynchronous entity insertion
- **InsertTableMessage** - Message object for async processing
- **InsertTableHandler** - Message handler for processing insert operations
- **DoctrineAsyncInsertBundle** - Bundle class for service registration

#### Flow

1. Entity is passed to `AsyncInsertService`
2. SQL and parameters are extracted from entity
3. `InsertTableMessage` is created and dispatched
4. `InsertTableHandler` processes the message asynchronously
5. On failure, fallback to synchronous insert

### Error Handling

The bundle includes comprehensive error handling:

- **Duplicate entry errors** - Configurable handling with logging
- **Message dispatch failures** - Automatic fallback to direct insert
- **Insert failures** - Detailed error logging with context

### Dependencies

This bundle depends on:

- `symfony/messenger` - For asynchronous message handling
- `doctrine/orm` - For entity management
- `doctrine/dbal` - For database operations
- `tourze/doctrine-direct-insert-bundle` - For fallback direct insertion
- `tourze/doctrine-entity-checker-bundle` - For SQL formatting

### Testing

Run the test suite:

```bash
./vendor/bin/phpunit packages/doctrine-async-insert-bundle/tests
```

**Coverage**: All tests pass with 100% assertion coverage.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
