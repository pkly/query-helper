# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**Install dependencies:**
```bash
composer update --no-interaction --no-scripts --ansi --no-progress --prefer-dist
```

**Static analysis (PHPStan level 9):**
```bash
vendor/bin/phpstan --ansi --no-progress
```

**Check code style (dry run):**
```bash
vendor/bin/php-cs-fixer fix --config ./.php-cs-fixer.dist.php --dry-run --diff --ansi --show-progress none
```

**Auto-fix code style:**
```bash
vendor/bin/php-cs-fixer fix --config ./.php-cs-fixer.dist.php --ansi --show-progress none
```

There are no unit tests — the test job in CI is commented out and PHPUnit is not installed.

## Architecture

The library is a single class: `src/QueryHelper.php`. It wraps Doctrine ORM's `QueryBuilder` to provide a chainable, type-safe API.

**Class signature:** `QueryHelper<TValue, TId of int|string>`

**Core assumption:** The wrapped `QueryBuilder` must have its main FROM clause aliased as `entity`. The private `validateQueryBuilder()` method asserts this on construction and `getMainFrom()` relies on it to extract the entity class name for reference lookups.

**Method categories:**
- Result fetchers: `value()`, `id()`, `list()`, `ids()` — return entities/IDs
- Reference fetchers: `reference()`, `references()` — return Doctrine lazy-loading references via `EntityManager::getReference()`
- Field fetchers: `fields()`, `fieldList()` — return raw scalar arrays for specific field selections
- Aggregates: `count()`, `exists()`
- Chainable modifiers: `limit()`, `offset()`, `distinct()`, `lockMode()`, `orderBy()`, `addOrderBy()` — all return `static`

**Key implementation detail:** Single-result methods (`value()`, `id()`, `reference()`) call the private `withSingleResult()` helper, which clones the internal QueryBuilder and sets `maxResults(1)` before executing, so they never mutate the stored builder.

**PHPStan suppression:** A few `@phpstan-ignore` comments exist in `QueryHelper.php` where the type system cannot fully verify generic constraints — these are intentional and should not be removed.

**Code style:** Symfony standard with PedroTroller extensions, enforced by PHP-CS-Fixer. Config in `.php-cs-fixer.dist.php`.