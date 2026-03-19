# MyAdmin VPS Module

[![Tests](https://github.com/detain/myadmin-vps-module/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-vps-module/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-vps-module/version)](https://packagist.org/packages/detain/myadmin-vps-module)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-vps-module/downloads)](https://packagist.org/packages/detain/myadmin-vps-module)
[![License](https://poser.pugx.org/detain/myadmin-vps-module/license)](https://packagist.org/packages/detain/myadmin-vps-module)

VPS hosting module for the [MyAdmin](https://github.com/detain/myadmin-client-vue) multi-service billing and management platform. Provides VPS provisioning, full lifecycle management (start, stop, restart, backup, terminate), slice-based resource scaling, and a SOAP/REST API for automated ordering and administration.

## Features

- Event-driven plugin architecture via Symfony EventDispatcher
- Slice-based VPS resource scaling with configurable per-slice costs
- Full VPS lifecycle: provisioning, activation, suspension, reactivation, termination
- IP address management and reverse-DNS cleanup on termination
- Backup creation, listing, and deletion through the API
- Coupon and multi-period billing support
- Admin-only order placement with explicit server targeting

## Requirements

- PHP 8.2 or later
- ext-soap
- Symfony EventDispatcher 5.x, 6.x, or 7.x

## Installation

```sh
composer require detain/myadmin-vps-module
```

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

## License

Licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html).
