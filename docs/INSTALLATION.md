# Installation

## Prerequisites

- PHP 8.2+
- Laravel 12+

## Install In Another Laravel App

Add this repository as a Composer repository in the host app:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../notifications",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Then require the package:

```bash
composer require acl/communications:@dev
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=communications-config
```

## Verify The Package Loads

```bash
composer dump-autoload
php artisan package:discover
php artisan test
```

You can also resolve the core service directly:

```php
app(\Acl\Communications\Contracts\CommunicationServiceInterface::class);
```
