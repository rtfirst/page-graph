# Contributing to Page Graph

Thank you for considering a contribution to the `page_graph` TYPO3 extension!

## Branching Model

1. **Fork** this repository on GitHub.
2. Create a **feature branch** from `develop` (e.g. `feature/my-change`).
3. Open a **Pull Request** targeting `develop`.

The `main` branch is reserved for releases and has branch protection enabled.

## Development Setup

### With DDEV (recommended)

```bash
git clone git@github.com:<your-fork>/page-graph.git packages/page_graph
ddev start
ddev composer install
ddev typo3 extension:setup
ddev typo3 cache:flush
```

### Standalone (without DDEV)

```bash
git clone git@github.com:<your-fork>/page-graph.git
cd page-graph
composer install
```

## Coding Standards

- **PSR-12** coding style
- `declare(strict_types=1);` in every PHP file
- Type hints on all parameters and return types
- **PHPStan level 8** must pass without errors

## Running Quality Tools

```bash
# Static analysis
vendor/bin/phpstan analyse

# Code style check
vendor/bin/php-cs-fixer fix --dry-run --diff

# Code style fix
vendor/bin/php-cs-fixer fix

# Unit tests
vendor/bin/phpunit
```

If you use DDEV, prefix commands with `ddev exec`.

## Pull Request Checklist

Before submitting your PR, please ensure:

- [ ] Code follows PSR-12 and uses strict types
- [ ] PHPStan level 8 passes (`vendor/bin/phpstan analyse`)
- [ ] PHP-CS-Fixer passes (`vendor/bin/php-cs-fixer fix --dry-run`)
- [ ] Unit tests pass (`vendor/bin/phpunit`)
- [ ] New features include tests where applicable
- [ ] Commit messages are clear and descriptive
- [ ] PR targets the `develop` branch

## Reporting Issues

Please use the [GitHub issue tracker](https://github.com/rtfirst/page-graph/issues) for bug reports and feature requests. Security vulnerabilities should be reported privately -- see [SECURITY.md](SECURITY.md).
