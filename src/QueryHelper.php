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

        if ($this->distinct) {
            $qb->select(sprintf('DISTINCT %s', $this->getRootAlias()));
        }

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
            $selects[] = !ctype_alpha($field) ? $field : sprintf('%s.%s', $this->getRootAlias(), $field);
        }

        $qb->select(...$selects);

        if ($this->distinct) {
            $qb->distinct();
        }

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

    public function count(): int
    {
        assert($this->validateQueryBuilder());

        return (int)(clone $this->queryBuilder)
            ->select($this->distinct ? sprintf('COUNT(DISTINCT %s.id)', $this->getRootAlias()) : 'COUNT(1)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Collection<int, TId>
     */
    public function ids(): Collection
    {
        assert($this->validateQueryBuilder());

        /** @phpstan-ignore-next-line */
        return new ArrayCollection(
            (clone $this->queryBuilder)
                ->select(($this->distinct ? 'DISTINCT ' : '').sprintf('%s.id', $this->getRootAlias()))
                ->getQuery()
                ->getSingleColumnResult()
        );
    }

    private function getMainFrom(): From|null
    {
        /** @var list<From> $parts */
        $parts = $this->queryBuilder->getDQLPart('from');

        $main = null;

        foreach ($parts as $part) {
            if ($this->getRootAlias() === $part->getAlias()) {
                $main = $part;
                break;
            }
        }

        return $main;
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

        $result = $callable();

        $this->limit($limit);

        return $result;
    }

    private function getRootAlias(): string
    {
        return $this->queryBuilder->getRootAliases()[0] ?? throw new \RuntimeException('No root alias found');
    }
}
