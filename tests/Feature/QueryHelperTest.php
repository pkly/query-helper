<?php

declare(strict_types=1);

namespace Tests\Pkly\QueryHelper\Feature;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pkly\QueryHelper\QueryHelper;
use Tests\Pkly\QueryHelper\Fixtures\Entity\DummyEntity;

#[CoversClass(QueryHelper::class)]
#[Group('feature')]
#[Group('query-helper')]
class QueryHelperTest extends TestCase
{
    private EntityManager $em;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $classes = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($classes);
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testGetQueryBuilder(): void
    {
        $qb = $this->qb();
        $helper = new QueryHelper($qb);

        static::assertSame($qb, $helper->getQueryBuilder());
    }

    public function testList(): void
    {
        $this->createMany(10);

        $list = new QueryHelper($this->qb())->list();

        static::assertCount(10, $list);

        foreach ($list as $item) {
            static::assertInstanceOf(DummyEntity::class, $item);
        }
    }

    public function testItReturnsEmptyCollectionWhenNoEntitiesExist(): void
    {
        static::assertCount(0, new QueryHelper($this->qb())->list());
    }

    public function testValue(): void
    {
        $this->createMany(2);

        $result = new QueryHelper($this->qbById(1))->value();

        static::assertInstanceOf(DummyEntity::class, $result);
        static::assertSame(1, $result->getId());
    }

    public function testValueReturnsNullWhenNotFound(): void
    {
        static::assertNull(new QueryHelper($this->qbById(99))->value());
    }

    public function testIds(): void
    {
        $this->createMany(5);

        $ids = new QueryHelper($this->qb())->ids();

        static::assertCount(5, $ids);

        foreach ($ids as $id) {
            static::assertIsInt($id);
        }
    }

    public function testId(): void
    {
        $this->createMany(1);

        static::assertSame(1, new QueryHelper($this->qbById(1))->id());
    }

    public function testIdReturnsNullWhenNotFound(): void
    {
        static::assertNull(new QueryHelper($this->qbById(99))->id());
    }

    public function testExistsReturnsTrueWhenNotEmpty(): void
    {
        $this->createMany(1);

        static::assertTrue(new QueryHelper($this->qb())->exists());
    }

    public function testExistsReturnsFalseWhenEmpty(): void
    {
        static::assertFalse(new QueryHelper($this->qb())->exists());
    }

    public function testCount(): void
    {
        $this->createMany(10);

        static::assertSame(10, new QueryHelper($this->qb())->count());
    }

    public function testReference(): void
    {
        $this->createMany(1);
        $this->em->clear();

        $reference = new QueryHelper($this->qbById(1))->reference();

        static::assertNotNull($reference);
        static::assertSame(1, $reference->getId());
        static::assertTrue($this->em->isUninitializedObject($reference));
    }

    public function testReferences(): void
    {
        $this->createMany(3);
        $this->em->clear();

        $references = new QueryHelper($this->qb())->references();
        $this->em->clear();

        static::assertCount(3, $references);

        foreach ($references as $index => $reference) {
            static::assertSame($index + 1, $reference->getId());
            static::assertTrue($this->em->isUninitializedObject($reference));
        }
    }

    public function testFields(): void
    {
        $this->createMany(1);
        $this->em->clear();

        $fields = new QueryHelper($this->qbById(1))->fields(['d.id']);

        static::assertNotNull($fields);
        static::assertSame(1, $fields['id']);
    }

    public function testFieldsWithMethod(): void
    {
        $this->createMany(1);
        $this->em->clear();

        $fields = new QueryHelper($this->qbById(1))->fields(['CONCAT(\'test_\' , d.id) as prefixed_id']);

        static::assertNotNull($fields);
        static::assertSame('test_1', $fields['prefixed_id']);
    }

    public function testFieldList(): void
    {
        $this->createMany(2);
        $this->em->clear();

        $helper = new QueryHelper($this->qb());

        $fields = $helper->fieldList(['d.id']);

        static::assertCount(2, $fields);

        foreach ($fields as $field) {
            static::assertArrayHasKey('id', $field);
        }

        $fields = $helper->fieldList(['id']);

        static::assertCount(2, $fields);

        foreach ($fields as $field) {
            static::assertArrayHasKey('id', $field);
        }
    }

    public function testFieldListWithMethod(): void
    {
        $this->createMany(2);
        $this->em->clear();

        $fields = new QueryHelper($this->qb())->fieldList(['CONCAT(\'test_\', d.id) as prefixed_id']);

        static::assertCount(2, $fields);

        foreach ($fields as $field) {
            static::assertArrayHasKey('prefixed_id', $field);
            static::assertStringStartsWith('test_', $field['prefixed_id']);
        }
    }

    public function testLimit(): void
    {
        $this->createMany(10);
        $this->em->clear();

        $list = new QueryHelper($this->qb())->limit(5)->list();

        static::assertCount(5, $list);
    }

    public function testOffset(): void
    {
        $this->createMany(10);
        $this->em->clear();

        $list = (new QueryHelper($this->qb()->orderBy('d.id', 'ASC')))->offset(5)->list();

        static::assertCount(5, $list);
    }

    public function testOrderBy(): void
    {
        $this->createMany(5);
        $this->em->clear();

        /** @var Collection<int, DummyEntity> $list */
        $list = new QueryHelper($this->qb())->orderBy('d.id', Order::Descending)->list();

        $ids = $list->map(static fn (DummyEntity $e): int => $e->getId())->toArray();
        static::assertSame([5, 4, 3, 2, 1], $ids);
    }

    public function testAddOrderBy(): void
    {
        $this->createMany(5);
        $this->em->clear();

        $helper = new QueryHelper($this->qb())
            ->orderBy('d.id', Order::Descending)
            ->addOrderBy('d.name', Order::Ascending);

        $list = $helper->list();

        static::assertCount(5, $list);
        static::assertSame(5, $list[0]->getId());
        static::assertCount(2, $helper->getQueryBuilder()->getDQLPart('orderBy'));
    }

    public function testDistinct(): void
    {
        $this->createMany(2);
        $this->em->clear();

        $fields = new QueryHelper($this->qb())->distinct()->fieldList(['\'1\' as value']);

        static::assertCount(1, $fields);
        static::assertSame('1', $fields[0]['value']);
    }

    public function testLockMode(): void
    {
        $this->createMany(1);

        $this->em->beginTransaction();

        try {
            $result = new QueryHelper($this->qbById(1))
                ->lockMode(LockMode::NONE)
                ->value();

            static::assertInstanceOf(DummyEntity::class, $result);
        } finally {
            $this->em->rollback();
        }
    }

    public function testCombinedLimitOffsetAndOrderBy(): void
    {
        $this->createMany(10);
        $this->em->clear();

        /** @var Collection<int, DummyEntity> $list */
        $list = new QueryHelper($this->qb())
            ->orderBy('d.id')
            ->limit(3)
            ->offset(4)
            ->list();

        static::assertCount(3, $list);
        static::assertSame(5, $list[0]->getId());
        static::assertSame(6, $list[1]->getId());
        static::assertSame(7, $list[2]->getId());
    }

    public function testCombinedDistinctAndFieldList(): void
    {
        $this->createMany(5);
        $this->em->clear();

        $fields = new QueryHelper($this->qb())
            ->distinct()
            ->fieldList(['\'fixed_value\' as value']);

        static::assertCount(1, $fields);
        static::assertSame('fixed_value', $fields[0]['value']);
    }

    public function testLimitResetsAfterSingleResult(): void
    {
        $this->createMany(5);
        $this->em->clear();

        $qb = $this->qb();
        $qb->setMaxResults(10);

        new QueryHelper($qb)->value();

        static::assertSame(10, $qb->getMaxResults());
    }

    // --- Helpers ---

    private function qb(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(DummyEntity::class, 'd');
    }

    private function qbById(
        int $id
    ): QueryBuilder {
        return $this->qb()
            ->where('d.id = :id')
            ->setParameter('id', $id);
    }

    private function createMany(
        int $count
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $this->em->persist(new DummyEntity());
        }

        $this->em->flush();
        $this->em->clear();
    }
}
