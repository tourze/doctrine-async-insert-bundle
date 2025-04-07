# Doctrine Async Bundle

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-async-bundle)

A Symfony bundle that provides asynchronous database operations for Doctrine DBAL, built on top of Symfony Messenger component.

## Features

- Asynchronous database insert operations
- Duplicate entry detection and handling
- Configurable duplicate entry error handling
- Comprehensive logging for insert operations
- JSON data type support with automatic encoding

## Installation

```bash
composer require tourze/doctrine-async-bundle
```

## Quick Start

```php
<?php

use Tourze\DoctrineAsyncBundle\Message\InsertTableMessage;

// Create an insert message
$message = new InsertTableMessage();
$message->setTableName('your_table');
$message->setParams([
    'column1' => 'value1',
    'column2' => ['array', 'will', 'be', 'json_encoded'],
]);
$message->setAllowDuplicate(false); // Set to true to ignore duplicate entry errors

// Dispatch the message using Symfony Messenger
$messageBus->dispatch($message);
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
