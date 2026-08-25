# OnTime — Automated Tests

## Overview

OnTime includes PHPUnit tests covering the payment system, booking flow, and
gateway integrations. Tests run against a WordPress test instance using the
official `wordpress-develop` test suite.

## Prerequisites

- PHP 7.4+
- Composer
- MySQL / MariaDB
- SVN (for WordPress test suite)

## Setup

```bash
# Install WordPress test suite
bash bin/install-wp-tests.sh

# Install PHPUnit (if not already)
composer install
```

## Running Tests

```bash
# Run all tests
phpunit

# Run only payment tests
phpunit --testsuite "OnTime Plugin Tests" --filter Payment
```

## Test Coverage

| File | Coverage |
|------|----------|
| `test-payment-handler.php` | Payment_Handler singleton, gateway registration, process_payment routing |
| `test-payment-mock.php` | Mock gateway request/verify behavior, interface compliance |
| `test-payment-zarinpal.php` | Zarinpal config, amount sanitization, sandbox mode, error handling |
| `test-booking-flow.php` | Shortcode rendering, AJAX endpoints, nonce verification, payment routing |
