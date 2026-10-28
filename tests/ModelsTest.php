<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests;

use Doctrine\Common\Collections\Order;
use Pkly\QueryHelper\Model;
use Pkly\QueryHelper\QueryHelper;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Post;
use Pkly\QueryHelper\Tests\Fixtures\Entity\User;
use Pkly\QueryHelper\Tests\Fixtures\Model\PostDetails;
use Pkly\QueryHelper\Tests\Fixtures\Model\PostSummary;
use Pkly\QueryHelper\Tests\Fixtures\Model\UserSummary;

class ModelsTest extends AbstractDoctrineTestCase
{
    public function testModelsReturnsEmptyCollectionWhenNoRows(): void
    {
        $helper = new QueryHelper($this->getEntityManager()->getRepository(User::class)->createQueryBuilder('entity'));

        static::assertCount(0, $helper->models(UserSummary::class, ['id', 'name']));
    }

    public function testModelsHydratesTypedObjects(): void
    {
        $alice = new User('Alice', 'alice@example.com');
        $bob = new User('Bob', 'bob@example.com');
        $this->persist($alice, $bob);

        $helper = new QueryHelper($this->getEntityManager()->getRepository(User::class)->createQueryBuilder('entity'));

        $models = $helper
            ->orderBy('entity.name', Order::Ascending)
            ->models(UserSummary::class, ['id', 'name']);

        static::assertCount(2, $models);
        static::assertSame($alice->getId(), $models[0]?->getId());
        static::assertSame('Alice', $models[0]?->getName());
        static::assertSame($bob->getId(), $models[1]?->getId());
        static::assertSame('Bob', $models[1]?->getName());
    }

    public function testModelsUsesSelectNewAndDoesNotHydrateEntities(): void
    {
        $this->persist(new User('Alice', 'alice@example.com'));

        $em = $this->getEntityManager();
        $em->clear();

        $helper = new QueryHelper($em->getRepository(User::class)->createQueryBuilder('entity'));

        $models = $helper->models(UserSummary::class, ['id', 'name']);

        static::assertCount(1, $models);
        static::assertInstanceOf(UserSummary::class, $models[0]);
        static::assertFalse($em->getUnitOfWork()->isInIdentityMap($models[0]));
        static::assertSame(0, $em->getUnitOfWork()->size());
    }

    public function testModelsAcceptsQualifiedPathsAndFunctions(): void
    {
        $alice = new User('Alice', 'alice@example.com');
        $this->persist($alice, new Post('Hello', $alice));

        $helper = new QueryHelper($this->getEntityManager()->getRepository(Post::class)->createQueryBuilder('entity'));

        $models = $helper->models(PostSummary::class, ['IDENTITY(entity.author)', 'entity.title']);

        static::assertCount(1, $models);
        static::assertSame($alice->getId(), $models[0]?->getAuthorId());
        static::assertSame('Hello', $models[0]?->getTitle());
    }

    public function testModelsRespectsDistinct(): void
    {
        $alice = new User('Alice', 'alice@example.com');
        $this->persist($alice, new Post('A', $alice), new Post('B', $alice));

        $helper = new QueryHelper($this->getEntityManager()->getRepository(Post::class)->createQueryBuilder('entity'));

        $sql = $this->captureLastSql(
            fn () => $helper->distinct()->models(PostSummary::class, ['IDENTITY(entity.author)', 'entity.title'])
        );

        static::assertStringContainsStringIgnoringCase('SELECT DISTINCT', $sql);
    }

    public function testModelsHydratesNestedModels(): void
    {
        $alice = new User('Alice', 'alice@example.com');
        $post = new Post('Hello', $alice);
        $this->persist($alice, $post);

        $helper = new QueryHelper(
            $this->getEntityManager()->getRepository(Post::class)->createQueryBuilder('entity')
                ->join('entity.author', 'a')
        );

        $models = $helper->models(PostDetails::class, [
            'id',
            'title',
            Model::of(UserSummary::class, ['a.id', 'a.name']),
        ]);

        static::assertCount(1, $models);
        $details = $models[0];
        static::assertInstanceOf(PostDetails::class, $details);
        static::assertSame($post->getId(), $details->getId());
        static::assertSame('Hello', $details->getTitle());
        static::assertSame($alice->getId(), $details->getAuthor()->getId());
        static::assertSame('Alice', $details->getAuthor()->getName());
    }

    public function testModelReturnsNullWhenNoMatch(): void
    {
        $helper = new QueryHelper($this->getEntityManager()->getRepository(User::class)->createQueryBuilder('entity'));

        static::assertNull($helper->model(UserSummary::class, ['id', 'name']));
    }

    public function testModelReturnsSingleObjectAndDoesNotMutateLimit(): void
    {
        $this->persist(new User('Alice', 'alice@example.com'), new User('Bob', 'bob@example.com'));

        $helper = new QueryHelper($this->getEntityManager()->getRepository(User::class)->createQueryBuilder('entity'));
        $helper->limit(10);

        $model = $helper->model(UserSummary::class, ['id', 'name']);

        static::assertInstanceOf(UserSummary::class, $model);
        static::assertCount(2, $helper->models(UserSummary::class, ['id', 'name']));
    }

    public function testModelRowsHydratesSideBySideModels(): void
    {
        $alice = new User('Alice', 'alice@example.com');
        $this->persist($alice, new Post('Hello', $alice));

        $helper = new QueryHelper(
            $this->getEntityManager()->getRepository(Post::class)->createQueryBuilder('entity')
                ->join('entity.author', 'a')
        );

        $rows = $helper->modelRows([
            'post' => Model::of(PostSummary::class, ['IDENTITY(entity.author)', 'title']),
            'author' => Model::of(UserSummary::class, ['a.id', 'a.name']),
        ]);

        static::assertCount(1, $rows);
        $row = $rows[0];
        static::assertIsArray($row);
        static::assertSame(['post', 'author'], array_keys($row));
        static::assertInstanceOf(PostSummary::class, $row['post']);
        static::assertInstanceOf(UserSummary::class, $row['author']);
        static::assertSame('Hello', $row['post']->getTitle());
        static::assertSame($alice->getId(), $row['post']->getAuthorId());
        static::assertSame('Alice', $row['author']->getName());
    }

    public function testModelRowReturnsNullWhenNoMatch(): void
    {
        $helper = new QueryHelper($this->getEntityManager()->getRepository(Post::class)->createQueryBuilder('entity'));

        static::assertNull($helper->modelRow([
            'post' => Model::of(PostSummary::class, ['IDENTITY(entity.author)', 'title']),
        ]));
    }

    public function testModelRowReturnsSingleRowAndDoesNotMutateLimit(): void
    {
        $alice = new User('Alice', 'alice@example.com');
        $this->persist($alice, new Post('A', $alice), new Post('B', $alice));

        $helper = new QueryHelper($this->getEntityManager()->getRepository(Post::class)->createQueryBuilder('entity'));
        $helper->limit(10);

        $models = ['post' => Model::of(PostSummary::class, ['IDENTITY(entity.author)', 'title'])];

        $row = $helper->modelRow($models);

        static::assertIsArray($row);
        static::assertInstanceOf(PostSummary::class, $row['post']);
        static::assertCount(2, $helper->modelRows($models));
    }
}
