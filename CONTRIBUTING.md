# Contributing

Contributions are welcome and will be fully credited.

## Pull Requests

- **[PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)** - The easiest way to apply the conventions is to run `composer lint`.
- **Add tests** - Your patch won't be accepted if it doesn't have tests.
- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.
- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

## Development Setup

```bash
# Clone the repository
git clone git@github.com:XVE-BV/laravel-db-export.git
cd laravel-db-export

# Install dependencies
composer install

# Run tests
composer test

# Run code style fixer
composer lint

# Run static analysis
composer test:types
```

## Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Unit/Config/ProfileManagerTest.php

# Run with coverage
composer test:coverage
```
