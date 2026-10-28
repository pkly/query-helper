<?php

namespace Pkly\QueryHelper;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\QueryBuilder;

/**
 * @template TValue
 * @template TId of int|string
 */
class QueryHelper
{
    private LockMode|null $lockMode = null;
    private bool $distinct = false;

    public function __construct(
        private readonly QueryBuilder $queryBuilder
    ) {
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function limit(
        int|null $limit
    ): static {
        $this->queryBuilder->setMaxResults($limit);

        return $this;
    }

    public function offset(
        int|null $offset
    ): static {
        $this->queryBuilder->setFirstResult($offset);

        return $this;
    }

    public function lockMode(
        LockMode|null $lockMode = null
    ): static {
        $this->lockMode = $lockMode;

        return $this;
    }

    /**
     * Note that passing false here does not necessarily disable distinct results — if the wrapped
     * QueryBuilder was itself set up with QueryBuilder::distinct(), that setting wins and every
     * fetcher stays distinct regardless of this flag.
     */
    public function distinct(
        bool $distinct = true
    ): static {
        $this->distinct = $distinct;

        return $this;
    }

    public function orderBy(
        string $field,
        Order $order = Order::Ascending
    ): static {
        $this->queryBuilder->orderBy($field, $order->value);

        return $this;
    }

    public function addOrderBy(
        string $field,
        Order $order = Order::Ascending
    ): static {
        $this->queryBuilder->addOrderBy($field, $order->value);

        return $this;
    }

    /**
     * @return TValue|null
     */
    public function value(): mixed
    {
        return $this->withSingleResult(fn () => $this->list()[0] ?? null);
    }

    /**
     * @return TId|null
     *
     * @phpstan-ignore-next-line
     */
    public function id(): int|string|null
    {
        return $this->withSingleResult(fn () => $this->ids()[0] ?? null);
    }

    public function exists(): bool
    {
        return null !== $this->id();
    }

    /**
     * @return Collection<int, TValue>
     */
    public function list(): Collection
    {
        assert($this->validateQueryBuilder());

        $qb = (clone $this->queryBuilder);
        $qb->distinct($this->isDistinct());

        $query = $qb->getQuery();

        if (null !== $this->lockMode) {
            $query->setLockMode($this->lockMode);
        }

        return new ArrayCollection($query->getResult()); // @phpstan-ignore-line
    }

    /**
     * @return Collection<int, TValue>
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    public function references(): Collection
    {
        $main = $this->getMainFrom();

        assert(null !== $main);

        $class = $main->getFrom();
        $em = $this->queryBuilder->getEntityManager();
        $results = [];

        foreach ($this->ids() as $id) {
            $reference = $em->getReference($class, $id);
            /** @var TValue $reference */
            $results[] = $reference; // @phpstan-ignore-line
        }

        return new ArrayCollection($results);
    }

    /**
     * @return TValue|null
     */
    public function reference(): mixed
    {
        return $this->withSingleResult(fn () => $this->references()[0] ?? null);
    }

    /**
     * @template TArrayKeys of array-key
     *
     * @param list<TArrayKeys> $fields
     *
     * @return Collection<int, array<TArrayKeys, mixed>>
     */
    public function fieldList(
        array $fields
    ): Collection {
        assert(!empty($fields));
        assert($this->validateQueryBuilder());

        $qb = (clone $this->queryBuilder);
        $selects = [];

        foreach ($fields as $field) {
            $selects[] = $this->qualifyField($field);
        }

        $qb->distinct($this->isDistinct());
        $qb->select(...$selects);

        $query = $qb->getQuery();

        if (null !== $this->lockMode) {
            $query->setLockMode($this->lockMode);
        }

        return new ArrayCollection($query->getArrayResult()); // @phpstan-ignore-line
    }

    /**
     * @template TArrayKeys of array-key
     *
     * @param list<TArrayKeys> $fields
     *
     * @return array<TArrayKeys, mixed>|null
     */
    public function fields(
        array $fields
    ): array|null {
        return $this->withSingleResult(fn () => $this->fieldList($fields)[0] ?? null);
    }

    /**
     * Hydrates each row directly into a plain object via DQL `SELECT NEW Class(...)`, bypassing
     * entity hydration and the UnitOfWork entirely. $fields are passed positionally to the
     * constructor of $class; bare names are prefixed with `entity.`, anything containing a dot or
     * parentheses (`entity.title`, `IDENTITY(entity.author)`, `COUNT(entity.id)`) is used verbatim.
     * A field may also be a Model, which becomes a nested NEW expression.
     *
     * @template TModel of object
     *
     * @param class-string<TModel>                 $class
     * @param non-empty-list<string|Model<object>> $fields
     *
     * @return Collection<int, TModel>
     */
    public function models(
        string $class,
        array $fields
    ): Collection {
        /** @var list<TModel> $result */
        $result = $this->executeNew([Model::of($class, $fields)]);

        return new ArrayCollection($result);
    }

    /**
     * @template TModel of object
     *
     * @param class-string<TModel>                 $class
     * @param non-empty-list<string|Model<object>> $fields
     *
     * @return TModel|null
     */
    public function model(
        string $class,
        array $fields
    ): object|null {
        return $this->withSingleResult(fn () => $this->models($class, $fields)[0] ?? null);
    }

    /**
     * Hydrates several side-by-side models per row: each row is an array keyed by the given
     * aliases, each holding the corresponding Model instance.
     *
     * @param non-empty-array<string, Model<object>> $models
     *
     * @return Collection<int, array<string, object>>
     */
    public function modelRows(
        array $models
    ): Collection {
        $alias = array_key_first($models);
        $rows = [];

        // Doctrine unwraps a single select expression to the bare object, so re-key it by its alias
        foreach ($this->executeNew($models) as $row) {
            /** @var array<string, object> $row */
            $row = is_array($row) ? $row : [$alias => $row];
            $rows[] = $row;
        }

        return new ArrayCollection($rows);
    }

    /**
     * @param non-empty-array<string, Model<object>> $models
     *
     * @return array<string, object>|null
     */
    public function modelRow(
        array $models
    ): array|null {
        return $this->withSingleResult(fn () => $this->modelRows($models)[0] ?? null);
    }

    public function count(): int
    {
        assert($this->validateQueryBuilder());

        $id = 'entity.'.$this->getIdentifierField();
        $distinct = $this->isDistinct();

        return (int)(clone $this->queryBuilder)
            ->distinct(false)
            ->select($distinct ? 'COUNT(DISTINCT '.$id.')' : 'COUNT(1)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Collection<int, TId>
     */
    public function ids(): Collection
    {
        assert($this->validateQueryBuilder());

        $query = (clone $this->queryBuilder)
            ->distinct($this->isDistinct())
            ->select('entity.'.$this->getIdentifierField())
            ->getQuery();

        if (null !== $this->lockMode) {
            $query->setLockMode($this->lockMode);
        }

        /** @phpstan-ignore-next-line */
        return new ArrayCollection($query->getSingleColumnResult());
    }

    private function getMainFrom(): From|null
    {
        /** @var list<From> $parts */
        $parts = $this->queryBuilder->getDQLPart('from');

        $main = null;

        foreach ($parts as $part) {
            if ('entity' === $part->getAlias()) {
                $main = $part;
                break;
            }
        }

        return $main;
    }

    private function getIdentifierField(): string
    {
        $main = $this->getMainFrom();

        assert(null !== $main);

        $class = $main->getFrom();
        $em = $this->queryBuilder->getEntityManager();

        return $em->getClassMetadata($class)->getIdentifierFieldNames()[0];
    }

    /**
     * @param non-empty-array<array-key, Model<object>> $models
     *
     * @return list<mixed>
     */
    private function executeNew(
        array $models
    ): array {
        assert($this->validateQueryBuilder());

        $selects = [];

        foreach ($models as $alias => $model) {
            $selects[] = is_string($alias) ? $this->toNewExpression($model).' AS '.$alias : $this->toNewExpression($model);
        }

        $qb = (clone $this->queryBuilder);
        $qb->distinct($this->isDistinct());
        $qb->select(...$selects);

        $query = $qb->getQuery();

        if (null !== $this->lockMode) {
            $query->setLockMode($this->lockMode);
        }

        /** @var list<mixed> $result */
        $result = $query->getResult();

        return $result;
    }

    /**
     * @param Model<object> $model
     */
    private function toNewExpression(
        Model $model
    ): string {
        $arguments = [];

        foreach ($model->getFields() as $field) {
            $arguments[] = $field instanceof Model ? $this->toNewExpression($field) : $this->qualifyField($field);
        }

        return sprintf('NEW %s(%s)', $model->getClass(), implode(', ', $arguments));
    }

    private function qualifyField(
        string $field
    ): string {
        return 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) ? 'entity.'.$field : $field;
    }

    private function validateQueryBuilder(): bool
    {
        return null !== $this->getMainFrom();
    }

    /**
     * @template TCallable
     *
     * @param callable(): TCallable $callable
     *
     * @return TCallable
     */
    private function withSingleResult(
        callable $callable
    ): mixed {
        $limit = $this->queryBuilder->getMaxResults();
        $this->limit(1);

        try {
            return $callable();
        } finally {
            $this->limit($limit);
        }
    }

    private function isDistinct(): bool
    {
        return $this->distinct || true === $this->queryBuilder->getDQLPart('distinct');
    }
}
