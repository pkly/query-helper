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

**Run tests:**
```bash
vendor/bin/phpunit
```

Tests live in `tests/` and run against an in-memory SQLite database via `AbstractDoctrineTestCase`, which builds a real `EntityManager` over the attribute-mapped fixtures in `tests/Fixtures/Entity` and creates the schema per test. `AbstractDoctrineTestCase::captureLastSql()` runs a callable and returns the SQL of the last statement it executed (recorded by `tests/Fixtures/QueryRecorder.php` through the DBAL logging middleware) — needed because some behavior is only visible in the generated SQL, not in the hydrated results. CI runs PHPStan, PHP-CS-Fixer, then PHPUnit against Symfony 6.4 and 7.4 on PHP 8.4.

## Architecture

The library is `src/QueryHelper.php` plus the small `src/Model.php` value object used by the model fetchers. It wraps Doctrine ORM's `QueryBuilder` to provide a chainable, type-safe API.

**Class signature:** `QueryHelper<TValue, TId of int|string>`

**Core assumption:** The wrapped `QueryBuilder` must have its main FROM clause aliased as `entity`. The private `validateQueryBuilder()` method asserts this on construction and `getMainFrom()` relies on it to extract the entity class name for reference lookups.

**Method categories:**
- Result fetchers: `value()`, `id()`, `list()`, `ids()` — return entities/IDs
- Reference fetchers: `reference()`, `references()` — return Doctrine lazy-loading references via `EntityManager::getReference()`
- Field fetchers: `fields()`, `fieldList()` — return raw scalar arrays for specific field selections
- Model fetchers: `model()`, `models()` — hydrate rows into plain objects via DQL `SELECT NEW Class(...)`, bypassing entity hydration; fields go positionally to the constructor. A field may be a `Model` (`src/Model.php`, built with `Model::of(Class::class, [...])`) for a nested `NEW`. `modelRows()` / `modelRow()` take `alias => Model` and return per-row arrays keyed by alias; Doctrine unwraps a lone aliased `NEW` to the bare object, so `modelRows()` re-keys single-model rows itself.
- Aggregates: `count()`, `exists()`
- Chainable modifiers: `limit()`, `offset()`, `distinct()`, `lockMode()`, `orderBy()`, `addOrderBy()` — all return `static`

**Key implementation detail:** Single-result methods (`value()`, `id()`, `reference()`) call the private `withSingleResult()` helper, which clones the internal QueryBuilder and sets `maxResults(1)` before executing, so they never mutate the stored builder.

**Field qualification:** `fieldList()`, `fields()`, `models()` and `model()` share the private `qualifyField()`: a bare identifier (`title`) becomes `entity.title`; anything else (`entity.title`, `IDENTITY(entity.author)`, `COUNT(entity.id)`) is passed through verbatim.

**Distinct resolution:** `distinct()` sets a local flag, but the private `isDistinct()` ORs it with the wrapped builder's own `distinct` DQL part, so `$qb->distinct()` set by the caller applies to every fetcher and cannot be turned off via `distinct(false)`. Each fetcher applies the result to its clone through `QueryBuilder::distinct()` rather than baking `DISTINCT` into a select string — that keeps custom selects intact — except `count()`, which clears it and uses `COUNT(DISTINCT entity.<id>)` instead.

**PHPStan extension:** `src/PHPStan/ModelRowsReturnTypeExtension.php` (registered in `extension.neon`, included by `phpstan.neon.dist` and advertised via `composer.json` `extra.phpstan.includes`) narrows `modelRows()` / `modelRow()` from `array<string, object>` to a constant array shape by unwrapping `Model<X>` → `X` for each literal alias; non-literal maps fall back to the docblock type. Covered by `tests/PHPStan/ModelRowsReturnTypeExtensionTest.php`, a `TypeInferenceTestCase` over the `assertType()` calls in `tests/PHPStan/data/model-rows.php`.

**PHPStan suppression:** A few `@phpstan-ignore` comments exist in `QueryHelper.php` where the type system cannot fully verify generic constraints — these are intentional and should not be removed.

**Code style:** Symfony standard with PedroTroller extensions, enforced by PHP-CS-Fixer. Config in `.php-cs-fixer.dist.php`.